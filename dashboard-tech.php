<?php

session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/lang.php';
require_once 'includes/services.php';


if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'technicien') {
    header("Location: login.php"); exit();
}

$lang = get_lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT t.*, u.* FROM technicien t JOIN utilisateur u ON t.id_user = u.id_user WHERE u.id_user = ?");
$stmt->execute([$user_id]);
$tech = $stmt->fetch();
if (!$tech) { header("Location: login.php"); exit(); }
if (($tech['statut_compte'] ?? 'actif') === 'suspendu') {
    session_destroy();
    $raison = $tech['raison_suspension'] ? htmlspecialchars($tech['raison_suspension']) : '';
    echo "<div style='font-family:sans-serif;padding:40px;max-width:500px;margin:60px auto;border:1px solid #fca5a5;border-radius:12px;background:#fef2f2'>
        <h2 style='color:#dc2626;margin-bottom:10px'>Compte suspendu</h2>
        <p style='color:#6b7280'>Votre compte a été suspendu par l'administration".($raison?" : <strong>$raison</strong>":'.')."</p>
        <p style='color:#6b7280;margin-top:10px'>Pour contacter le support : <a href='mailto:contact@khidmadz.dz'>contact@khidmadz.dz</a></p>
        <a href='login.php' style='display:inline-block;margin-top:20px;padding:10px 20px;background:#06b6d4;color:#fff;border-radius:8px;text-decoration:none'>Retour à la connexion</a>
    </div>"; exit();
}
$tech_id = $tech['id_technicien'];



// Backfill old prestations created before id_catalogue was persisted.
if (!empty($tech['specialité'])) {
  $bf = $pdo->prepare("UPDATE prestation p JOIN prestation_catalogue pc ON pc.specialite=? AND pc.nom=p.nom SET p.id_catalogue=pc.id_catalogue WHERE p.id_technicien=? AND p.id_catalogue IS NULL");
  $bf->execute([$tech['specialité'], $tech_id]);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'accept_service' && isset($_POST['id_service'])) {
        $pdo->prepare("UPDATE service SET statut_service='en cours' WHERE id_service=? AND id_technicien=?")->execute([$_POST['id_service'],$tech_id]);
        // Notify client + send email
        $cl=$pdo->prepare("SELECT u.id_user as cuid, u.nom, u.prenom, u.email, u.langue, s.date_service FROM service s JOIN client c ON c.id_client=s.id_client JOIN utilisateur u ON u.id_user=c.id_user WHERE s.id_service=?");
        $cl->execute([$_POST['id_service']]); $cr=$cl->fetch();
        if ($cr) {
            $cl2=$cr['langue']??'fr';
            notifier($pdo,$cr['cuid'],$cl2==='ar'?'تم قبول طلبك ✓':'Réservation acceptée ✓',$cl2==='ar'?'طلبك رقم #'.$_POST['id_service'].' تم قبوله من قِبل التقني.':'Votre demande #'.$_POST['id_service'].' a été acceptée par le technicien.','dashboard-client.php?tab=reservations');
            require_once 'includes/mailer.php';
            send_reservation_confirmed($cr['email'], $cr['nom'], $cr['prenom'], $tech['prenom'], $cr['date_service']);
        }
        log_action($pdo,$user_id,'accept_service','Service #'.$_POST['id_service']);
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم قبول الطلب ✓':'Demande acceptée ✓','type'=>'ok'];
    }
    if ($action === 'refuse_service' && isset($_POST['id_service'])) {
        $sid = intval($_POST['id_service']);
      try {
        $pdo->beginTransaction();

        $up = $pdo->prepare("UPDATE service 
                     SET statut_service='annulé', prix_final=NULL 
                     WHERE id_service=? 
                       AND id_technicien=? 
                       AND statut_service IN ('planifié','en cours')");
        $up->execute([$sid,$tech_id]);
        if ($up->rowCount() === 0) {
          $pdo->rollBack();
          $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تعذر إلغاء الخدمة':'Annulation impossible pour ce service','type'=>'err'];
        } else {
          // Clean up facture/paiment/avis for canceled services
          $pdo->prepare("DELETE FROM paiment WHERE id_service=?")->execute([$sid]);
          $pdo->prepare("DELETE FROM facture WHERE id_service=?")->execute([$sid]);
          $pdo->prepare("DELETE FROM avis WHERE id_service=?")->execute([$sid]);

          $cl=$pdo->prepare("SELECT c.id_user as cuid FROM service s JOIN client c ON c.id_client=s.id_client WHERE s.id_service=?");
          $cl->execute([$sid]); $cr=$cl->fetch();
          if ($cr) notifier($pdo,$cr['cuid'],'Service annulé','Le technicien a annulé votre service #'.$sid.'.','dashboard-client.php?tab=reservations');
          log_action($pdo,$user_id,'refuse_service','Service #'.$sid);
          $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم إلغاء الخدمة':'Service annulé','type'=>'err'];
          $pdo->commit();
        }
      } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'خطأ أثناء الإلغاء':'Erreur pendant l\'annulation','type'=>'err'];
      }
    }
    if ($action === 'complete_service' && isset($_POST['id_service']) && isset($_POST['complete_confirm'])) {
        $sid = intval($_POST['id_service']);
      $status_check = $pdo->prepare("SELECT statut_service FROM service WHERE id_service=? AND id_technicien=?");
      $status_check->execute([$sid,$tech_id]);
      $current_status = $status_check->fetchColumn();
      if ($current_status !== 'en cours') {
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'لا يمكن إنهاء خدمة ملغاة أو غير جارية':'Impossible de terminer un service annulé ou non en cours','type'=>'err'];
        $tab = $_POST['tab'] ?? 'demandes';
        header("Location: dashboard-tech.php?tab=$tab");
        exit();
      }
        // Save updated prestations from the confirm form first
        if (!empty($_POST['prestations']) && is_array($_POST['prestations'])) {
            // Fetch previous totals before changing prestations (to compute incremental amount)
            $prev_calc = $pdo->prepare("SELECT COALESCE(SUM(p.prix_unitaire * sp.quantite),0) as total_ht FROM service_prestation sp JOIN prestation p ON p.id_prestation=sp.id_prestation WHERE sp.id_service=?");
            $prev_calc->execute([$sid]); $prev_total_ht = floatval($prev_calc->fetchColumn());
            $prev_service = $pdo->prepare("SELECT COALESCE(prix_final,0) as prix_final FROM service WHERE id_service=?");
            $prev_service->execute([$sid]); $prev_prix_final = floatval($prev_service->fetchColumn());

            $pdo->prepare("DELETE FROM service_prestation WHERE id_service=?")->execute([$sid]);
            $ins = $pdo->prepare("INSERT IGNORE INTO service_prestation (id_service,id_prestation,quantite) VALUES (?,?,?)");
            foreach ($_POST['prestations'] as $pid) {
                $qty = max(1, intval($_POST['qty_'.intval($pid)] ?? 1));
                $ins->execute([$sid, intval($pid), $qty]);
            }
        }
        $pdo->prepare("UPDATE service SET statut_service='terminé' WHERE id_service=? AND id_technicien=?")->execute([$sid,$tech_id]);
        // Auto-calculate total from final prestations
        $calc = $pdo->prepare("SELECT COALESCE(SUM(p.prix_unitaire * sp.quantite),0) as total FROM service_prestation sp JOIN prestation p ON p.id_prestation=sp.id_prestation WHERE sp.id_service=?");
        $calc->execute([$sid]); $total = floatval($calc->fetchColumn());
       // Save prix_final — facture generated after payment confirmed in paiment.php
$pdo->prepare("UPDATE service SET prix_final=? WHERE id_service=?")->execute([$total,$sid]);
        // Payment will be confirmed via paiment.php — don't auto-mark as paid
$pmchk = $pdo->prepare("SELECT id_paiment FROM paiment WHERE id_service=?");
$pmchk->execute([$sid]);
if (!$pmchk->fetch()) {
    $pdo->prepare("INSERT INTO paiment (id_service,mode_paiment,montant,statut_paiment) VALUES (?,'espèces',?,'en_attente')")->execute([$sid,$total]);
} else {
    $pdo->prepare("UPDATE paiment SET montant=?,statut_paiment='en_attente' WHERE id_service=?")->execute([$total,$sid]);
}
        $cl=$pdo->prepare("SELECT u.id_user as cuid, u.langue FROM service s JOIN client c ON c.id_client=s.id_client JOIN utilisateur u ON u.id_user=c.id_user WHERE s.id_service=?");
        $cl->execute([$sid]); $cr=$cl->fetch();
        if ($cr) { $cl3=$cr['langue']??'fr'; notifier($pdo,$cr['cuid'],$cl3==='ar'?'الخدمة منتهية ✓':'Service terminé ✓',$cl3==='ar'?'خدمتك منتهية. الفاتورة ('.number_format($total,0).' DA) متاحة.':'Votre service est terminé. Facture de '.number_format($total,0).' DA disponible.','dashboard-client.php?tab=reservations'); }
        log_action($pdo,$user_id,'complete_service','Service #'.$sid.' total '.$total.' DA');
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'الخدمة منتهية ✓ — فاتورة '.number_format($total,0).' DA صدرت':'Service terminé ✓ — Facture de '.number_format($total,0).' DA générée','type'=>'ok'];
    }
    if ($action === 'add_prestation') {
      $catalogue_id = intval($_POST['id_catalogue'] ?? 0);
      $nom = trim($_POST['nom']??'');
      if ($nom !== '') {
        if ($catalogue_id > 0) {
          $cat = $pdo->prepare("SELECT nom FROM prestation_catalogue WHERE id_catalogue=? AND specialite=? LIMIT 1");
          $cat->execute([$catalogue_id, $tech['specialité'] ?? '']);
          $cat_nom = $cat->fetchColumn();
          if ($cat_nom) {
            $nom = $cat_nom;
          }
        }
        $pdo->prepare("INSERT INTO prestation (id_technicien,id_catalogue,nom,description,prix_unitaire,unite,actif) VALUES (?,?,?,?,?,?,1)")->execute([
          $tech_id,
          $catalogue_id > 0 ? $catalogue_id : null,
          $nom,
          trim($_POST['description']??''),
          floatval($_POST['prix_unitaire']??0),
          trim($_POST['unite']??'heure')
        ]);
            log_action($pdo,$user_id,'add_prestation',$nom);
            $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تمت إضافة الخدمة ✓':'Prestation ajoutée ✓','type'=>'ok'];
        }
    }
    if ($action === 'update_prestation' && isset($_POST['id_prestation'])) {
        $pid = intval($_POST['id_prestation']);
        $pdo->prepare("UPDATE prestation SET nom=?,description=?,prix_unitaire=?,unite=? WHERE id_prestation=? AND id_technicien=?")->execute([
            trim($_POST['nom']??''), trim($_POST['description']??''),
            floatval($_POST['prix_unitaire']??0), trim($_POST['unite']??'heure'),
            $pid, $tech_id
        ]);
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم تعديل الخدمة ✓':'Prestation modifiée ✓','type'=>'ok'];
    }
    if ($action === 'update_service_prestations' && isset($_POST['id_service'])) {
        $sid = intval($_POST['id_service']);
      $allow_after_completion = !empty($_POST['modifier_confirm']);
        $chk = $pdo->prepare("SELECT statut_service FROM service WHERE id_service=? AND id_technicien=?");
        $chk->execute([$sid,$tech_id]); $svc=$chk->fetch();
        if ($svc && ($svc['statut_service']==='planifié' || ($svc['statut_service']==='terminé' && $allow_after_completion))) {
          $existing_qtys = [];
          // Fetch previous totals/prix_final when service was already terminated
          $prev_total_ht = 0.0;
          $prev_prix_final = 0.0;
          if ($svc['statut_service']==='terminé') {
            $oq = $pdo->prepare("SELECT id_prestation, quantite FROM service_prestation WHERE id_service=?");
            $oq->execute([$sid]);
            foreach ($oq->fetchAll(PDO::FETCH_ASSOC) as $row) {
              $existing_qtys[(int)$row['id_prestation']] = max(1, intval($row['quantite']));
            }
            $prev_calc = $pdo->prepare("SELECT COALESCE(SUM(p.prix_unitaire * sp.quantite),0) as total_ht FROM service_prestation sp JOIN prestation p ON p.id_prestation=sp.id_prestation WHERE sp.id_service=?");
            $prev_calc->execute([$sid]); $prev_total_ht = floatval($prev_calc->fetchColumn());
            $prev_service = $pdo->prepare("SELECT COALESCE(prix_final,0) as prix_final FROM service WHERE id_service=?");
            $prev_service->execute([$sid]); $prev_prix_final = floatval($prev_service->fetchColumn());
          }
          $pdo->prepare("DELETE FROM service_prestation WHERE id_service=?")->execute([$sid]);
          if (!empty($_POST['prestations']) && is_array($_POST['prestations'])) {
            $ins = $pdo->prepare("INSERT INTO service_prestation (id_service,id_prestation,quantite) VALUES (?,?,?)");
            foreach ($_POST['prestations'] as $pid) {
              $pid = intval($pid);
              $posted_qty = max(1,intval($_POST['qty_'.$pid]??1));
              $min_qty = $existing_qtys[$pid] ?? 1;
              $qty = max($min_qty, $posted_qty);
              $ins->execute([$sid,$pid,$qty]);
            }
          }
        $calc = $pdo->prepare("SELECT COALESCE(SUM(p.prix_unitaire * sp.quantite),0) as total FROM service_prestation sp JOIN prestation p ON p.id_prestation=sp.id_prestation WHERE sp.id_service=?");
        $calc->execute([$sid]); $new_total_ht = floatval($calc->fetchColumn());

        // Compute incremental amount (client should pay only the added prestations if service was already paid)
        $incremental_ht = max(0, $new_total_ht - ($prev_total_ht ?? 0));
        $use_facture_increment = facture_uses_tva($incremental_ht);
        $tva_inc = $use_facture_increment ? round($incremental_ht * 0.19, 2) : 0;
        $inc_amount = round($incremental_ht + $tva_inc, 2);

        // Update service prix_final to include the incremental amount
        $new_prix_final = round($prev_prix_final + $inc_amount, 2);
        $pdo->prepare("UPDATE service SET prix_final=? WHERE id_service=?")->execute([$new_prix_final,$sid]);

        // If service was terminated and we're allowing modifications after completion,
        // create a separate paiment row for the incremental amount so the client pays only that.
        if ($svc['statut_service']==='terminé' && $allow_after_completion) {
            if ($inc_amount > 0) {
                $pdo->prepare("INSERT INTO paiment (id_service,mode_paiment,montant,statut_paiment) VALUES (?,'espèces',?,'en_attente')")->execute([$sid,$inc_amount]);
                if ($use_facture_increment) {
                    $pdo->prepare("INSERT INTO facture (id_service,date_facture,montant_total,statut_facture) VALUES (?,CURDATE(),?,'émise')")->execute([$sid,$inc_amount]);
                }
            }
        } else {
            // For planifié or non-terminal edits, keep single paiment for full amount
            $use_facture = facture_uses_tva($new_total_ht);
            $tva_amount = $use_facture ? round($new_total_ht * 0.19, 2) : 0;
            $final_amount = round($new_total_ht + $tva_amount, 2);
            $pm = $pdo->prepare("SELECT id_paiment FROM paiment WHERE id_service=? ORDER BY id_paiment DESC LIMIT 1");
            $pm->execute([$sid]);
            $pm_id = $pm->fetchColumn();
            if ($pm_id) {
              $pdo->prepare("UPDATE paiment SET montant=?, statut_paiment='en_attente', date_paiment=NULL WHERE id_paiment=?")->execute([$final_amount,$pm_id]);
            } else {
              $pdo->prepare("INSERT INTO paiment (id_service,mode_paiment,montant,statut_paiment) VALUES (?,'espèces',?,'en_attente')")->execute([$sid,$final_amount]);
            }
            if ($svc['statut_service']==='terminé' && $use_facture) {
                $pdo->prepare("INSERT INTO facture (id_service,date_facture,montant_total,statut_facture) VALUES (?,CURDATE(),?,'émise')")->execute([$sid,$final_amount]);
            }
        }
        // Ensure $use_facture is defined for the notification message
        if (!isset($use_facture)) $use_facture = facture_uses_tva($new_total_ht);

        if ($svc['statut_service']==='terminé') {
          if (!empty($final_amount) && $use_facture) {
            $pdo->prepare("INSERT INTO facture (id_service,date_facture,montant_total,statut_facture) VALUES (?,CURDATE(),?,'émise')")->execute([$sid,$final_amount]);
          }
          $cl = $pdo->prepare("SELECT u.id_user as cuid, u.langue FROM service s JOIN client c ON c.id_client=s.id_client JOIN utilisateur u ON u.id_user=c.id_user WHERE s.id_service=?");
          $cl->execute([$sid]);
          $cr = $cl->fetch();
          if ($cr) {
            $cl4 = $cr['langue'] ?? 'fr';
            $notify_amount = isset($final_amount) ? $final_amount : (isset($new_prix_final) ? $new_prix_final : (isset($inc_amount) ? $inc_amount : 0));
            $notify_facture = isset($use_facture_increment) ? $use_facture_increment : ($use_facture ?? false);
            $msg = $cl4==='ar'
              ? ('تم تحديث قيمة الخدمة #'.$sid.' إلى '.number_format($notify_amount,0).' دج. يرجى تأكيد الدفع.'.($notify_facture?' تم إصدار فاتورة جديدة.':''))
              : ('Le montant du service #'.$sid.' a été mis à jour à '.number_format($notify_amount,0).' DA. Merci de confirmer le paiement.'.($notify_facture?' Une nouvelle facture a été générée.':''));
            notifier($pdo,$cr['cuid'],$cl4==='ar'?'تحديث مبلغ الخدمة':'Montant du service mis à jour',$msg,'paiment.php?id_service='.$sid);
          }
          $notify_amount = isset($final_amount) ? $final_amount : (isset($new_prix_final) ? $new_prix_final : (isset($inc_amount) ? $inc_amount : 0));
          $notify_facture = isset($use_facture_increment) ? $use_facture_increment : ($use_facture ?? false);
          notifier($pdo,$user_id,$lang==='ar'?'تم تحديث المبلغ':'Montant mis à jour',$lang==='ar'?('تم احتساب المبلغ الجديد: '.number_format($notify_amount,0).' دج'.($notify_facture?' مع فاتورة جديدة.':'')):('Nouveau montant calculé: '.number_format($notify_amount,0).' DA'.($notify_facture?' avec une nouvelle facture.':'')),'dashboard-tech.php?tab=demandes');
        }
            $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم تحديث الخدمات ✓':'Prestations mises à jour ✓','type'=>'ok'];
        }
    }
    if ($action === 'delete_prestation' && isset($_POST['id_prestation'])) {
        $pdo->prepare("DELETE FROM prestation WHERE id_prestation=? AND id_technicien=?")->execute([$_POST['id_prestation'],$tech_id]);
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم حذف الخدمة':'Prestation supprimée','type'=>'err'];
    }
    if ($action === 'toggle_prestation' && isset($_POST['id_prestation'])) {
        $pdo->prepare("UPDATE prestation SET actif=1-actif WHERE id_prestation=? AND id_technicien=?")->execute([$_POST['id_prestation'],$tech_id]);
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم تحديث الخدمة ✓':'Prestation mise à jour ✓','type'=>'ok'];
    }
    if ($action === 'save_dispo') {
      $tid = intval($tech_id);
      if ($tid <= 0) {
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'خطأ: معرّف التقني غير صالح':'Error: invalid technician id','type'=>'err'];
      } else {
        $pdo->prepare("DELETE FROM disponibilite WHERE id_technicien=?")->execute([$tid]);
        $jours_enabled = $_POST['jours_enabled'] ?? [];
        $jours = $_POST['jours'] ?? [];
        foreach ($jours_enabled as $jour => $_flag) {
          if (!isset($jours[$jour])) continue;
          $d = $jours[$jour];
          $debut = trim($d['debut'] ?? '');
          $fin   = trim($d['fin'] ?? '');
          if ($debut !== '' && $fin !== '') {
            $pdo->prepare("INSERT INTO disponibilite (id_disponibilite,id_technicien,jour,heure_debut,heure_fin,statut_tech) SELECT COALESCE(MAX(id_disponibilite),0)+1, ?, ?, ?, ?, 'disponible' FROM disponibilite")->execute([$tid,$jour,$debut,$fin]);
          }
        }
        log_action($pdo,$user_id,'save_dispo','');
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم حفظ التوقيت ✓':'Disponibilités enregistrées ✓','type'=>'ok'];
      }
    }
    if ($action === 'update_profile') {
        $pdo->prepare("UPDATE utilisateur SET nom=?,prenom=?,email=?,telephone=?,ville=? WHERE id_user=?")->execute([
            $_POST['nom']??$tech['nom'],$_POST['prenom']??$tech['prenom'],
            $_POST['email']??$tech['email'],$_POST['telephone']??$tech['telephone'],
            $_POST['ville']??$tech['ville'],$user_id
        ]);
        $pdo->prepare("UPDATE technicien SET specialité=?,experience=?,tarif=?,description_profil=? WHERE id_technicien=?")->execute([
            $_POST['specialite']??$tech['specialité'],intval($_POST['experience']??0),
            floatval($_POST['tarif']??0),$_POST['bio']??$tech['description_profil'],$tech_id
        ]);
        if (!empty($_POST['new_password'])) {
            $pdo->prepare("UPDATE utilisateur SET mot_de_passe=? WHERE id_user=?")->execute([password_hash($_POST['new_password'],PASSWORD_DEFAULT),$user_id]);
        }
        log_action($pdo,$user_id,'update_profile','');
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم تحديث الملف الشخصي ✓':'Profil mis à jour ✓','type'=>'ok'];
    }
    if ($action === 'send_message' && !empty($_POST['id_destinataire']) && !empty($_POST['contenu'])) {
        $pdo->prepare("INSERT INTO message (id_message,id_expediteur,id_destinataire,contenu,data_envoi,lu) SELECT COALESCE(MAX(id_message),0)+1, ?, ?, ?, NOW(), 0 FROM message")->execute([$user_id,$_POST['id_destinataire'],$_POST['contenu']]);
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم إرسال الرسالة ✓':'Message envoyé ✓','type'=>'ok'];
    
   
    }
    if ($action === 'mark_all_read') {
        $pdo->prepare("UPDATE notification SET lu=1 WHERE id_user=?")->execute([$user_id]);
        $_SESSION['toast'] = ['msg'=>$lang==='ar'?'تم تحديد الإشعارات كمقروءة':'Notifications marquées comme lues','type'=>'ok'];
    }

    $tab = $_POST['tab'] ?? 'dashboard';
    $extra = isset($_POST['with_redirect']) ? '&with='.$_POST['with_redirect'] : '';
    header("Location: dashboard-tech.php?tab=$tab$extra"); exit();
}

// --- Fetch data ---
$demandes = $pdo->prepare("
    SELECT s.id_service,s.date_service,s.statut_service,s.prix_estime,s.prix_final,s.description,
           u.nom as cn,u.prenom as cp,u.telephone as ct,u.ville as cv, u.id_user as cuid,
           p.id_paiment,p.statut_paiment,f.id_facture
    FROM service s
    JOIN client c ON s.id_client=c.id_client
    JOIN utilisateur u ON c.id_user=u.id_user
    LEFT JOIN paiment p ON p.id_service=s.id_service
    LEFT JOIN facture f ON f.id_facture = (
      SELECT MAX(f2.id_facture)
      FROM facture f2
      WHERE f2.id_service = s.id_service
    )
    WHERE s.id_technicien=?
    ORDER BY
      FIELD(s.statut_service,'planifié','en cours','terminé','annulé'),
      s.id_service DESC
");
$demandes->execute([$tech_id]); $demandes=$demandes->fetchAll();

// Fetch prestations per service
$svc_ids = array_values(array_column($demandes,'id_service'));
$svc_prestations = [];
if (!empty($svc_ids)) {
    $in = implode(',', array_fill(0, count($svc_ids), '?'));
    $sp = $pdo->prepare("SELECT sp.id_service, sp.id_prestation, sp.quantite, pr.nom, pr.prix_unitaire, pr.unite, COALESCE(pc.nom_ar,'') as nom_ar FROM service_prestation sp JOIN prestation pr ON pr.id_prestation=sp.id_prestation LEFT JOIN prestation_catalogue pc ON pc.id_catalogue=pr.id_catalogue WHERE sp.id_service IN ($in)");
    $sp->execute($svc_ids);
    foreach ($sp->fetchAll() as $row) {
        $svc_prestations[(int)$row['id_service']][] = $row;
    }
}
$new_count = count(array_filter($demandes,fn($d)=>$d['statut_service']==='planifié'));

$total_done  = count(array_filter($demandes,fn($d)=>$d['statut_service']==='terminé'));
$rev_mois_q  = $pdo->prepare("SELECT COALESCE(SUM(p.montant),0) FROM paiment p JOIN service s ON s.id_service=p.id_service WHERE s.id_technicien=? AND p.statut_paiment='payé' AND MONTH(p.date_paiment)=MONTH(CURDATE()) AND YEAR(p.date_paiment)=YEAR(CURDATE())");
$rev_mois_q->execute([$tech_id]); $rev_mois=$rev_mois_q->fetchColumn();
$rev_total_q = $pdo->prepare("SELECT COALESCE(SUM(p.montant),0) FROM paiment p JOIN service s ON s.id_service=p.id_service WHERE s.id_technicien=? AND p.statut_paiment='payé'");
$rev_total_q->execute([$tech_id]); $rev_total=$rev_total_q->fetchColumn();

$note_q = $pdo->prepare("SELECT COALESCE(AVG(note),0) as avg, COUNT(*) as cnt FROM avis WHERE id_technicien=?");
$note_q->execute([$tech_id]); $note_data=$note_q->fetch();

// Prestations
$prestations = $pdo->prepare("SELECT p.*, COALESCE(pc.nom_ar,'') as nom_ar FROM prestation p LEFT JOIN prestation_catalogue pc ON pc.id_catalogue=p.id_catalogue WHERE p.id_technicien=? ORDER BY p.actif DESC, p.id_prestation ASC");
$prestations->execute([$tech_id]); $prestations=$prestations->fetchAll();

// Catalogue for tech's specialité
$tech_catalogue = [];
if ($tech['specialité'] ?? null) {
    $cq = $pdo->prepare("SELECT id_catalogue, nom, COALESCE(nom_ar,'') as nom_ar FROM prestation_catalogue WHERE specialite=? ORDER BY nom");
    $cq->execute([$tech['specialité']]); $tech_catalogue=$cq->fetchAll();
}
// Names already added by this tech
$existing_names = array_map('strtolower', array_column($prestations,'nom'));

// Disponibilites
$dispos = $pdo->prepare("SELECT * FROM disponibilite WHERE id_technicien=?");
$dispos->execute([$tech_id]); $dispos=$dispos->fetchAll();
$dispo_map = [];
foreach($dispos as $d) $dispo_map[$d['jour']] = $d;

// Avis
$avis_list = $pdo->prepare("
    SELECT a.*, u.nom, u.prenom
    FROM avis a
    JOIN utilisateur u ON a.id_client=(SELECT id_client FROM client WHERE id_user=u.id_user LIMIT 1)
    WHERE a.id_technicien=? ORDER BY a.date_avis DESC LIMIT 20
");
$avis_list->execute([$tech_id]); $avis_list=$avis_list->fetchAll();

// Détail avis par service (prestations effectuées)
$avis_par_service = $pdo->prepare("
    SELECT sv.description as service_nom,
           ROUND(AVG(a.note),2) as note_moy,
           COUNT(*) as nb,
           MIN(a.note) as note_min,
           MAX(a.note) as note_max
    FROM avis a
    JOIN service sv ON sv.id_service=a.id_service
    WHERE a.id_technicien=?
    GROUP BY sv.description
    ORDER BY note_moy DESC
    LIMIT 20
");
$avis_par_service->execute([$tech_id]); $avis_par_service=$avis_par_service->fetchAll();

// Monthly revenue history — all time, grouped by month
$monthly = $pdo->prepare("
    SELECT DATE_FORMAT(p.date_paiment,'%M %Y') as label,
           DATE_FORMAT(p.date_paiment,'%m/%Y') as period,
           YEAR(p.date_paiment) as yr,
           MONTH(p.date_paiment) as mo,
           COALESCE(SUM(p.montant),0) as total,
           COUNT(*) as nb
    FROM paiment p JOIN service s ON s.id_service=p.id_service
    WHERE s.id_technicien=? AND p.statut_paiment='payé'
    GROUP BY YEAR(p.date_paiment), MONTH(p.date_paiment)
    ORDER BY yr DESC, mo DESC
");
$monthly = $pdo->prepare("
  SELECT DATE_FORMAT(p.date_paiment,'%M %Y') as label,
       DATE_FORMAT(p.date_paiment,'%m/%Y') as period,
       YEAR(p.date_paiment) as yr,
       MONTH(p.date_paiment) as mo,
       COALESCE(SUM(p.montant),0) as total,
       COUNT(*) as nb,
      COALESCE((
                 SELECT SUM(c.montant_commission) FROM commission c
                 WHERE c.id_technicien = ?
                   AND YEAR(c.date_commission) = YEAR(p.date_paiment)
                   AND MONTH(c.date_commission) = MONTH(p.date_paiment)
             ),0) AS commission_total,
      COALESCE((
                 SELECT SUM(t.montant_tva) FROM tva t
                 WHERE t.id_technicien = ?
                   AND YEAR(t.date_tva) = YEAR(p.date_paiment)
                   AND MONTH(t.date_tva) = MONTH(p.date_paiment)
             ),0) AS tva_total
  FROM paiment p JOIN service s ON s.id_service=p.id_service
  WHERE s.id_technicien=? AND p.statut_paiment='payé'
  GROUP BY YEAR(p.date_paiment), MONTH(p.date_paiment)
  ORDER BY yr DESC, mo DESC
");
$monthly->execute([$tech_id, $tech_id, $tech_id]); $monthly=$monthly->fetchAll();
// Current month label
$current_month_label = date('F Y');
$current_mo = (int)date('m'); $current_yr = (int)date('Y');

// ── Commission summary for this technician ────────────────────────
$comm_tech_q = $pdo->prepare("
    SELECT
        COALESCE(SUM(montant_commission),0)                                        AS total_comm,
        COALESCE(SUM(CASE WHEN statut='due' THEN montant_commission ELSE 0 END),0) AS comm_due,
        COALESCE(SUM(montant_service),0)                                           AS total_brut,
        COUNT(CASE WHEN statut='due' THEN 1 END)                                   AS nb_due
    FROM commission
    WHERE id_technicien = ?
");
$comm_tech_q->execute([$tech_id]);
$comm_tech = $comm_tech_q->fetch(PDO::FETCH_ASSOC);
// TVA summary for this technician
$tva_tech_q = $pdo->prepare("\n    SELECT\n        COALESCE(SUM(montant_tva),0)                                             AS total_tva,\n        COALESCE(SUM(CASE WHEN statut='due' THEN montant_tva ELSE 0 END),0)     AS tva_due,\n        COUNT(CASE WHEN statut='due' THEN 1 END)                                AS nb_due\n    FROM tva\n    WHERE id_technicien = ?\n");
$tva_tech_q->execute([$tech_id]);
$tva_tech = $tva_tech_q->fetch(PDO::FETCH_ASSOC);
// ─────────────────────────────────────────────────────────────────

// Messages / conversations
$convs = $pdo->prepare("
    SELECT DISTINCT CASE WHEN m.id_expediteur=? THEN m.id_destinataire ELSE m.id_expediteur END as other_id,
           u.nom,u.prenom,u.role,MAX(m.data_envoi) as last_date,
           SUM(CASE WHEN m.lu=0 AND m.id_destinataire=? THEN 1 ELSE 0 END) as unread
    FROM message m
    JOIN utilisateur u ON u.id_user=CASE WHEN m.id_expediteur=? THEN m.id_destinataire ELSE m.id_expediteur END
    WHERE m.id_expediteur=? OR m.id_destinataire=?
    GROUP BY other_id ORDER BY last_date DESC
");
$convs->execute([$user_id,$user_id,$user_id,$user_id,$user_id]); $convs=$convs->fetchAll();
$unread_total = array_sum(array_column($convs,'unread'));

// Selected chat
$active_tab = $_GET['tab'] ?? 'dashboard';
$sel_uid = $_GET['with'] ?? ($convs[0]['other_id'] ?? null);
$chat_msgs=[]; $chat_user=null;
if($active_tab==='messages' && $sel_uid){
    $cu=$pdo->prepare("SELECT * FROM utilisateur WHERE id_user=?");$cu->execute([$sel_uid]);$chat_user=$cu->fetch();
    $cm=$pdo->prepare("SELECT m.*,u.nom,u.prenom FROM message m JOIN utilisateur u ON u.id_user=m.id_expediteur WHERE (m.id_expediteur=? AND m.id_destinataire=?) OR (m.id_expediteur=? AND m.id_destinataire=?) ORDER BY m.data_envoi ASC,m.id_message ASC");
    $cm->execute([$user_id,$sel_uid,$sel_uid,$user_id]); $chat_msgs=$cm->fetchAll();
    $pdo->prepare("UPDATE message SET lu=1 WHERE id_destinataire=? AND id_expediteur=?")->execute([$user_id,$sel_uid]);
}


// Show success toast after password reset
if (isset($_GET['reset']) && $_GET['reset'] == '1') {
    $_SESSION['toast'] = ['msg' => $lang==='ar'?'🔒 تم تحديث كلمة المرور بنجاح !':'🔒 Mot de passe mis à jour avec succès !', 'type' => 'ok'];
}
$toast = $_SESSION['toast'] ?? null; unset($_SESSION['toast']);

// Realtime seed IDs
$_q = $pdo->prepare("SELECT COALESCE(MAX(id_notif),0) FROM notification WHERE id_user=?");
$_q->execute([$user_id]); $seed_notif_id = $_q->fetchColumn();
$_q = $pdo->prepare("SELECT COALESCE(MAX(id_message),0) FROM message WHERE id_destinataire=?");
$_q->execute([$user_id]); $seed_msg_id = $_q->fetchColumn();
$_q = $pdo->prepare("SELECT COALESCE(MAX(id_service),0) FROM service WHERE id_technicien=?"); $_q->execute([$tech_id]); $seed_svc_id = $_q->fetchColumn();

// RTL-safe number display
function money($n,$l){$f=number_format((float)$n,0,'',chr(32));return $l==='ar'?'<span dir="ltr" style="unicode-bidi:embed">'.$f.' دج</span>':$f.' DA';}
function money_plain($n,$l){$f=number_format((float)$n,0,'',chr(32));return $l==='ar'?$f.' دج':$f.' DA';}

// Notifications
$notifs_all_q = $pdo->prepare("SELECT * FROM notification WHERE id_user=? ORDER BY date_notif DESC LIMIT 50");
$notifs_all_q->execute([$user_id]); $notifs_all=$notifs_all_q->fetchAll();
$unread_notif_count = count(array_filter($notifs_all, fn($n)=>!$n['lu']));
if ($active_tab==='notifications') {
    $pdo->prepare("UPDATE notification SET lu=1 WHERE id_user=?")->execute([$user_id]);
    $unread_notif_count = 0;
}

function ini($nom,$prenom){return strtoupper(substr($prenom??'',0,1).substr($nom??'',0,1));}
function stars($n){$n=round($n);$s='';for($i=1;$i<=5;$i++)$s.=$i<=$n?'★':'☆';return $s;}
function unit_label($u,$lang){
  $u = strtolower(trim((string)$u));
  if ($lang === 'ar') {
    $m = ['heure'=>'ساعة','jour'=>'يوم','unité'=>'وحدة','intervention'=>'تدخل'];
    return $m[$u] ?? $u;
  }
  return $u ?: 'unite';
}
function chip($s){
    global $lang;
    $ar = $lang === 'ar';
    $m=[
        'planifié' =>['chip-blue',  $ar?'مجدول' :'Planifié'],
        'en cours' =>['chip-amber', $ar?'جارٍ'  :'En cours'],
        'terminé'  =>['chip-green', $ar?'منتهي' :'Terminé'],
        'annulé'   =>['chip-red',   $ar?'ملغى'  :'Annulé'],
    ];
    [$c,$l]=$m[strtolower($s??'')]??['chip-amber',ucfirst($s)];
    return "<span class=\"chip $c\">$l</span>";
}
$jours_semaine=['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
$titles = $lang === 'ar'
  ? [
    'dashboard' => 'لوحة التحكم',
    'demandes' => 'طلبات الخدمة',
    'prestations' => 'خدماتي',
    'planning' => 'التخطيط والتوفر',
    'messages' => 'الرسائل',
    'revenus' => 'إيراداتي',
    'avis' => 'التقييم والسمعة',
    'notifications' => 'الإشعارات',
    'profil' => 'ملفي الشخصي'
  ]
  : [
    'dashboard' => 'Tableau de bord',
    'demandes' => 'Demandes de service',
    'prestations' => 'Mes prestations',
    'planning' => 'Planning & disponibilités',
    'messages' => 'Messages',
    'revenus' => 'Mes revenus',
    'avis' => 'Avis & Réputation',
    'notifications' => 'Notifications',
    'profil' => 'Mon profil'
  ];
?>
<!DOCTYPE html>
<html lang="<?=$lang?>" dir="<?=$dir?>">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#7c3aed">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Khidma DZ">
<link rel="apple-touch-icon" href="icons/icon-152.png">
<title>Espace Technicien — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{
  --primary:#06b6d4;--primary-dark:#0891b2;--primary-light:rgba(6,182,212,.09);
  --secondary:#1a73e8;--secondary-light:rgba(26,115,232,.1);
  --bg:#f0fdfe;--surface:#fff;--surface2:#ecfeff;--border:#cffafe;
  --text:#1f2933;--muted:#6b7280;
  --green:#16a34a;--green-l:rgba(22,163,74,.1);
  --red:#ef4444;--red-l:rgba(239,68,68,.1);
  --amber:#f59e0b;--amber-l:rgba(245,158,11,.1);
  --blue:#1a73e8;--blue-l:rgba(26,115,232,.1);
  --sidebar-w:250px;--radius:12px;
  --shadow:0 4px 16px rgba(15,23,42,.07);--shadow-lg:0 10px 30px rgba(15,23,42,.12);
}
*{margin:0;padding:0;box-sizing:border-box;}
html,body{height:100%;overflow:hidden;}body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;width:100%;}
[dir=rtl] body,[dir=rtl] button,[dir=rtl] input,[dir=rtl] textarea,[dir=rtl] select{font-family:'Cairo',sans-serif;}
[dir=rtl] .logo-text,[dir=rtl] .page-title,[dir=rtl] .card-title,[dir=rtl] .sec-title,[dir=rtl] .stat-val,[dir=rtl] .prof-name,[dir=rtl] .menu-item-name,[dir=rtl] .av-name{font-family:'Cairo',sans-serif;}

/* ── Sidebar ── */
.sidebar{width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;bottom:0;z-index:50;box-shadow:2px 0 12px rgba(15,23,42,.04);}
[dir=rtl] .sidebar{border-right:none;border-left:1px solid var(--border);left:auto;right:0;}
.logo{padding:22px 20px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);}
.logo-icon{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:13px;color:#fff;flex-shrink:0;}
.logo-text{font-family:'Syne',sans-serif;font-weight:700;font-size:16px;}.logo-text span{color:var(--primary);}
.nav{flex:1;padding:14px 10px;display:flex;flex-direction:column;gap:2px;overflow-y:auto;}
.nav-sec{font-size:10px;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;padding:10px 10px 4px;}
.nav-item{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:10px;cursor:pointer;transition:all .18s;color:var(--muted);font-size:13.5px;font-weight:500;text-decoration:none;position:relative;}
.nav-item:hover{background:var(--surface2);color:var(--text);}
.nav-item.active{background:var(--primary-light);color:var(--primary);font-weight:600;}
.nav-item.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:18px;background:var(--primary);border-radius:0 3px 3px 0;}
[dir=rtl] .nav-item.active::before{left:auto;right:0;border-radius:3px 0 0 3px;}
.nav-item svg{width:17px;height:17px;flex-shrink:0;}
.badge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;line-height:1.4;}
[dir=rtl] .badge{margin-left:0;margin-right:auto;}
.sidebar-foot{padding:14px 10px;border-top:1px solid var(--border);}
.tech-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;cursor:pointer;transition:background .18s;text-decoration:none;color:inherit;}
.tech-chip:hover{background:var(--surface2);}
.av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:13px;color:#fff;flex-shrink:0;}
.av-name{font-size:13px;font-weight:600;}
.av-role{font-size:11px;color:var(--primary);font-weight:500;}

/* ── Main ── */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;height:100vh;overflow:hidden;}
[dir=rtl] .main{margin-left:0;margin-right:var(--sidebar-w);}
.topbar{padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);background:var(--surface);position:sticky;top:0;z-index:40;}
.page-title{font-family:'Syne',sans-serif;font-weight:700;font-size:19px;}
.topbar-r{display:flex;align-items:center;gap:10px;}
[dir=rtl] .topbar-r{flex-direction:row-reverse;}
.icon-btn{width:36px;height:36px;border-radius:10px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;}
.notif-dot{position:absolute;top:7px;right:7px;width:7px;height:7px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface);}
[dir=rtl] .notif-dot{right:auto;left:7px;}
.content{flex:1;padding:26px 28px;overflow-y:auto;min-height:0;}.content.msgs{padding:0;overflow:hidden;display:flex;flex-direction:column;}

/* ── Stats ── */
.stats-g{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;position:relative;box-shadow:var(--shadow);}
.stat-lbl{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;}
.stat-val{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;line-height:1;}
.stat-sub{font-size:11px;color:var(--muted);margin-top:5px;}
.stat-sub span{color:var(--green);font-weight:600;}
.stat-ico{position:absolute;right:14px;top:14px;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;}
[dir=rtl] .stat-ico{right:auto;left:14px;}

/* ── Cards ── */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
.card-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--surface2);}
.card-title{font-family:'Syne',sans-serif;font-weight:700;font-size:14px;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;}
.sec-title{font-family:'Syne',sans-serif;font-weight:700;font-size:15px;margin-bottom:12px;}

/* ── Buttons ── */
.btn{padding:7px 14px;border-radius:999px;font-size:12.5px;font-weight:600;cursor:pointer;border:none;transition:all .18s;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
[dir=rtl] .btn{font-family:'Cairo',sans-serif;}
[dir=rtl] .btn{flex-direction:row-reverse;}
.btn .btn-ico{line-height:1;display:inline-block;}
[dir=rtl] .btn .btn-ico{order:2;}
.btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(6,182,212,.25);}
.btn-primary:hover{background:var(--primary-dark);transform:translateY(-1px);}
.btn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border);}
.btn-ghost:hover{background:var(--border);}
.btn-g{background:var(--green-l);color:var(--green);border:1px solid rgba(22,163,74,.25);}
.btn-g:hover{background:rgba(22,163,74,.2);}
.btn-r{background:var(--red-l);color:var(--red);border:1px solid rgba(239,68,68,.25);}
.btn-r:hover{background:rgba(239,68,68,.2);}
.btn-sm{padding:5px 11px;font-size:12px;}

/* ── Table ── */
table{width:100%;border-collapse:collapse;}
thead tr{border-bottom:1px solid var(--border);background:var(--surface2);}
thead th{padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;}
[dir=rtl] thead th{text-align:right;}
tbody tr{border-bottom:1px solid var(--border);transition:background .12s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:var(--surface2);}
td{padding:11px 14px;font-size:13.5px;}
[dir=rtl] td{text-align:right;}
.uc{display:flex;align-items:center;gap:9px;}
.mav{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;border:1px solid var(--border);background:var(--surface2);}
.fa{display:flex;gap:5px;flex-wrap:wrap;}

/* ── Chips ── */
.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;}
.chip::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
.chip-green{background:var(--green-l);color:var(--green);}
.chip-amber{background:var(--amber-l);color:var(--amber);}
.chip-red{background:var(--red-l);color:var(--red);}
.chip-blue{background:var(--blue-l);color:var(--blue);}

/* ── Prestations menu ── */
.menu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-bottom:20px;}
.menu-item{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);position:relative;transition:border .2s;}
.menu-item:hover{border-color:var(--primary);}
.menu-item.inactive{opacity:.55;}
.menu-item-name{font-family:'Syne',sans-serif;font-weight:700;font-size:15px;margin-bottom:5px;}
.menu-item-desc{font-size:13px;color:var(--muted);line-height:1.5;margin-bottom:10px;}
.menu-item-price{font-size:14px;font-weight:700;color:var(--primary);}
.menu-item-unit{font-size:12px;color:var(--muted);margin-left:4px;}
.menu-item-actions{position:absolute;top:12px;right:12px;display:flex;gap:5px;}
[dir=rtl] .menu-item-actions{right:auto;left:12px;flex-direction:row-reverse;}
.add-prestation-form{background:var(--surface);border:2px dashed var(--border);border-radius:var(--radius);padding:22px;margin-bottom:20px;}
.add-prestation-form:hover{border-color:var(--primary);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group.full{grid-column:1/-1;}
.flbl{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;}
.finput{background:var(--surface2);border:1px solid var(--border);border-radius:9px;padding:9px 12px;color:var(--text);font-size:13.5px;font-family:'DM Sans',sans-serif;outline:none;transition:border .18s;width:100%;}
[dir=rtl] .finput{font-family:'Cairo',sans-serif;text-align:right;}
.finput:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(6,182,212,.12);}
textarea.finput{resize:vertical;min-height:70px;}

/* ── Planning ── */
.planning-grid{display:flex;flex-direction:column;gap:10px;}
.planning-row{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow);}
.planning-day{font-weight:700;font-size:14px;width:90px;flex-shrink:0;}
.planning-toggle{position:relative;width:42px;height:24px;flex-shrink:0;}
.planning-toggle input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background:var(--border);border-radius:20px;transition:.3s;}
.toggle-slider::before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.3s;}
.planning-toggle input:checked + .toggle-slider{background:var(--primary);}
.planning-toggle input:checked + .toggle-slider::before{transform:translateX(18px);}
.time-inputs{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);}
.time-inputs input{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:6px 10px;font-size:13px;color:var(--text);outline:none;width:90px;}
.time-inputs input:focus{border-color:var(--primary);}

/* ── Messages ── */
.msg-layout{display:flex;flex:1;min-height:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
.msg-list{width:260px;flex-shrink:0;border-right:1px solid var(--border);overflow-y:auto;display:flex;flex-direction:column;}
[dir=rtl] .msg-list{border-right:none;border-left:1px solid var(--border);}
.msg-search{padding:12px 14px;border-bottom:1px solid var(--border);}
.msg-search input{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:7px 11px;color:var(--text);font-size:13px;font-family:'DM Sans',sans-serif;outline:none;}
[dir=rtl] .msg-search input{font-family:'Cairo',sans-serif;text-align:right;}
.msg-search input:focus{border-color:var(--primary);}
.msg-item{padding:12px 14px;border-bottom:1px solid var(--border);cursor:pointer;display:flex;gap:9px;align-items:flex-start;text-decoration:none;color:inherit;transition:background .12s;}
.msg-item:hover{background:var(--surface2);}
.msg-item.active{background:var(--primary-light);border-left:3px solid var(--primary);}
[dir=rtl] .msg-item.active{border-left:none;border-right:3px solid var(--primary);}
.msg-sender{font-size:13px;font-weight:600;}
.msg-time{font-size:11px;color:var(--muted);}
.msg-preview{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.msg-unread{width:8px;height:8px;background:var(--primary);border-radius:50%;margin-top:4px;flex-shrink:0;}
.chat-area{flex:1;display:flex;flex-direction:column;background:var(--bg);overflow:hidden;min-width:0;}
.chat-hdr{padding:14px 18px;border-bottom:1px solid var(--border);background:var(--surface);display:flex;align-items:center;gap:10px;}
.chat-msgs{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;gap:14px;min-height:0;}
.mbw{display:flex;gap:10px;align-items:flex-end;}
.mbw.me{flex-direction:row-reverse;}
.bubble-inner{display:flex;flex-direction:column;min-width:0;max-width:68%;}
.mbw.me .bubble-inner{align-items:flex-end;}
.bubble{padding:11px 15px;font-size:13.5px;line-height:1.55;word-break:break-word;overflow-wrap:break-word;white-space:pre-wrap;}
.bubble.other{background:#fff;border:1px solid var(--border);border-radius:18px 18px 18px 4px;color:var(--text);box-shadow:0 1px 4px rgba(0,0,0,.06);}
.bubble.me{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;border-radius:18px 18px 4px 18px;box-shadow:0 3px 10px rgba(6,182,212,.28);}
.bt{font-size:10.5px;color:var(--muted);margin-top:4px;padding:0 4px;}
.mbw.me .bt{text-align:right;color:rgba(6,182,212,.7);}
.bt{font-size:10px;color:var(--muted);margin-top:2px;}
.chat-inp{padding:12px 16px;border-top:1px solid var(--border);background:var(--surface);display:flex;align-items:flex-end;gap:8px;flex-shrink:0;}
.chat-inp form{display:contents;}
.chat-inp textarea{flex:1;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:9px 14px;color:var(--text);font-size:13.5px;font-family:'DM Sans',sans-serif;outline:none;resize:none;min-height:44px;max-height:120px;overflow-y:auto;line-height:1.5;}
[dir=rtl] .chat-inp textarea{font-family:'Cairo',sans-serif;text-align:right;}
.chat-inp textarea:focus{border-color:var(--primary);}
.send-btn{padding:9px 16px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:13px;align-self:flex-end;flex-shrink:0;}
.send-btn:hover{background:var(--primary-dark);}

/* ── Revenus chart ── */
.chart-bars{background:var(--surface2);border-radius:10px;height:130px;display:flex;align-items:flex-end;padding:12px 12px 0;gap:7px;}
.bar{border-radius:6px 6px 0 0;background:var(--primary);opacity:.6;flex:1;transition:opacity .2s;cursor:pointer;}
.bar:hover{opacity:1;}
.rev-row{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px solid var(--border);font-size:13.5px;}
.rev-row:last-child{border-bottom:none;}

/* ── Profile ── */
.prof-hero{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:28px;display:flex;gap:22px;align-items:flex-start;margin-bottom:18px;box-shadow:var(--shadow);direction:ltr;text-align:left;}
.prof-main{display:flex;flex-direction:column;align-items:flex-start;min-width:0;}
.prof-av{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:#fff;border:3px solid var(--primary);flex-shrink:0;}
.prof-name{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;margin-bottom:2px;}
.prof-spec{color:var(--primary);font-size:13px;font-weight:600;margin-bottom:10px;}
.prof-stats{display:flex;gap:18px;flex-wrap:wrap;}
.prof-stat{font-size:12px;color:var(--muted);}
.prof-stat strong{color:var(--text);}

/* ── Avis ── */
.avis-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:10px;box-shadow:var(--shadow);}
.stars-big{color:var(--amber);font-size:13px;letter-spacing:2px;}
.stars-sm{color:var(--amber);font-size:12px;}

/* ── Empty ── */
.empty{text-align:center;padding:50px 0;color:var(--muted);}
.empty .ico{font-size:38px;margin-bottom:10px;}
.empty p{font-size:15px;font-weight:600;}

/* ── Toast ── */
.toast-cont{position:fixed;bottom:20px;right:20px;display:flex;flex-direction:column;gap:8px;z-index:200;}
[dir=rtl] .toast-cont{right:auto;left:20px;}
.toast{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:11px 16px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:9px;box-shadow:var(--shadow-lg);animation:tIn .25s ease;min-width:220px;}
@keyframes tIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
[dir=rtl] @keyframes tIn{from{opacity:0;transform:translateX(-16px)}to{opacity:1;transform:translateX(0)}}
.toast.success{border-left:3px solid var(--green);}
.toast.error{border-left:3px solid var(--red);}
[dir=rtl] .toast.success{border-left:none;border-right:3px solid var(--green);}
[dir=rtl] .toast.error{border-left:none;border-right:3px solid var(--red);}
/* Additional RTL fixes */
[dir=rtl] .stat{text-align:right;padding-left:54px;padding-right:20px;}
[dir=rtl] .stat-ico{right:auto;left:14px;}
[dir=rtl] .user-chip{flex-direction:row-reverse;}
[dir=rtl] .uc-info{text-align:right;}
[dir=rtl] .av-title{text-align:right;}
[dir=rtl] .av-block{flex-direction:row-reverse;}
[dir=rtl] .notif-item{flex-direction:row-reverse;}
[dir=rtl] .msg-item{flex-direction:row-reverse;}
[dir=rtl] .msg-item.active{border-left:none;border-right:3px solid var(--primary);}
[dir=rtl] .msg-hdr{flex-direction:row-reverse;}
[dir=rtl] .fa{flex-direction:row-reverse;}
[dir=rtl] .uc{flex-direction:row;}
[dir=rtl] .menu-item{flex-direction:row-reverse;text-align:right;}
[dir=rtl] .menu-item-price{text-align:left;}
[dir=rtl] .planning-row{flex-direction:row-reverse;}

/* ── Status banner ── */
.status-banner{background:var(--amber-l);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px;}
.status-banner.verified{background:var(--green-l);border-color:rgba(22,163,74,.3);}

::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-track{background:transparent;}::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}

/* RTL desktop sidebar position */
[dir=rtl] .sidebar{left:auto;right:0;}


/* ════════════════════════════════
   MOBILE ≤ 768px — TECH
════════════════════════════════ */
@media (max-width: 768px) {

  html, body { height: auto !important; overflow: auto !important; width: 100% !important; }
  body { display: block !important; }

  .sidebar {
    position: fixed !important;
    top: 0; left: 0; bottom: 0;
    width: 260px !important; height: 100vh !important;
    transform: translateX(-110%);
    transition: transform .3s cubic-bezier(.4,0,.2,1);
    z-index: 300; overflow-y: auto;
  }
  .sidebar.open { transform: translateX(0); }

  /* RTL mobile sidebar slides from right */
  [dir=rtl] .sidebar {
    left: auto;
    right: 0;
    transform: translateX(110%);
  }
  [dir=rtl] .sidebar.open { transform: translateX(0); }

  .sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.5); z-index: 299;
  }
  .sidebar-overlay.open { display: block; }

  .main {
    width: 100% !important; min-width: 0 !important;
    height: auto !important; overflow: visible !important;
    display: flex; flex-direction: column; min-height: 100vh;
    margin-left: 0 !important;
  }

  .topbar {
    padding: 10px 12px !important;
    position: sticky; top: 0; z-index: 100;
    overflow: hidden;
  }
  .page-title {
    font-size: 14px !important;
    white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; max-width: 130px;
  }
  .topbar-right { gap: 6px !important; }
  .icon-btn { width: 32px !important; height: 32px !important; }
  .avatar { width: 32px !important; height: 32px !important; font-size: 11px !important; }
  .lang-btn { display: none !important; }
  .mob-hamburger { display: flex !important; }

  .content {
    padding: 14px 12px 90px !important;
    overflow-y: auto !important; flex: 1;
    width: 100%; min-width: 0;
  }
  .content.msgs {
    padding: 0 !important; overflow: hidden !important;
    height: calc(100vh - 56px);
  }

  /* Cards */
  .card { border-radius: 12px; }
  .sec-title { font-size: 14px; }

  /* Search form stacks */
  .search-form-row { flex-direction: column !important; }

  /* Messages */
  .msg-layout { grid-template-columns: 1fr !important; height: calc(100vh - 110px); }
  .msg-list { display: none !important; }
  .chat-msgs { height: calc(100vh - 175px) !important; }

  /* Bottom tab bar */
  .mob-nav {
    display: flex !important;
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--surface);
    border-top: 1.5px solid var(--border);
    z-index: 200;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    box-shadow: 0 -4px 20px rgba(0,0,0,.06);
  }
  .mob-tab {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 7px 2px 5px; text-decoration: none;
    color: var(--muted); font-size: 9px; font-weight: 600;
    gap: 3px; position: relative;
  }
  .mob-tab svg { width: 20px; height: 20px; flex-shrink: 0; }
  .mob-tab.active { color: var(--primary); }
  .mob-badge {
    position: absolute; top: 4px; right: calc(50% - 20px);
    background: var(--red); color: #fff;
    font-size: 9px; font-weight: 700;
    padding: 1px 4px; border-radius: 20px;
    min-width: 15px; text-align: center;
  }

  input, select, textarea { font-size: 16px !important; }
  /* Chat input bar — always row layout */
  .chat-inp { padding: 10px 12px !important; gap: 8px !important; }
  .chat-inp form { display: contents !important; }
  .chat-inp textarea {
    min-height: 40px !important;
    max-height: 100px !important;
    padding: 9px 12px !important;
    font-size: 14px !important;
    border-radius: 10px !important;
  }
  .send-btn {
    padding: 9px 14px !important;
    font-size: 13px !important;
    border-radius: 10px !important;
    height: 40px !important;
    align-self: flex-end !important;
    flex-shrink: 0 !important;
    white-space: nowrap;
  }
}

@media (min-width: 769px) {
  .mob-nav { display: none !important; }
  .mob-hamburger { display: none !important; }
  .sidebar-overlay { display: none !important; }
}

</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="logo">
    <div class="logo-icon">KDZ</div>
    <div class="logo-text">Khidma <span>DZ</span></div>
  </div>
  <nav class="nav">
    <div class="nav-sec"><?=$lang==='ar'?'القائمة':'Menu'?></div>
    <a class="nav-item <?= $active_tab==='dashboard'?'active':'' ?>" href="?tab=dashboard">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      <?=$lang==='ar'?'لوحة التحكم':'Tableau de bord'?>
    </a>
    <a class="nav-item <?= $active_tab==='demandes'?'active':'' ?>" href="?tab=demandes">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      <?=$lang==='ar'?'الطلبات':'Demandes'?>
      <?php if($new_count>0):?><span class="badge nav-badge-demandes"><?=$new_count?></span><?php endif;?>
    </a>
    <a class="nav-item <?= $active_tab==='prestations'?'active':'' ?>" href="?tab=prestations">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <?=$lang==='ar'?'خدماتي':'Mes prestations'?>
    </a>
    <a class="nav-item <?= $active_tab==='planning'?'active':'' ?>" href="?tab=planning">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <?=$lang==='ar'?'التخطيط':'Planning'?>
    </a>
    <a class="nav-item <?= $active_tab==='messages'?'active':'' ?>" href="?tab=messages">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <?=$lang==='ar'?'الرسائل':'Messages'?>
      <?php if($unread_total>0):?><span class="badge nav-badge-msg"><?=$unread_total?></span><?php endif;?>
    </a>
    <a class="nav-item <?= $active_tab==='revenus'?'active':'' ?>" href="?tab=revenus">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      <?=$lang==='ar'?'الإيرادات':'Revenus'?>
    </a>
    <a class="nav-item <?= $active_tab==='avis'?'active':'' ?>" href="?tab=avis">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      <?=$lang==='ar'?'التقييم والسمعة':'Avis & Réputation'?>
    </a>
    <a class="nav-item <?= $active_tab==='notifications'?'active':'' ?>" href="?tab=notifications">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?=$lang==='ar'?'الإشعارات':'Notifications'?>
      <?php if($unread_notif_count>0):?><span class="badge nav-badge-notif"><?=$unread_notif_count?></span><?php endif;?>
    </a>
    <div class="nav-sec"><?=$lang==='ar'?'الحساب':'Compte'?></div>
    <a class="nav-item <?= $active_tab==='profil'?'active':'' ?>" href="?tab=profil">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <?=$lang==='ar'?'ملفي الشخصي':'Mon profil'?>
    </a>
    <a class="nav-item" href="index.php">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <?=$lang==='ar'?'الموقع':'Voir le site'?>
    </a>
    <a class="nav-item" href="actions/handle_logout.php">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <?=$lang==='ar'?'خروج':'Déconnexion'?>
    </a>
  </nav>
  <div class="sidebar-foot">
    <a class="tech-chip" href="?tab=profil">
      <div class="av"><?=ini($tech['nom'],$tech['prenom'])?></div>
      <div>
        <div class="av-name"><?=htmlspecialchars($tech['prenom'].' '.$tech['nom'])?></div>
        <div class="av-role"><?=htmlspecialchars(svc_label($tech['specialité']??'',$lang)?:($lang==='ar'?'تقني':'Technicien'))?></div>
      </div>
    </a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <button class="mob-hamburger" onclick="openSidebar()" style="display:none;background:none;border:none;cursor:pointer;padding:4px;color:var(--text);margin-right:8px;flex-shrink:0"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><?=$titles[$active_tab]??'Tableau de bord'?></div>
    <div class="topbar-r">
      <!-- Geolocation toggle -->
      <?php $geo_active = $tech['localisation_active']??0; ?>
      <button id="geo-btn" onclick="toggleGeo()" title="<?=$geo_active?'Désactiver':'Activer'?> la localisation"
        style="background:<?=$geo_active?'var(--green)':'var(--bg)'?>;color:<?=$geo_active?'#fff':'var(--muted)'?>;border:1px solid <?=$geo_active?'var(--green)':'var(--border)'?>;padding:6px 12px;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;transition:all .2s">
        📍 <?=$geo_active?'En ligne':'Localisation'?>
      </button>
      <a href="?lang=<?=t('lang_target',$lang)?>&tab=<?=$active_tab?>" style="padding:6px 14px;background:var(--bg);border:1px solid var(--border);border-radius:9px;font-size:12px;font-weight:700;color:var(--green);text-decoration:none;transition:all .2s"><?=t('lang_label',$lang)?></a>
      <a href="?tab=notifications" class="icon-btn" style="text-decoration:none;color:inherit">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if($unread_notif_count>0):?><div class="notif-dot"></div><?php endif;?>
      </a>
      <a href="?tab=profil" class="av" style="text-decoration:none"><?=ini($tech['nom'],$tech['prenom'])?></a>
    </div>
  </div>

  <div class="content <?php if($active_tab==='messages') echo 'msgs'; ?>">

  <?php if(!$tech['verifie']):?>
  <div class="status-banner">
    ⏳ <strong><?=$lang==='ar'?'ملفك الشخصي في انتظار التحقق':'Votre profil est en attente de validation'?></strong> — <?=$lang==='ar'?'سيتحقق المسؤول من وثائقك. ستتمكن من قبول الطلبات بعد الموافقة.':'Un administrateur va vérifier vos documents. Vous pourrez accepter des demandes une fois approuvé.'?>
  </div>
  <?php else:?>
  <div class="status-banner verified">
    ✅ <strong><?=$lang==='ar'?'ملف موثق':'Profil vérifié'?></strong> — <?=$lang==='ar'?'حسابك نشط ومرئي للعملاء.':'Votre compte est actif et visible par les clients.'?>
  </div>
  <?php endif;?>

  <?php if($active_tab==='dashboard'): ?>
  <!-- ══ DASHBOARD ══ -->
  <div style="margin-bottom:20px">
    <div style="font-family:'Syne',sans-serif;font-size:22px;font-weight:800;margin-bottom:3px"><?=$lang==='ar'?'مرحباً':'Bonjour'?>, <?=htmlspecialchars($tech['prenom'])?> 👷</div>
    <div style="color:var(--muted);font-size:13px"><?=$lang==='ar'?'إليك ملخص نشاطك على Khidma DZ':'Voici un aperçu de votre activité sur Khidma DZ'?></div>
  </div>
  <div class="stats-g">
    <div class="stat" style="<?=$lang==='ar'?'padding-left:54px;padding-right:20px;text-align:right':''?>"><div class="stat-ico" style="background:var(--amber-l)">📋</div><div class="stat-lbl"><?=$lang==='ar'?'التدخلات':'Interventions'?></div><div class="stat-val"><?=$total_done?></div><div class="stat-sub" style="color:var(--muted)"><?=$lang==='ar'?'إجمالي المنتهية':'Total terminées'?></div></div>
    <div class="stat" style="<?=$lang==='ar'?'padding-left:54px;padding-right:20px;text-align:right':''?>"><div class="stat-ico" style="background:var(--green-l)">💰</div><div class="stat-lbl"><?=$lang==='ar'?'الإيرادات (الشهر)':'Revenus (mois)'?></div><div class="stat-val" style="font-size:<?=$rev_mois>99999?'18px':'22px'?>"><?=money($rev_mois,$lang)?></div><div class="stat-sub"><span><?=$lang==='ar'?'هذا الشهر':'Ce mois'?></span></div></div>
    <div class="stat" style="<?=$lang==='ar'?'padding-left:54px;padding-right:20px;text-align:right':''?>"><div class="stat-ico" style="background:var(--amber-l)">⭐</div><div class="stat-lbl"><?=$lang==='ar'?'متوسط التقييم':'Note moyenne'?></div><div class="stat-val"><?=number_format($note_data['avg'],1)?></div><div class="stat-sub"><?=$lang==='ar'?'من '.$note_data['cnt'].' تقييم':'Sur '.$note_data['cnt'].' avis'?></div></div>
    <div class="stat" style="<?=$lang==='ar'?'padding-left:54px;padding-right:20px;text-align:right':''?>"><div class="stat-ico" style="background:var(--blue-l)">📥</div><div class="stat-lbl"><?=$lang==='ar'?'طلبات جديدة':'Nouvelles demandes'?></div><div class="stat-val"><?=$new_count?></div><div class="stat-sub" style="color:<?=$new_count>0?'var(--amber)':'var(--muted)'?>"><?=$new_count>0?($lang==='ar'?'في انتظار الرد':'À répondre'):($lang==='ar'?'لا طلبات معلقة':'Aucune en attente')?></div></div>
  </div>

  <div class="two-col">
    <div>
      <div class="sec-title"><?=$lang==='ar'?'آخر الطلبات':'Dernières demandes'?></div>
      <div class="card">
        <?php if(empty($demandes)):?>
        <div class="empty"><div class="ico">📭</div><p><?=$lang==='ar'?'لا توجد طلبات':'Aucune demande reçue'?></p></div>
        <?php else: foreach(array_slice($demandes,0,4) as $d):?>
        <div style="padding:14px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center">
          <div class="mav" style="background:var(--blue-l);color:var(--blue)"><?=ini($d['cn'],$d['cp'])?></div>
          <div style="flex:1">
            <div style="font-size:13px;font-weight:600"><?=htmlspecialchars($d['cp'].' '.$d['cn'])?></div>
            <div style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($d['description']??svc_label($tech['specialité']??'',$lang)??($lang==='ar'?'خدمة':'Service'))?> · <?=htmlspecialchars($d['cv']??'—')?></div>
          </div>
          <?=chip($d['statut_service'])?>
          <?php if($d['statut_service']==='planifié'):?>
          <div class="fa" style="<?=$lang==='ar'?'flex-direction:row-reverse':'' ?>">
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="accept_service"><input type="hidden" name="id_service" value="<?=$d['id_service']?>"><input type="hidden" name="tab" value="dashboard"><button class="btn btn-g btn-sm">✓</button></form>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="refuse_service"><input type="hidden" name="id_service" value="<?=$d['id_service']?>"><input type="hidden" name="tab" value="dashboard"><button class="btn btn-r btn-sm">✗</button></form>
          </div>
          <?php endif;?>
        </div>
        <?php endforeach; endif;?>
      </div>
    </div>
    <div>
      <div class="sec-title"><?=$lang==='ar'?'الإيرادات — آخر 8 أشهر':'Revenus — 8 derniers mois'?></div>
      <div class="card" style="padding:16px">
        <?php if(!empty($monthly)):$max=max(array_column($monthly,'total'))?:1;?>
        <div class="chart-bars">
          <?php foreach($monthly as $m):?><div class="bar" style="height:<?=max(8,round($m['total']/$max*100))?>%" title="<?=htmlspecialchars($m['label'].': '.number_format($m['total'],0).($lang==='ar'?' دج':' DA'))?>"></div><?php endforeach;?>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:10px;color:var(--muted);padding:0 2px">
          <?php foreach($monthly as $m):?><span><?=htmlspecialchars($m['period'])?></span><?php endforeach;?>
        </div>
        <?php else:?><div class="empty" style="padding:30px 0"><p><?=$lang==='ar'?'لا توجد بيانات بعد':'Pas encore de données'?></p></div><?php endif;?>
      </div>
    </div>
  </div>

  <?php elseif($active_tab==='demandes'): ?>
  <!-- ══ DEMANDES ══ -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div class="sec-title"><?=$lang==='ar'?'طلبات الخدمة ('.count($demandes).')':'Demandes de service ('.count($demandes).')'?></div>
    <?php if($new_count>0):?><span style="background:var(--primary-light);color:var(--primary);border:1.5px solid var(--border);border-radius:20px;padding:4px 14px;font-size:12px;font-weight:700"><?=$new_count?> <?=$lang==='ar'?'جديد':'nouvelle(s)'?></span><?php endif;?>
  </div>
  <?php if(empty($demandes)):?>
  <div class="card"><div class="empty"><div class="ico">📭</div><p><?=$lang==='ar'?'لا توجد طلبات':'Aucune demande reçue'?></p></div></div>
  <?php else:?>
  <div style="display:flex;flex-direction:column;gap:14px">
  <?php foreach($demandes as $d):
    $prests = $svc_prestations[(int)$d['id_service']] ?? [];
    $client_phone_href = preg_replace('/[^0-9+]/', '', (string)($d['ct'] ?? ''));
    $is_new = $d['statut_service']==='planifié';
    $is_active = $d['statut_service']==='en cours';
    $border_color = $is_new ? 'var(--primary)' : ($is_active ? '#f59e0b' : 'var(--border)');
  ?>
  <div style="background:#fff;border:1.5px solid <?=$border_color?>;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.05)">

    <!-- Card header: client info + status + date -->
    <div style="padding:16px 20px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--border);background:<?=$is_new?'var(--surface2)':'#fff'?>">
      <div class="mav" style="width:44px;height:44px;font-size:15px;background:var(--blue-l);color:var(--blue);flex-shrink:0"><?=ini($d['cn'],$d['cp'])?></div>
      <div style="flex:1;min-width:0">
        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:16px;color:var(--text)"><?=htmlspecialchars($d['cp'].' '.$d['cn'])?></div>
        <div style="display:flex;gap:12px;margin-top:3px;flex-wrap:wrap">
          <?php if($d['ct']):?><span style="font-size:12px;color:var(--muted)">📞 <?=htmlspecialchars($d['ct'])?></span><?php endif;?>
          <?php if($d['cv']):?><span style="font-size:12px;color:var(--muted)">📍 <?=htmlspecialchars($d['cv'])?></span><?php endif;?>
          <span style="font-size:12px;color:var(--muted)">📅 <?=$d['date_service']?date('d/m/Y',strtotime($d['date_service'])):'—'?></span>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0">
        <?=chip($d['statut_service'])?>
        <?php if($d['prix_estime']):?><span style="font-size:13px;font-weight:700;color:var(--green)"><?=money($d['prix_estime'],$lang)?> <?=$lang==='ar'?'تقديري':'estimé'?></span><?php endif;?>
      </div>
    </div>

    <!-- Prestations -->
    <?php
    $prest_total = array_sum(array_map(fn($pr)=>($pr['prix_unitaire']??0)*($pr['quantite']??1), $prests));
    ?>
    <div style="padding:12px 20px;border-bottom:1px solid var(--border);background:rgba(6,182,212,.03)">
      <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">
        🔧 <?=$lang==='ar'?'الخدمات':'Prestations'?><?php if($prest_total>0):?> — <span style="color:var(--primary)"><?=money($prest_total,$lang)?></span><?php endif;?>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php if(empty($prests)):?>
        <span style="font-size:12px;color:var(--muted);font-style:italic"><?=$lang==='ar'?'لا توجد خدمات مختارة':'Aucune prestation sélectionnée'?></span>
        <?php else: foreach($prests as $pr):?>
        <span style="background:var(--primary-light);color:var(--primary);border:1.5px solid rgba(6,182,212,.3);border-radius:8px;padding:4px 12px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:5px">
          <?=htmlspecialchars($lang==='ar'&&!empty($pr['nom_ar'])?$pr['nom_ar']:$pr['nom'])?>
          <?php if($pr['prix_unitaire']??null):?><span style="opacity:.65;font-weight:400;font-size:11px">· <span dir="ltr" style="unicode-bidi:embed"><?=number_format($pr['prix_unitaire'],0)?></span> <?=$lang==='ar'?'دج':'DA'?>/<?=htmlspecialchars(unit_label($pr['unite']??'u',$lang))?><?php if(($pr['quantite']??1)>1):?> × <?=$pr['quantite']?> = <strong><span dir="ltr" style="unicode-bidi:embed"><?=number_format(($pr['prix_unitaire'])*($pr['quantite']??1),0)?></span> <?=$lang==='ar'?'دج':'DA'?></strong><?php endif;?></span><?php endif;?>
        </span>
        <?php endforeach; endif;?>
      </div>
    </div>

    <!-- Confirm & complete panel (en cours only, hidden until Marquer terminé clicked) -->
    <?php if(in_array($d['statut_service'], ['en cours','terminé'])):?>
    <div id="complete-panel-<?=$d['id_service']?>" style="display:none;border-bottom:1px solid var(--border);background:#f0fdf4">
      <div style="padding:12px 20px 8px;font-size:12px;font-weight:700;color:#15803d">
        ✅ <?=$d['statut_service']==='terminé' ? ($lang==='ar'?'عدّل الخدمات ثم احفظ':'Modifiez les prestations puis enregistrez') : ($lang==='ar'?'راجع الخدمات المنجزة — عدّل إن لزم ثم أكّد':'Vérifiez les prestations effectuées — modifiez si nécessaire puis confirmez')?>
      </div>
      <?php
      $all_my_prests = $pdo->prepare("SELECT p.*, COALESCE(pc.nom_ar,'') as nom_ar FROM prestation p LEFT JOIN prestation_catalogue pc ON pc.id_catalogue=p.id_catalogue WHERE p.id_technicien=? AND p.actif=1 ORDER BY p.nom");
      $all_my_prests->execute([$tech_id]); $all_my_prests=$all_my_prests->fetchAll();
      $checked_pids = array_column($prests,'id_prestation');
      $checked_qtys = array_column($prests,'quantite','id_prestation');
      ?>
      <form method="POST" style="padding:0 20px 16px">
        <input type="hidden" name="action" value="<?=$d['statut_service']==='terminé'?'update_service_prestations':'complete_service'?>">
        <input type="hidden" name="tab" value="demandes">
        <input type="hidden" name="id_service" value="<?=$d['id_service']?>">
        <div style="display:flex;flex-direction:column;gap:5px;margin-bottom:12px">
          <?php foreach($all_my_prests as $mp):
            $is_chk = in_array($mp['id_prestation'],$checked_pids);
            $qty = $checked_qtys[$mp['id_prestation']] ?? 1;
            $is_locked = $d['statut_service']==='terminé' && $is_chk;
            $min_qty = $is_locked ? $qty : 1;
          ?>
          <label style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;border:1.5px solid <?=$is_chk?'rgba(6,182,212,.5)':'var(--border)'?>;background:<?=$is_chk?'rgba(6,182,212,.06)':'#fff'?>;cursor:<?=$is_locked?'not-allowed':'pointer'?>;opacity:<?=$is_locked?'1':'1'?>">
            <?php if($is_locked): ?>
              <input type="checkbox" checked disabled style="accent-color:var(--primary);width:16px;height:16px;cursor:not-allowed">
              <input type="hidden" name="prestations[]" value="<?=$mp['id_prestation']?>">
            <?php else: ?>
              <input type="checkbox" name="prestations[]" value="<?=$mp['id_prestation']?>"
                <?=$is_chk?'checked':''?>
                onchange="techToggleQty(this,<?=$d['id_service']?>,<?=$mp['id_prestation']?>)"
                style="accent-color:var(--primary);width:16px;height:16px;cursor:pointer">
            <?php endif; ?>
            <div style="flex:1;font-size:13px;font-weight:600;color:var(--text)"><?=htmlspecialchars($mp['nom'])?></div>
            <div style="font-size:12px;font-weight:700;color:var(--primary);flex-shrink:0"><span dir="ltr" style="unicode-bidi:embed"><?=number_format($mp['prix_unitaire']??0,0)?></span> <?=$lang==='ar'?'دج':'DA'?>/<?=htmlspecialchars(unit_label($mp['unite']??'u',$lang))?></div>
            <?php if(in_array(($mp['unite']??''), ['heure', 'jour', 'unité'])):?>
            <div id="techqw-<?=$d['id_service']?>-<?=$mp['id_prestation']?>" style="display:<?=$is_chk?'flex':'none'?>;align-items:center;gap:5px;flex-shrink:0" onclick="event.stopPropagation()">
              <button type="button" style="width:24px;height:24px;border-radius:6px;border:1px solid var(--border);background:#fff;font-weight:700;cursor:pointer" onclick="techQty(<?=$d['id_service']?>,<?=$mp['id_prestation']?>,-1,<?=$min_qty?>)">−</button>
              <span id="techqv-<?=$d['id_service']?>-<?=$mp['id_prestation']?>" style="font-weight:700;font-size:13px;min-width:18px;text-align:center"><?=$qty?></span>
              <input type="hidden" name="qty_<?=$mp['id_prestation']?>" id="techqi-<?=$d['id_service']?>-<?=$mp['id_prestation']?>" value="<?=$qty?>">
              <input type="hidden" id="techqm-<?=$d['id_service']?>-<?=$mp['id_prestation']?>" value="<?=$min_qty?>">
              <button type="button" style="width:24px;height:24px;border-radius:6px;border:1px solid var(--border);background:#fff;font-weight:700;cursor:pointer" onclick="techQty(<?=$d['id_service']?>,<?=$mp['id_prestation']?>,1,<?=$min_qty?>)">+</button>
            </div>
            <?php else:?>
            <input type="hidden" name="qty_<?=$mp['id_prestation']?>" id="techqi-<?=$d['id_service']?>-<?=$mp['id_prestation']?>" value="<?=$qty?>">
            <?php endif;?>
          </label>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:8px">
          <?php if($d['statut_service']==='terminé'):?>
          <button type="submit" name="modifier_confirm" value="1" class="btn btn-sm" style="background:var(--primary);color:#fff;border:none">✎ <?=$lang==='ar'?'حفظ التعديل':'Enregistrer la modification'?></button>
          <?php else:?>
          <button type="submit" name="complete_confirm" value="1" class="btn btn-sm" style="background:#16a34a;color:#fff;border:none">✓ <?=$lang==='ar'?'تأكيد وإنهاء':'Confirmer et terminer'?></button>
          <?php endif;?>
          <button type="button" class="btn btn-ghost btn-sm" onclick="
            document.getElementById('complete-panel-<?=$d['id_service']?>').style.display='none';
            document.getElementById('actions-<?=$d['id_service']?>').style.display='';
          "><?=$lang==='ar'?'إلغاء':'Annuler'?></button>
        </div>
      </form>
    </div>
    <?php endif;?>

    <!-- Description -->
    <?php if($d['description']):?>
    <div style="padding:12px 20px;border-bottom:1px solid var(--border)">
      <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px">💬 <?=$lang==='ar'?'الوصف':'Description'?></div>
      <div style="font-size:13.5px;color:var(--text2);line-height:1.65"><?=htmlspecialchars($d['description'])?></div>
    </div>
    <?php endif;?>

    <!-- Actions -->
    <div style="padding:13px 20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;background:var(--bg)">
      <?php if($d['statut_service']==='planifié'):?>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="accept_service"><input type="hidden" name="id_service" value="<?=$d['id_service']?>"><input type="hidden" name="tab" value="demandes"><button class="btn btn-primary btn-sm">✓ <?=$lang==='ar'?'قبول':'Accepter'?></button></form>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="refuse_service"><input type="hidden" name="id_service" value="<?=$d['id_service']?>"><input type="hidden" name="tab" value="demandes"><button class="btn btn-r btn-sm">✕ <?=$lang==='ar'?'رفض':'Refuser'?></button></form>
      <?php elseif($d['statut_service']==='en cours'):?>
        <div id="actions-<?=$d['id_service']?>">
          <button class="btn btn-primary btn-sm" onclick="
            document.getElementById('complete-panel-<?=$d['id_service']?>').style.display='';
            document.getElementById('actions-<?=$d['id_service']?>').style.display='none';
          ">✓ <?=$lang==='ar'?'تحديد كمنتهٍ':'Marquer terminé'?></button>
          <form method="POST" style="display:inline" onsubmit="return confirm('<?=$lang==='ar'?'إلغاء هذه الخدمة؟':'Annuler ce service ?'?>')"><input type="hidden" name="action" value="refuse_service"><input type="hidden" name="id_service" value="<?=$d['id_service']?>"><input type="hidden" name="tab" value="demandes"><button class="btn btn-r btn-sm">✕ <?=$lang==='ar'?'إلغاء الخدمة':'Annuler le service'?></button></form>
        </div>
      <?php elseif($d['statut_service']==='annulé'):?>
        <span style="font-size:12px;color:#dc2626;font-weight:600;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:5px 12px">✕ <?=$lang==='ar'?'الطلب مرفوض':'Demande refusée'?></span>
      <?php endif;?>
      <?php if($d['statut_service']==='terminé'):?>
        <div id="actions-<?=$d['id_service']?>">
          <button class="btn btn-primary btn-sm" onclick="
            document.getElementById('complete-panel-<?=$d['id_service']?>').style.display='';
            document.getElementById('actions-<?=$d['id_service']?>').style.display='none';
          ">✎ <?=$lang==='ar'?'تعديل':'Modifier'?></button>
          <?php 
            // Check if there's a paid paiment and no facture
            if (!$d['id_facture']) {
              $paid_chk = $pdo->prepare("SELECT id_paiment FROM paiment WHERE id_service=? AND statut_paiment='payé' LIMIT 1");
              $paid_chk->execute([$d['id_service']]);
              $has_paid = $paid_chk->fetchColumn() ? true : false;
              // Show paiment button only after payment confirmed AND amount < 5000 DA
              if ($has_paid) {
                // Calculate amount to check threshold
                $amt_calc = $pdo->prepare("SELECT COALESCE(SUM(p.prix_unitaire*sp.quantite),0) FROM service_prestation sp JOIN prestation p ON p.id_prestation=sp.id_prestation WHERE sp.id_service=?");
                $amt_calc->execute([$d['id_service']]);
                $svc_amount = floatval($amt_calc->fetchColumn());
                if (!$svc_amount) $svc_amount = $d['prix_final'] ?? $d['prix_estime'] ?? 0;
                if (!facture_uses_tva((float)$svc_amount)) {
                  echo '<a href="paiment.php?id_service='.$d['id_service'].'" class="btn btn-ghost btn-sm">💳 '.($lang==='ar'?'الدفع':'Paiement').'</a>';
                }
              }
            }
          ?>
          <?php if($d['id_facture']):?><a href="facture.php?id=<?=$d['id_facture']?>" target="_blank" class="btn btn-ghost btn-sm">📄 <?=$lang==='ar'?'فاتورة':'Facture'?></a><?php endif;?>
        </div>
      <?php endif;?>
      <div style="flex:1"></div>
      <?php if(!empty($client_phone_href)):?><a href="tel:<?=htmlspecialchars($client_phone_href)?>" class="btn btn-ghost btn-sm">📞 <?=$lang==='ar'?'اتصال':'Appeler'?></a><?php endif;?>
      <a href="?tab=messages&with=<?=$d['cuid']?>" class="btn btn-ghost btn-sm">✉ <?=$lang==='ar'?'رسالة':'Message'?></a>
      <button onclick="openSignal(<?=$d['cuid']?>,<?=htmlspecialchars(json_encode($d['cp'].' '.$d['cn']))?>,this)" class="btn btn-sm" style="background:rgba(239,68,68,.07);color:#dc2626;border:1px solid rgba(239,68,68,.2)">🚩</button>
    </div>

  </div>
  <?php endforeach;?>
  </div>
  <?php endif;?>

  <?php elseif($active_tab==='prestations'): ?>
  <!-- ══ PRESTATIONS ══ -->
  <!-- Add prestation via catalogue -->
  <div style="margin-bottom:20px">
    <div class="sec-title" style="margin-bottom:12px"><?=$lang==='ar'?'إضافة خدمة':'Ajouter une prestation'?></div>

    <?php if(empty($tech_catalogue)): ?>
    <div style="padding:20px;background:var(--surface);border:1.5px dashed var(--border);border-radius:12px;color:var(--muted);font-size:13px;text-align:center">
      <?=$lang==='ar'?'لا توجد خدمات كتالوج متاحة لتخصصك.':'Aucune prestation catalogue disponible pour votre spécialité.'?>
    </div>
    <?php else: ?>

    <div style="font-size:12px;color:var(--muted);margin-bottom:10px"><?=$lang==='ar'?'اختر خدمة لتحديد سعرك:':'Choisissez une prestation pour définir votre tarif :'?></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;margin-bottom:14px">
      <?php foreach($tech_catalogue as $ci):
        $already = in_array(strtolower($ci['nom']), $existing_names);
        $display_name = $lang==='ar' && !empty($ci['nom_ar']) ? $ci['nom_ar'] : $ci['nom'];
      ?>
      <button type="button"
        onclick="<?=$already?'':('openAddPrest('.htmlspecialchars(json_encode($display_name),ENT_QUOTES).','.htmlspecialchars(json_encode($ci['nom']),ENT_QUOTES).','.intval($ci['id_catalogue']).')')?>"
        style="padding:12px 8px;background:<?=$already?'var(--surface2)':'var(--surface)'?>;border:1.5px solid <?=$already?'var(--border)':'var(--border)'?>;border-radius:12px;cursor:<?=$already?'default':'pointer'?>;text-align:center;font-size:12.5px;font-weight:<?=$already?'500':'700'?>;color:<?=$already?'var(--muted)':'var(--text)'?>;transition:all .18s;line-height:1.3;position:relative;font-family:inherit"
        <?=$already?'disabled':''?>
        onmouseover="<?=$already?'':("this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)';this.style.color='var(--primary)'")?>"
        onmouseout="<?=$already?'':("this.style.borderColor='var(--border)';this.style.background='var(--surface)';this.style.color='var(--text)'")?>"
      >
        <?=htmlspecialchars($lang==='ar'&&!empty($ci['nom_ar'])?$ci['nom_ar']:$ci['nom'])?>
        <?php if($already):?><br><span style="font-size:10px;color:var(--muted);font-weight:400">✓ <?=$lang==='ar'?'مضافة مسبقاً':'déjà ajouté'?></span><?php endif;?>
      </button>
      <?php endforeach;?>
    </div>
    <?php endif;?>

    <div id="add-prest-form" style="display:none;background:var(--primary-light);border:2px solid var(--primary);border-radius:14px;padding:18px">
      <div style="font-size:13px;font-weight:700;color:var(--primary);margin-bottom:14px">
        <?=$lang==='ar'?'تحديد السعر لـ:':'Définir le tarif pour :'?> <span id="add-prest-name" style="font-weight:800"></span>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_prestation">
        <input type="hidden" name="tab" value="prestations">
        <input type="hidden" name="nom" id="add-prest-nom">
        <input type="hidden" name="id_catalogue" id="add-prest-id-catalogue">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
          <div>
            <div class="flbl" style="margin-bottom:6px"><?=$lang==='ar'?'السعر (دج) *':'Prix unitaire (DA) *'?></div>
            <input class="finput" id="add-prest-prix" name="prix_unitaire" type="number" min="0" step="50" placeholder="Ex: 1500" required style="width:100%">
          </div>
          <div>
            <div class="flbl" style="margin-bottom:6px"><?=$lang==='ar'?'الوحدة':'Unité'?></div>
            <select class="finput" name="unite" style="width:100%">
              <option value="intervention"><?=$lang==='ar'?'لكل تدخل (جزافي)':'Par intervention (forfait)'?></option>
              <option value="heure"><?=$lang==='ar'?'لكل ساعة':'Par heure'?></option>
              <option value="jour"><?=$lang==='ar'?'لكل يوم':'Par jour'?></option>
              <option value="unité"><?=$lang==='ar'?'لكل وحدة':'Par unité'?></option>
            </select>
          </div>
        </div>
        <div style="margin-bottom:12px">
          <div class="flbl" style="margin-bottom:6px"><?=$lang==='ar'?'الوصف (اختياري)':'Description (optionnel)'?></div>
          <textarea class="finput" name="description" rows="2" placeholder="<?=$lang==='ar'?'ما يشمله، المدة التقديرية...':'Ce qui est inclus, délai estimé...'?>" style="width:100%"></textarea>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-primary btn-sm" type="submit" style="background:var(--primary);border-color:var(--primary)"><span class="btn-ico">+</span> <?=$lang==='ar'?'إضافة':'Ajouter'?></button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('add-prest-form').style.display='none'"><?=$lang==='ar'?'إلغاء':'Annuler'?></button>
        </div>
      </form>
    </div>
  </div>

  <div class="sec-title"><?=$lang==='ar'?'قائمة خدماتي ('.count($prestations).')':'Mon menu de services ('.count($prestations).')'?></div>
  <?php if(empty($prestations)):?>
  <div class="empty"><div class="ico">🛠</div><p><?=$lang==='ar'?'أضف خدماتك لتظهر في نتائج البحث':'Ajoutez vos prestations pour apparaître dans les recherches clients'?></p></div>
  <?php else:?>
  <div class="menu-grid">
    <?php foreach($prestations as $p):?>
    <div class="menu-item <?=!$p['actif']?'inactive':''?>" id="pitem-<?=$p['id_prestation']?>">
      <div class="menu-item-actions">
        <button class="btn btn-ghost btn-sm" onclick="toggleEditPrestation(<?=$p['id_prestation']?>)" title="<?=$lang==='ar'?'تعديل':'Modifier'?>">✏️</button>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_prestation"><input type="hidden" name="id_prestation" value="<?=$p['id_prestation']?>"><input type="hidden" name="tab" value="prestations"><button class="btn btn-ghost btn-sm" title="<?=$p['actif']?($lang==='ar'?'تعطيل':'Désactiver'):($lang==='ar'?'تفعيل':'Activer')?>"><?=$p['actif']?'👁':'👁‍🗨'?></button></form>
        <form method="POST" style="display:inline" onsubmit="return confirm('<?=$lang==='ar'?'حذف هذه الخدمة؟':'Supprimer cette prestation ?'?>')"><input type="hidden" name="action" value="delete_prestation"><input type="hidden" name="id_prestation" value="<?=$p['id_prestation']?>"><input type="hidden" name="tab" value="prestations"><button class="btn btn-r btn-sm">🗑</button></form>
      </div>
      <!-- View mode -->
      <div id="pview-<?=$p['id_prestation']?>">
        <div class="menu-item-name"><?=htmlspecialchars($lang==='ar'&&!empty($p['nom_ar'])?$p['nom_ar']:$p['nom'])?></div>
        <?php if($p['description']):?><div class="menu-item-desc"><?=htmlspecialchars($p['description'])?></div><?php endif;?>
        <div class="menu-item-price" style="<?=$lang==='ar'?'text-align:left':'' ?>">
          <span dir="ltr" style="unicode-bidi:embed"><?=number_format($p['prix_unitaire']??0,0)?></span> <?=$lang==='ar'?'دج':'DA'?>
          <span class="menu-item-unit">/ <?=htmlspecialchars(unit_label($p['unite']??'intervention',$lang))?></span>
        </div>
        <?php if(!$p['actif']):?><div style="margin-top:8px"><span class="chip chip-red"><?=$lang==='ar'?'معطلة':'Désactivé'?></span></div><?php else:?><div style="margin-top:8px"><span class="chip chip-green"><?=$lang==='ar'?'مفعلة':'Actif'?></span></div><?php endif;?>
      </div>
      <!-- Edit mode -->
      <div id="pedit-<?=$p['id_prestation']?>" style="display:none;margin-top:8px">
        <form method="POST">
          <input type="hidden" name="action" value="update_prestation">
          <input type="hidden" name="tab" value="prestations">
          <input type="hidden" name="id_prestation" value="<?=$p['id_prestation']?>">
          <div style="display:flex;flex-direction:column;gap:8px">
            <input class="finput" name="nom" value="<?=htmlspecialchars($p['nom'])?>" placeholder="<?=$lang==='ar'?'الاسم':'Nom'?>" required style="font-size:13px;padding:7px 10px"/>
            <textarea class="finput" name="description" placeholder="<?=$lang==='ar'?'الوصف':'Description'?>" style="font-size:12px;padding:7px 10px;resize:none;rows:2"><?=htmlspecialchars($p['description']??'')?></textarea>
            <div style="display:flex;gap:8px">
              <input class="finput" name="prix_unitaire" type="number" min="0" step="50" value="<?=$p['prix_unitaire']??0?>" style="flex:1;font-size:13px;padding:7px 10px"/>
              <select class="finput" name="unite" style="flex:1;font-size:13px;padding:7px 10px">
                <option value="heure" <?=$p['unite']==='heure'?'selected':''?>><?=$lang==='ar'?'لكل ساعة':'/ heure'?></option>
                <option value="jour" <?=$p['unite']==='jour'?'selected':''?>><?=$lang==='ar'?'لكل يوم':'/ jour'?></option>
                <option value="unité" <?=$p['unite']==='unité'?'selected':''?>><?=$lang==='ar'?'لكل وحدة':'/ unité'?></option>
                <option value="intervention" <?=$p['unite']==='intervention'?'selected':''?>><?=$lang==='ar'?'لكل تدخل':'/ intervention'?></option>
              </select>
            </div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-primary btn-sm" type="submit" style="flex:1"><span class="btn-ico">✓</span> <?=$lang==='ar'?'حفظ':'Enregistrer'?></button>
              <button type="button" class="btn btn-ghost btn-sm" onclick="toggleEditPrestation(<?=$p['id_prestation']?>)"><?=$lang==='ar'?'إلغاء':'Annuler'?></button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <?php elseif($active_tab==='planning'): ?>
  <!-- ══ PLANNING ══ -->
  <?php $jours_ar=['Lundi'=>'الإثنين','Mardi'=>'الثلاثاء','Mercredi'=>'الأربعاء','Jeudi'=>'الخميس','Vendredi'=>'الجمعة','Samedi'=>'السبت','Dimanche'=>'الأحد']; ?>
  <div class="sec-title"><?=$lang==='ar'?'إدارة أوقات التوفر':'Gérez vos disponibilités'?></div>
  <p style="color:var(--muted);font-size:13px;margin-bottom:20px"><?=$lang==='ar'?'حدد الأيام والأوقات التي تكون فيها متاحاً لاستقبال الطلبات.':'Indiquez les jours et heures où vous êtes disponible pour recevoir des demandes.'?></p>
  <form method="POST">
    <input type="hidden" name="action" value="save_dispo">
    <input type="hidden" name="tab" value="planning">
    <div class="planning-grid" style="margin-bottom:20px">
      <?php foreach($jours_semaine as $jour):
        $d = $dispo_map[$jour] ?? null;
        $checked = $d ? 'checked' : '';
        $debut = $d ? substr($d['heure_debut'],0,5) : '09:00';
        $fin   = $d ? substr($d['heure_fin'],0,5)   : '18:00';
      ?>
      <div class="planning-row">
        <div class="planning-day"><?=$lang==='ar'?($jours_ar[$jour]??$jour):$jour?></div>
        <label class="planning-toggle">
          <input type="checkbox" name="jours_enabled[<?=$jour?>]" value="1" <?=$checked?> onchange="toggleDay(this,'<?=$jour?>')">
          <span class="toggle-slider"></span>
        </label>
        <div class="time-inputs" id="times-<?=$jour?>" style="<?=!$d?'opacity:.4;pointer-events:none':''?>">
          <span><?=$lang==='ar'?'من':'De'?></span>
          <input type="time" name="jours[<?=$jour?>][debut]" value="<?=$debut?>">
          <span><?=$lang==='ar'?'إلى':'à'?></span>
          <input type="time" name="jours[<?=$jour?>][fin]" value="<?=$fin?>">
        </div>
        <?php if($d):?><span class="chip chip-green" style="margin-left:auto"><?=$lang==='ar'?'متاح':'Disponible'?></span><?php else:?><span class="chip chip-red" style="margin-left:auto"><?=$lang==='ar'?'غير متاح':'Indisponible'?></span><?php endif;?>
      </div>
      <?php endforeach;?>
    </div>
    <button class="btn btn-primary" type="submit">💾 <?=$lang==='ar'?'حفظ الجدول الزمني':'Enregistrer le planning'?></button>
  </form>

  <?php elseif($active_tab==='messages'): ?>
  <!-- ══ MESSAGES ══ -->
  <div class="msg-layout">
    <div class="msg-list">
      <div class="msg-search"><input type="text" placeholder="<?=$lang==='ar'?'بحث...':'Rechercher…'?>"/></div>
      <?php if(empty($convs)):?>
      <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px"><?=$lang==='ar'?'لا توجد محادثات':'Aucune conversation'?></div>
      <?php else:foreach($convs as $conv):?>
        <a href="?tab=messages&with=<?=$conv['other_id']?>" class="msg-item <?=$conv['other_id']==$sel_uid?'active':''?>">
          <div class="mav" style="width:34px;height:34px;background:var(--primary-light);color:var(--primary)"><?=ini($conv['nom'],$conv['prenom'])?></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px">
              <span class="msg-sender"><?=htmlspecialchars($conv['prenom'].' '.$conv['nom'])?></span>
              <span class="msg-time"><?=$conv['last_date']?date('d/m',strtotime($conv['last_date'])):''?></span>
            </div>
            <div class="msg-preview"><?=ucfirst($conv['role'])?></div>
          </div>
          <?php if($conv['unread']>0):?><div class="msg-unread"></div><?php endif;?>
        </a>
      <?php endforeach;endif;?>
    </div>
    <div class="chat-area">
      <?php if($chat_user):?>
      <div class="chat-hdr">
        <div class="mav" style="width:36px;height:36px;background:var(--primary-light);color:var(--primary)"><?=ini($chat_user['nom'],$chat_user['prenom'])?></div>
        <div><div style="font-weight:600;font-size:14px"><?=htmlspecialchars($chat_user['prenom'].' '.$chat_user['nom'])?></div>
        <div style="font-size:11px;color:var(--muted)"><?=ucfirst($chat_user['role'])?></div></div>
      </div>
      <div class="chat-msgs" id="chat-messages">
        <?php if(empty($chat_msgs)):?>
        <div style="text-align:center;color:var(--muted);font-size:13px;padding:30px"><?=$lang==='ar'?'ابدأ المحادثة':'Commencez la conversation'?></div>
        <?php else:foreach($chat_msgs as $m):$im=$m['id_expediteur']==$user_id;?>
          <div class="mbw <?=$im?'me':''?>" data-msgid="<?=$m['id_message']?>">
            <div class="mav" style="width:30px;height:30px;font-size:11px;flex-shrink:0;background:<?=$im?'var(--primary);color:#fff':'var(--primary-light);color:var(--primary)'?>"><?=$im?ini($tech['nom'],$tech['prenom']):ini($chat_user['nom'],$chat_user['prenom'])?></div>
            <div class="bubble-inner"><div class="bubble <?=$im?'me':'other'?>"><?=nl2br(htmlspecialchars($m['contenu']))?></div>
            <div class="bt"><?=$m['data_envoi']?date('d/m H:i',strtotime($m['data_envoi'])):''?></div></div>
          </div>
        <?php endforeach;endif;?>
      </div>
      <div class="chat-inp">
        <form id="chat-send-form" method="POST">
          <input type="hidden" name="action" value="send_message">
          <input type="hidden" name="id_destinataire" value="<?=$sel_uid?>">
          <input type="hidden" name="tab" value="messages">
          <input type="hidden" name="with_redirect" value="<?=$sel_uid?>">
          <textarea id="chat-textarea" name="contenu" placeholder="<?=$lang==='ar'?'اكتب رسالة...':'Écrire un message…'?>" autocomplete="off" rows="1"></textarea>
          <button type="submit" class="send-btn"><?=$lang==='ar'?'إرسال':'Envoyer'?></button>
        </form>
      </div>
      <?php else:?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:32px;text-align:center">
        <div style="font-size:48px">💬</div>
        <div style="font-weight:700;font-size:15px;color:var(--text)"><?=$lang==='ar'?'لم يتم اختيار محادثة':'Aucune conversation sélectionnée'?></div>
        <div style="font-size:13px;color:var(--muted);max-width:260px"><?=$lang==='ar'?'اختر محادثة من القائمة، أو انتظر حتى يتواصل معك عميل.':'Choisissez une conversation dans la liste, ou attendez qu\'un client vous contacte via une réservation.'?></div>
      </div>
      <?php endif;?>
    </div>
  </div>

  <?php elseif($active_tab==='revenus'): ?>
  <!-- ══ REVENUS ══ -->
  <div style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:18px;padding:24px;margin-bottom:16px;color:#fff;position:relative;overflow:hidden">
    <div style="position:absolute;right:-20px;top:-20px;font-size:100px;opacity:.07;line-height:1">💰</div>
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;opacity:.75;margin-bottom:8px"><?=date('F Y')?></div>
    <div style="font-family:'Syne',sans-serif;font-size:36px;font-weight:800;line-height:1;margin-bottom:6px"><?=money($rev_mois,$lang)?></div>
    <div style="font-size:13px;opacity:.75"><?=$lang==='ar'?'إيرادات الشهر الحالي':'Revenus du mois en cours'?></div>
  </div>
  <div class="stats-g" style="margin-bottom:16px">
    <div class="stat"><div class="stat-lbl"><?=$lang==='ar'?'الإجمالي التراكمي':'Total cumulé'?></div><div class="stat-val" style="font-size:20px"><?=money($rev_total,$lang)?></div></div>
    <div class="stat"><div class="stat-lbl"><?=$lang==='ar'?'التدخلات':'Interventions'?></div><div class="stat-val"><?=$total_done?></div></div>
  </div>

  <!-- Commission breakdown -->
  <div style="background:linear-gradient(135deg,#fff8e1,#fffde7);border:1.5px solid #f59e0b;border-radius:16px;padding:20px;margin-bottom:16px">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#b45309;margin-bottom:14px">
      💼 <?=$lang==='ar'?'عمولة المنصة (8%)':'Commission plateforme (8 %)'?>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center">
      <div style="background:#fff;border-radius:12px;padding:14px 8px">
        <div style="font-size:10px;color:#b45309;font-weight:700;margin-bottom:4px"><?=$lang==='ar'?'إجمالي المدفوعات':'Total encaissé'?></div>
        <div style="font-size:17px;font-weight:800;color:var(--text)"><?=money($comm_tech['total_brut']??0,$lang)?></div>
      </div>
      <div style="background:#fff;border-radius:12px;padding:14px 8px">
        <div style="font-size:10px;color:#b45309;font-weight:700;margin-bottom:4px"><?=$lang==='ar'?'إجمالي العمولات':'Total commissions'?></div>
        <div style="font-size:17px;font-weight:800;color:var(--text)"><?=money($comm_tech['total_comm']??0,$lang)?></div>
      </div>
      <?php $comm_due = $comm_tech['comm_due'] ?? 0; $nb_due = $comm_tech['nb_due'] ?? 0; ?>
      <div style="background:<?=$comm_due>0?'linear-gradient(135deg,#fef2f2,#fee2e2)':'linear-gradient(135deg,#f0fdf4,#dcfce7)'?>;border-radius:12px;padding:14px 8px;border:1.5px solid <?=$comm_due>0?'#fca5a5':'#86efac'?>">
        <div style="font-size:10px;font-weight:700;margin-bottom:4px;color:<?=$comm_due>0?'#b91c1c':'#15803d'?>"><?=$lang==='ar'?'مستحق حالياً':'Vous devez actuellement'?></div>
        <div style="font-size:17px;font-weight:800;color:<?=$comm_due>0?'#dc2626':'#16a34a'?>"><?=money($comm_due,$lang)?></div>
        <?php if($nb_due>0):?>
        <div style="font-size:10px;color:#b91c1c;margin-top:3px"><?=$nb_due?> intervention(s)</div>
        <?php endif;?>
      </div>
    </div>
    <div style="margin-top:12px;font-size:11px;color:#92400e;text-align:center">
      <?=$lang==='ar'
        ?'أنت تستلم المبلغ كاملاً نقداً — عليك دفع 8% لمنصة خدمة DZ كل شهر'
        :'Vous encaissez le cash directement — vous reversez 8 % à Khidma DZ chaque mois'?>
    </div>
  </div>
  <div class="card" style="padding:20px">
    <div class="sec-title" style="margin-bottom:16px"><?=$lang==='ar'?'السجل الشهري':'Historique par mois'?></div>
    <?php if(empty($monthly)):?>
    <div class="empty" style="padding:24px 0"><p><?=$lang==='ar'?'لا توجد إيرادات مسجلة بعد':'Aucun revenu enregistré pour le moment'?></p></div>
    <?php else:?>
    <div style="display:flex;flex-direction:column;gap:0">
      <?php foreach($monthly as $idx=>$m):
        $is_current = ((int)$m['yr']===$current_yr && (int)$m['mo']===$current_mo);
        $max_total  = max(array_column($monthly,'total')) ?: 1;
        $pct        = round($m['total']/$max_total*100);
      ?>
      <div style="padding:14px 0;<?=$idx>0?'border-top:1px solid var(--border)':''?>">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:13px;font-weight:<?=$is_current?'800':'600'?>;color:<?=$is_current?'var(--primary)':'var(--text)'?>"><?=htmlspecialchars($m['label'])?></span>
            <?php if($is_current):?><span style="background:var(--primary-light);color:var(--primary);font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px"><?=$lang==='ar'?'الشهر الحالي':'Mois actuel'?></span><?php endif;?>
          </div>
          <div style="text-align:right">
            <span style="font-family:'Syne',sans-serif;font-weight:800;font-size:15px;color:var(--green)">+<?=money($m['total'],$lang)?></span>
            <span style="font-size:11px;color:var(--muted);margin-left:6px"><?=$m['nb']?> <?=$lang==='ar'?'دفعة'.($m['nb']>1?'':''):'paiement'.($m['nb']>1?'s':'')?></span>
            <div style="margin-top:6px;font-size:12px;color:var(--muted)">
              <?php if(!empty($m['commission_total']) && $m['commission_total']>0): ?>
                <span style="margin-left:8px;display:inline-block"><?=$lang==='ar'?'عمولة':'Commission'?>: <strong><?=money($m['commission_total'],$lang)?></strong></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <div style="height:6px;background:var(--surface2);border-radius:3px;overflow:hidden">
          <div style="height:100%;width:<?=$pct?>%;background:<?=$is_current?'linear-gradient(90deg,var(--primary),var(--secondary))':'var(--primary-light)'?>;border-radius:3px;transition:width .5s ease"></div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>

  <?php elseif($active_tab==='avis'): ?>
  <!-- ══ AVIS ══ -->
  <div class="two-col" style="margin-bottom:20px">
    <div class="stat" style="text-align:center;padding:24px">
      <div class="stat-lbl"><?=$lang==='ar'?'التقييم العام':'Note globale'?></div>
      <div style="font-family:'Syne',sans-serif;font-size:52px;font-weight:800;color:var(--amber);line-height:1"><?=number_format($note_data['avg'],1)?></div>
      <div style="color:var(--amber);font-size:22px;margin:6px 0"><?=stars($note_data['avg'])?></div>
      <div style="color:var(--muted);font-size:12px"><?=$lang==='ar'?'بناءً على '.$note_data['cnt'].' تقييم':'Basé sur '.$note_data['cnt'].' avis'?></div>
    </div>
    <div class="card" style="padding:20px">
      <div class="sec-title" style="margin-bottom:14px"><?=$lang==='ar'?'توزيع التقييمات':'Distribution des notes'?></div>
      <?php for($i=5;$i>=1;$i--):
        $cnt_i = count(array_filter($avis_list,fn($a)=>$a['note']==$i));
        $pct = $note_data['cnt']>0?round($cnt_i/$note_data['cnt']*100):0;
      ?>
      <div style="display:flex;align-items:center;gap:8px;font-size:12px;margin-bottom:8px">
        <span style="width:20px;color:var(--amber);font-weight:700"><?=$i?>★</span>
        <div style="flex:1;height:8px;background:var(--surface2);border-radius:4px"><div style="height:100%;width:<?=$pct?>%;background:var(--amber);border-radius:4px;opacity:<?=0.4+$i*0.12?>"></div></div>
        <span style="color:var(--muted);width:35px"><?=$pct?>%</span>
      </div>
      <?php endfor;?>
    </div>
  </div>
  <?php if(!empty($avis_par_service)):?>
  <div class="card" style="padding:20px;margin-bottom:20px">
    <div class="sec-title" style="margin-bottom:14px">🔧 <?=$lang==='ar'?'تفاصيل حسب الخدمة':'Détail par service'?></div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead><tr style="border-bottom:2px solid var(--border)">
        <th style="text-align:<?=$lang==='ar'?'right':'left'?>;padding:7px 10px;color:var(--muted);font-weight:600"><?=$lang==='ar'?'الخدمة':'Service'?></th>
        <th style="text-align:center;padding:7px 10px;color:var(--muted);font-weight:600"><?=$lang==='ar'?'متوسط':'Note moy.'?></th>
        <th style="text-align:center;padding:7px 10px;color:var(--muted);font-weight:600"><?=$lang==='ar'?'عدد':'Nb avis'?></th>
        <th style="text-align:center;padding:7px 10px;color:var(--muted);font-weight:600"><?=$lang==='ar'?'أدنى':'Min'?></th>
        <th style="text-align:center;padding:7px 10px;color:var(--muted);font-weight:600"><?=$lang==='ar'?'أعلى':'Max'?></th>
      </tr></thead>
      <tbody>
      <?php foreach($avis_par_service as $ps):?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:8px 10px;font-weight:600"><?=htmlspecialchars($ps['service_nom']??'—')?></td>
        <td style="padding:8px 10px;text-align:center"><span style="color:var(--amber)"><?=stars($ps['note_moy'])?></span><strong style="margin-left:5px"><?=$ps['note_moy']?></strong></td>
        <td style="padding:8px 10px;text-align:center"><span style="background:var(--surface2);border-radius:6px;padding:2px 9px;font-size:12px"><?=$ps['nb']?></span></td>
        <td style="padding:8px 10px;text-align:center;color:var(--red)"><?=$ps['note_min']?>★</td>
        <td style="padding:8px 10px;text-align:center;color:var(--green)"><?=$ps['note_max']?>★</td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif;?>
  <div class="sec-title"><?=$lang==='ar'?'آخر التقييمات المستلمة':'Derniers avis reçus'?></div>
  <?php if(empty($avis_list)):?>
  <div class="empty"><div class="ico">⭐</div><p><?=$lang==='ar'?'لا توجد تقييمات بعد':'Aucun avis pour l\'instant'?></p></div>
  <?php else:foreach($avis_list as $a):?>
  <div class="avis-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
      <div style="display:flex;align-items:center;gap:9px">
        <div class="mav" style="background:var(--blue-l);color:var(--blue)"><?=ini($a['nom'],$a['prenom']??'')?></div>
        <strong style="font-size:13px"><?=htmlspecialchars(($a['prenom']??'').' '.$a['nom'])?></strong>
      </div>
      <div class="stars-sm"><?=stars($a['note'])?></div>
    </div>
    <?php if($a['commentaire']):?><div style="font-size:13px;color:var(--muted);line-height:1.6"><?=htmlspecialchars($a['commentaire'])?></div><?php endif;?>
    <div style="font-size:11px;color:var(--muted);margin-top:6px"><?=$a['date_avis']?date('d/m/Y',strtotime($a['date_avis'])):''?></div>
  </div>
  <?php endforeach;endif;?>

  <?php elseif($active_tab==='notifications'): ?>
  <!-- ══ NOTIFICATIONS ══ -->
  <div class="card">
    <div class="card-head">
      <div class="card-title">🔔 <?=$lang==='ar'?'الإشعارات':'Notifications'?></div>
      <?php if(!empty($notifs_all)):?>
      <form method="POST"><input type="hidden" name="action" value="mark_all_read"><input type="hidden" name="tab" value="notifications"><button class="btn btn-ghost btn-sm" type="submit"><?=$lang==='ar'?'تحديد الكل كمقروء':'Tout marquer lu'?></button></form>
      <?php endif;?>
    </div>
    <?php if(empty($notifs_all)):?>
    <div class="empty"><div class="ico">🔔</div><p><?=$lang==='ar'?'لا توجد إشعارات':'Aucune notification'?></p></div>
    <?php else: ?><div id="notif-list"><?php foreach($notifs_all as $n): ?>
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start;background:<?=$n['lu']?'var(--surface)':'var(--primary-light)'?>">
      <div style="width:38px;height:38px;border-radius:10px;background:var(--green-l);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">🔔</div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:600;color:var(--text)"><?=htmlspecialchars($n['titre'])?></div>
        <div style="font-size:13px;color:var(--muted);margin-top:3px"><?=htmlspecialchars($n['contenu'])?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px"><?=$n['date_notif']?date('d/m/Y H:i',strtotime($n['date_notif'])):''?></div>
      </div>
      <?php if($n['lien']):?><a href="<?=htmlspecialchars($n['lien'])?>" class="btn btn-ghost btn-sm" style="flex-shrink:0"><?=$lang==='ar'?'عرض':'Voir →'?></a><?php endif;?>
    </div>
    <?php endforeach; endif;?>
  </div>

  <?php elseif($active_tab==='notifications_duplicate_removed'): ?>
  <!-- ══ NOTIFICATIONS ══ -->
  <div class="card">
    <div class="card-head">
      <div class="card-title">🔔 <?=$lang==='ar'?'الإشعارات':'Notifications'?></div>
      <?php if(count(array_filter($notifs_all,fn($n)=>!$n['lu']))>0):?>
      <form method="POST"><input type="hidden" name="action" value="mark_all_read"><input type="hidden" name="tab" value="notifications"><button class="btn btn-ghost btn-sm">✓ <?=$lang==='ar'?'تحديد الكل كمقروء':'Tout marquer lu'?></button></form>
      <?php endif;?>
    </div>
    <?php if(empty($notifs_all)):?>
    <div class="empty"><div class="ico">🔔</div><p><?=$lang==='ar'?'لا توجد إشعارات':'Aucune notification'?></p></div>
    <?php else: ?><div id="notif-list"><?php foreach($notifs_all as $n): ?>
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start;background:<?=!$n['lu']?'rgba(6,182,212,.05)':'transparent'?>">
      <div style="width:38px;height:38px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">🔔</div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:<?=!$n['lu']?'700':'600'?>"><?=htmlspecialchars($n['titre'])?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px"><?=htmlspecialchars($n['contenu'])?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px"><?=$n['date_notif']?date('d/m/Y H:i',strtotime($n['date_notif'])):''?></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
        <?php if(!$n['lu']):?><div style="width:8px;height:8px;background:var(--primary);border-radius:50%"></div><?php endif;?>
        <?php if($n['lien']):?><a href="<?=htmlspecialchars($n['lien'])?>" class="btn btn-ghost btn-sm"><?=$lang==='ar'?'عرض':'Voir →'?></a><?php endif;?>
      </div>
    </div>
    <?php endforeach; endif;?>
  </div>

  <?php elseif($active_tab==='profil'): ?>
  <!-- ══ PROFIL ══ -->
  <div class="prof-hero">
    <div class="prof-av"><?=ini($tech['nom'],$tech['prenom'])?></div>
    <div class="prof-main">
      <div class="prof-name"><?=htmlspecialchars($tech['prenom'].' '.$tech['nom'])?></div>
      <div class="prof-spec"><?=htmlspecialchars(svc_label($tech['specialité']??'',$lang)?:($lang==='ar'?'تقني':'Technicien'))?> · <?=htmlspecialchars(wilaya_display($tech['ville']??($lang==='ar'?'الجزائر':'Algérie'),$lang))?></div>
      <div class="prof-stats">
        <div class="prof-stat">⭐ <strong><?=number_format($note_data['avg'],1)?></strong> (<?=$note_data['cnt']?> <?=$lang==='ar'?'تقييم':'avis'?>)</div>
        <div class="prof-stat">📋 <strong><?=$total_done?></strong> <?=$lang==='ar'?'تدخل':'interventions'?></div>
        <div class="prof-stat">📍 <strong><?=htmlspecialchars(wilaya_display($tech['ville']??'—',$lang))?></strong></div>
      </div>
      <div style="margin-top:10px">
        <?php if($tech['verifie']):?><span class="chip chip-green">✓ <?=$lang==='ar'?'ملف موثق':'Profil Vérifié'?></span><?php else:?><span class="chip chip-amber">⏳ <?=$lang==='ar'?'في انتظار التحقق':'En attente de validation'?></span><?php endif;?>
      </div>
    </div>
  </div>
  <div id="tech-profile-summary" class="card" style="padding:22px;margin-bottom:14px">
    <div class="sec-title" style="margin-bottom:14px"><?=$lang==='ar'?'معلوماتي الشخصية':'Mes informations personnelles'?></div>
    <div class="form-grid">
      <div class="form-group"><div class="flbl"><?=$lang==='ar'?'الاسم':'Prénom'?></div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"><?=htmlspecialchars($tech['prenom']??'')?></div></div>
      <div class="form-group"><div class="flbl"><?=$lang==='ar'?'اللقب':'Nom'?></div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"><?=htmlspecialchars($tech['nom']??'')?></div></div>
      <div class="form-group"><div class="flbl">Email</div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($tech['email']??'')?></div></div>
      <div class="form-group"><div class="flbl"><?=$lang==='ar'?'الهاتف':'Téléphone'?></div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"><?=htmlspecialchars($tech['telephone']??'')?></div></div>
      <div class="form-group"><div class="flbl"><?=$lang==='ar'?'الولاية':'Wilaya'?></div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"><?=htmlspecialchars(wilaya_display($tech['ville']??'—',$lang))?></div></div>
      <div class="form-group"><div class="flbl"><?=$lang==='ar'?'التخصص':'Spécialité'?></div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"><?=htmlspecialchars(svc_label($tech['specialité']??'',$lang)?:($lang==='ar'?'تقني':'Technicien'))?></div></div>
      <div class="form-group"><div class="flbl"><?=$lang==='ar'?'الخبرة':'Expérience'?></div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"><?=htmlspecialchars(($tech['experience']??0).' '.($lang==='ar'?'سنة':'ans'))?></div></div>
      <div class="form-group full"><div class="flbl"><?=$lang==='ar'?'نبذة / وصف':'Bio / Description'?></div><div class="finput" style="background:#fff;min-height:70px;white-space:pre-wrap;line-height:1.6"><?=htmlspecialchars($tech['description_profil']?:($lang==='ar'?'لا توجد نبذة':'Aucune description'))?></div></div>
    </div>
    <div style="display:flex;justify-content:flex-start;margin-top:18px">
      <button class="btn btn-primary" type="button" onclick="toggleTechProfileEdit(true)">✏️ <?=$lang==='ar'?'تعديل':'Modifier'?></button>
    </div>
  </div>
  <div id="tech-profile-form" class="card" style="padding:22px;display:none">
    <div class="sec-title" style="margin-bottom:16px"><?=$lang==='ar'?'تعديل معلوماتي':'Modifier mes informations'?></div>
    <form method="POST">
      <input type="hidden" name="action" value="update_profile">
      <input type="hidden" name="tab" value="profil">
      <div class="form-grid">
        <div class="form-group"><div class="flbl"><?=$lang==='ar'?'الاسم':'Prénom'?></div><input class="finput" name="prenom" value="<?=htmlspecialchars($tech['prenom']??'')?>"/></div>
        <div class="form-group"><div class="flbl"><?=$lang==='ar'?'اللقب':'Nom'?></div><input class="finput" name="nom" value="<?=htmlspecialchars($tech['nom']??'')?>"/></div>
        <div class="form-group"><div class="flbl">Email</div><input class="finput" type="email" name="email" value="<?=htmlspecialchars($tech['email']??'')?>"/></div>
        <div class="form-group"><div class="flbl"><?=$lang==='ar'?'الهاتف':'Téléphone'?></div><input class="finput" name="telephone" value="<?=htmlspecialchars($tech['telephone']??'')?>"/></div>
        <div class="form-group">
          <div class="flbl"><?=$lang==='ar'?'الولاية':'Wilaya'?></div>
          <select class="finput" name="ville">
            <?php
            $wfr_dt = wilayas_fr();
            $war_dt = wilayas_ar();
            $tvn = wilaya_norm((string)($tech['ville'] ?? ''));
            foreach($wfr_dt as $num=>$fr):$ar=$war_dt[$num]??$fr;$sel=($tvn===wilaya_norm($fr))?'selected':'';$lbl=$lang==='ar'?$num.' — '.$ar:$num.' — '.$fr;
            ?><option value="<?=htmlspecialchars($fr)?>" <?=$sel?>><?=htmlspecialchars($lbl)?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group">
          <div class="flbl"><?=$lang==='ar'?'التخصص':'Spécialité'?></div>
          <?=services_select('specialite', $lang, $tech['specialité']??'', $lang==='ar'?'اختر التخصص':'Choisir une spécialité')?>
        </div>
        <div class="form-group"><div class="flbl"><?=$lang==='ar'?'الخبرة (سنوات)':'Expérience (années)'?></div><input class="finput" type="number" name="experience" min="0" max="50" value="<?=$tech['experience']??0?>"/></div>
        <div class="form-group full"><div class="flbl"><?=$lang==='ar'?'نبذة / وصف':'Bio / Description'?></div><textarea class="finput" name="bio" rows="3" placeholder="<?=$lang==='ar'?'صف نفسك وخبراتك...':'Décrivez votre expérience et vos compétences…'?>"><?=htmlspecialchars($tech['description_profil']??'')?></textarea></div>
        <div class="form-group full"><div class="flbl"><?=$lang==='ar'?'كلمة مرور جديدة (اتركه فارغاً للإبقاء)':'Nouveau mot de passe (vide = inchangé)'?></div><input class="finput" type="password" name="new_password" placeholder="••••••••"/></div>
      </div>
      <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-start">
        <button class="btn btn-primary" type="submit">💾 <?=$lang==='ar'?'حفظ':'Enregistrer'?></button>
        <button type="button" class="btn btn-ghost" onclick="toggleTechProfileEdit(false)"><?=$lang==='ar'?'إلغاء':'Annuler'?></button>
      </div>
    </form>
  </div>
  <?php endif;?>

  </div><!-- /content -->
</main>

<!-- SIGNAL MODAL -->
<div style="position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:blur(4px);z-index:300;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s" id="signal-modal">
  <div style="background:#fff;border-radius:20px;width:460px;max-width:95vw;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.15);border:1px solid var(--border)">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--bg)">
      <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:16px">🚩 <?=$lang==='ar'?'الإبلاغ عن مستخدم':'Signaler un utilisateur'?></div>
      <div onclick="closeSignal()" style="cursor:pointer;width:28px;height:28px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:13px">✕</div>
    </div>
    <div style="padding:20px 24px">
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px" id="signal-desc"></p>
      <form method="POST" action="actions/handle_signalement.php">
        <input type="hidden" name="id_user_signale" id="signal-uid">
        <div style="margin-bottom:12px">
          <div class="flbl"><?=$lang==='ar'?'السبب':'Motif'?></div>
          <select name="motif" class="finput">
            <option value="comportement"><?=$lang==='ar'?'سلوك غير لائق':'Comportement inapproprié'?></option>
            <option value="fraude"><?=$lang==='ar'?'احتيال':'Fraude / Arnaque'?></option>
            <option value="absence"><?=$lang==='ar'?'غياب غير مبرر':'Absence non justifiée'?></option>
            <option value="paiement"><?=$lang==='ar'?'مشكل دفع':'Problème de paiement'?></option>
            <option value="autre"><?=$lang==='ar'?'أخرى':'Autre'?></option>
          </select>
        </div>
        <div style="margin-bottom:16px">
          <div class="flbl"><?=$lang==='ar'?'الوصف':'Description'?></div>
          <textarea name="description" class="finput" rows="3" placeholder="<?=$lang==='ar'?'صف المشكلة...':'Décrivez le problème…'?>"></textarea>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" onclick="closeSignal()" class="btn btn-ghost"><?=$lang==='ar'?'إلغاء':'Annuler'?></button>
          <button type="submit" style="background:#dc2626;color:#fff;border:none;border-radius:10px;padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer"><?=$lang==='ar'?'إرسال البلاغ':'Envoyer le signalement'?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast-cont" id="toast-cont">
<?php if($toast):?>
<div class="toast <?=$toast['type']==='ok'?'success':'error'?>" id="php-toast"><?=$toast['type']==='ok'?'✅':'❌'?> <span><?=htmlspecialchars($toast['msg'])?></span></div>
<?php endif;?>
</div>

<script>
const pt=document.getElementById('php-toast');
if(pt)setTimeout(()=>{pt.style.opacity='0';pt.style.transition='opacity .4s';setTimeout(()=>pt.remove(),400)},3500);

function toggleDay(cb,jour){
    const wrap=document.getElementById('times-'+jour);
    if(cb.checked){wrap.style.opacity='1';wrap.style.pointerEvents='auto';}
    else{wrap.style.opacity='.4';wrap.style.pointerEvents='none';}
}

const cm=document.getElementById('chat-messages');
if(cm)cm.scrollTop=cm.scrollHeight;

function toggleTechEditPrest(sid) {
  const v = document.getElementById('techpv-'+sid);
  const e = document.getElementById('techpe-'+sid);
  if (!v||!e) return;
  const editing = e.style.display !== 'none';
  v.style.display = editing ? 'flex' : 'none';
  e.style.display = editing ? 'none' : '';
}

// Opens prestation edit panel first, then shows confirm button
function openCompleteFlow(sid) {
  // Show the prestation edit panel
  const v = document.getElementById('techpv-'+sid);
  const e = document.getElementById('techpe-'+sid);
  if (v) v.style.display = 'none';
  if (e) {
    e.style.display = '';
    // Add confirm button inside the edit panel if not already there
    if (!document.getElementById('confirm-complete-'+sid)) {
      const btn = document.createElement('button');
      btn.id = 'confirm-complete-'+sid;
      btn.type = 'button';
      btn.className = 'btn btn-primary btn-sm';
      btn.style.cssText = 'margin-left:8px;background:var(--green);border-color:var(--green)';
      btn.innerHTML = '✓ <?=$lang==="ar"?"تأكيد وإنهاء":"Confirmer et terminer"?>';
      btn.onclick = function() {
        if (confirm('<?=$lang==="ar"?"الخدمات المنجزة محددة — تحديد الخدمة كمنتهية؟":"Prestations vérifiées — marquer ce service comme terminé ?"?>')) {
          document.getElementById('complete-form-'+sid).submit();
        }
      };
      // Append after the save/cancel buttons row
      const btnRow = e.querySelector('div[style*="display:flex;gap:8px"]') || e.querySelector('div:last-child');
      if (btnRow) btnRow.appendChild(btn);
      else e.appendChild(btn);
    }
    // Scroll to the edit panel
    e.scrollIntoView({behavior:'smooth', block:'nearest'});
  }
}
function openAddPrest(displayName, canonicalName, catalogueId) {
  document.getElementById('add-prest-name').textContent = displayName;
  document.getElementById('add-prest-nom').value = canonicalName;
  document.getElementById('add-prest-id-catalogue').value = catalogueId || '';
  document.getElementById('add-prest-prix').value = '';
  const form = document.getElementById('add-prest-form');
  form.style.display = '';
  form.scrollIntoView({behavior:'smooth', block:'nearest'});
}

function techToggleQty(cb, sid, pid) {
  const qw = document.getElementById('techqw-'+sid+'-'+pid);
  const lbl = cb.closest('label');
  if (qw) qw.style.display = cb.checked ? 'flex' : 'none';
  if (lbl) {
    lbl.style.background = cb.checked ? 'rgba(6,182,212,.06)' : 'var(--bg)';
    lbl.style.borderColor = cb.checked ? 'rgba(6,182,212,.5)' : 'var(--border)';
  }
}
function techQty(sid, pid, delta, minQty = 1) {
  const inp = document.getElementById('techqi-'+sid+'-'+pid);
  const val = document.getElementById('techqv-'+sid+'-'+pid);
  if (!inp||!val) return;
  let q = Math.max(parseInt(minQty||1), parseInt(inp.value||1)+delta);
  inp.value = q; val.textContent = q;
}
function toggleEditPrestation(id) {
  const view = document.getElementById('pview-'+id);
  const edit = document.getElementById('pedit-'+id);
  if (!view||!edit) return;
  const isEditing = edit.style.display !== 'none';
  view.style.display = isEditing ? '' : 'none';
  edit.style.display = isEditing ? 'none' : '';
}

function openSignal(uid, name, btn) {
  document.getElementById('signal-uid').value = uid;
  document.getElementById('signal-desc').textContent = '<?=$lang==="ar"?"الإبلاغ عن:":"Signaler :"?> ' + name;
  const m = document.getElementById('signal-modal');
  m.style.opacity='1'; m.style.pointerEvents='all';
}
function closeSignal() {
  const m = document.getElementById('signal-modal');
  m.style.opacity='0'; m.style.pointerEvents='none';
}
document.getElementById('signal-modal').addEventListener('click', function(e){ if(e.target===this) closeSignal(); });

// Geolocation
let geoInterval=null;
let geoActive=<?=($tech['localisation_active']??0)?'true':'false'?>;

function sendLocation(active){
  if(!navigator.geolocation){alert('Géolocalisation non supportée.');return;}
  navigator.geolocation.getCurrentPosition(pos=>{
    fetch('actions/handle_location.php',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({lat:pos.coords.latitude,lng:pos.coords.longitude,active:active?1:0})
    }).then(r=>r.json()).then(d=>{
      const btn=document.getElementById('geo-btn');
      if(active){
        btn.style.background='var(--green)';btn.style.color='#fff';
        btn.style.borderColor='var(--green)';btn.textContent='📍 En ligne';
      } else {
        btn.style.background='var(--bg)';btn.style.color='var(--muted)';
        btn.style.borderColor='var(--border)';btn.textContent='📍 Localisation';
      }
    });
  },err=>console.warn('Geo error:',err));
}

function toggleGeo(){
  geoActive=!geoActive;
  sendLocation(geoActive);
  if(geoActive){
    geoInterval=setInterval(()=>sendLocation(true),60000); // update every 60s
  } else {
    if(geoInterval)clearInterval(geoInterval);
  }
}

function toggleTechProfileEdit(showEdit){
  const summary=document.getElementById('tech-profile-summary');
  const form=document.getElementById('tech-profile-form');
  if(!form||!summary) return;
  summary.style.display = showEdit ? 'none' : 'block';
  form.style.display = showEdit ? 'block' : 'none';
  window.scrollTo({top:0, behavior:'smooth'});
}

// Auto-start if was active
if(geoActive){
  sendLocation(true);
  geoInterval=setInterval(()=>sendLocation(true),60000);
}
</script>
<script>
window.KhidmaRT = {
  role: 'technicien',
  base: '',
  myId: <?=$user_id?>,
  me: { initials: '<?=ini($tech["nom"],$tech["prenom"])?>' },
  seed: {
    notif_id:   <?=(int)$seed_notif_id?>,
    msg_id:     <?=(int)$seed_msg_id?>,
    service_id: <?=(int)$seed_svc_id?>,
  }
};
</script>
<script src="js/khidma-realtime.js"></script>
<script src="js/khidma-pwa.js" defer></script>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<nav class="mob-nav">
  <a href="?tab=dashboard" class="mob-tab <?=$active_tab==='dashboard'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    <?=$lang==='ar'?'الرئيسية':'Dashboard'?>
  </a>
  <a href="?tab=demandes" class="mob-tab <?=$active_tab==='demandes'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <?php if($new_count>0):?><span class="mob-badge nav-badge-demandes"><?=$new_count?></span><?php endif;?>
    <?=$lang==='ar'?'الطلبات':'Demandes'?>
  </a>
  <a href="?tab=messages" class="mob-tab <?=$active_tab==='messages'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <?php if($unread_total>0):?><span class="mob-badge nav-badge-msg"><?=$unread_total?></span><?php endif;?>
    <?=$lang==='ar'?'الرسائل':'Messages'?>
  </a>
  <a href="?tab=notifications" class="mob-tab <?=$active_tab==='notifications'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <?php if($unread_notif_count>0):?><span class="mob-badge nav-badge-notif"><?=$unread_notif_count?></span><?php endif;?>
    <?=$lang==='ar'?'الإشعارات':'Notifs'?>
  </a>
  <a href="?tab=profil" class="mob-tab <?=$active_tab==='profil'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <?=$lang==='ar'?'الملف':'Profil'?>
  </a>
</nav>

<script>
function openSidebar(){
  document.querySelector('.sidebar').classList.add('open');
  document.getElementById('sidebar-overlay').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeSidebar(){
  document.querySelector('.sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('open');
  document.body.style.overflow='';
}
</script>

</body>
</html>