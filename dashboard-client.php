<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/lang.php';
require_once 'includes/services.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$lang    = get_lang();
$stmt    = $pdo->prepare("SELECT u.*, c.id_client FROM utilisateur u JOIN client c ON c.id_user = u.id_user WHERE u.id_user = ?");
$stmt->execute([$user_id]); $client = $stmt->fetch();
if (!$client) { header("Location: login.php"); exit(); }
if (($client['statut_compte'] ?? 'actif') === 'suspendu') {
    session_destroy();
    $r = $client['raison_suspension'] ? htmlspecialchars($client['raison_suspension']) : '';
    echo "<div style='font-family:sans-serif;padding:40px;max-width:500px;margin:60px auto;border:1px solid #fca5a5;border-radius:12px;background:#fef2f2'><h2 style='color:#dc2626;margin-bottom:10px'>".($lang==='ar'?'الحساب موقوف':'Compte suspendu')."</h2><p style='color:#6b7280'>".($lang==='ar'?'تم إيقاف حسابك.':'Votre compte a été suspendu.').($r?" : <strong>$r</strong>":'')."</p><a href='login.php' style='display:inline-block;margin-top:20px;padding:10px 20px;background:#7c3aed;color:#fff;border-radius:8px;text-decoration:none'>".($lang==='ar'?'رجوع':'Retour')."</a></div>";
    exit();
}
$client_id = $client['id_client'];

// RTL-safe number display
function money($n,$l){$f=number_format((float)$n,0,'',chr(32));return $l==='ar'?'<span dir="ltr" style="unicode-bidi:embed">'.$f.' دج</span>':$f.' DA';}

// Language switch
if (isset($_GET['lang'])) {
    $nl = $_GET['lang'] === 'ar' ? 'ar' : 'fr';
  $pdo->prepare("UPDATE utilisateur SET langue=? WHERE id_user=?")->execute([$nl, $user_id]);
    $_SESSION['user_lang'] = $nl;
    header("Location: ?tab=".($_GET['tab']??'accueil')); exit();
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'book') {
        $id_tech = intval($_POST['id_technicien']??0);
        $date    = $_POST['date_service']??date('Y-m-d');
        $desc    = trim($_POST['description']??'');
        $prix    = floatval($_POST['prix_estime']??0);
        if ($id_tech && $date) {
        $date_ts = strtotime($date);
        if ($date_ts === false) {
          $_SESSION['toast']=['msg'=>$lang==='ar'?'تاريخ غير صالح':'Date invalide','type'=>'err'];
          header('Location: ?tab=recherche'); exit;
        }
        if ($date_ts < strtotime(date('Y-m-d'))) {
          $_SESSION['toast']=['msg'=>$lang==='ar'?'لا يمكنك الحجز في تاريخ سابق':'Vous ne pouvez pas réserver à une date passée','type'=>'err'];
          header('Location: ?tab=recherche'); exit;
        }
        $day_fr = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'][date('l', $date_ts)] ?? 'Lundi';
        $avail = $pdo->prepare("SELECT id_disponibilite FROM disponibilite WHERE id_technicien=? AND jour=? LIMIT 1");
        $avail->execute([$id_tech, $day_fr]);
        if (!$avail->fetch()) {
          $_SESSION['toast']=['msg'=>$lang==='ar'?'هذا التقني غير متاح في هذا اليوم. اختر يوماً آخر.':'Ce technicien est indisponible ce jour-là. Choisissez une autre date.','type'=>'err'];
          header('Location: ?tab=recherche'); exit;
        }
            $busy = $pdo->prepare("SELECT id_service FROM service WHERE id_technicien=? AND statut_service='en cours' LIMIT 1");
            $busy->execute([$id_tech]);
            if ($busy->fetch()) {
                $_SESSION['toast']=['msg'=>$lang==='ar'?'هذا التقني في مهمة حالياً. حاول لاحقاً.':'Ce technicien est actuellement en intervention. Réessayez plus tard.','type'=>'err'];
                header('Location: ?tab=recherche'); exit;
            }
            $pdo->prepare("INSERT INTO service (id_client,id_technicien,date_service,statut_service,description,prix_estime) VALUES (?,?,?,'planifié',?,?)")
                ->execute([$client_id,$id_tech,$date,$desc,$prix]);
            $svc_id = $pdo->lastInsertId();
            if (!empty($_POST['prestations']) && is_array($_POST['prestations'])) {
                $ins = $pdo->prepare("INSERT IGNORE INTO service_prestation (id_service,id_prestation,quantite) VALUES (?,?,?)");
                foreach ($_POST['prestations'] as $pid) {
                    $ins->execute([$svc_id, intval($pid), max(1, intval($_POST['qty_'.intval($pid)] ?? 1))]);
                }
            }
            $tu = $pdo->prepare("SELECT u.id_user,u.nom,u.prenom,u.email,u.langue FROM technicien t JOIN utilisateur u ON u.id_user=t.id_user WHERE t.id_technicien=?");
            $tu->execute([$id_tech]); $trow=$tu->fetch();
            if ($trow) {
                $tl=$trow['langue']??'fr';
                notifier($pdo,$trow['id_user'],$tl==='ar'?'حجز جديد':'Nouvelle réservation',$tl==='ar'?'حجز جديد بتاريخ '.date('d/m/Y',strtotime($date)):'Un client a réservé votre service pour le '.date('d/m/Y',strtotime($date)),'dashboard-tech.php?tab=demandes');
                require_once 'includes/mailer.php';
                send_new_reservation($trow['email'],$trow['nom'],$trow['prenom'],$client['prenom'],$date,$desc);
            }
            log_action($pdo,$user_id,'book_service','Service #'.$svc_id.' tech #'.$id_tech);
            $_SESSION['toast']=['msg'=>$lang==='ar'?'تم إرسال الحجز ✓':'Réservation envoyée ✓','type'=>'ok'];
        }
    }
    if ($act === 'cancel' && isset($_POST['id_service'])) {
        $sid = intval($_POST['id_service']);
        try {
            $pdo->beginTransaction();
            $up = $pdo->prepare("UPDATE service SET statut_service='annulé',prix_final=NULL WHERE id_service=? AND id_client=? AND statut_service IN ('planifié','en cours')");
            $up->execute([$sid,$client_id]);
            if ($up->rowCount()===0) { $pdo->rollBack(); $_SESSION['toast']=['msg'=>$lang==='ar'?'تعذر الإلغاء':'Annulation impossible','type'=>'err']; }
            else {
                $pdo->prepare("DELETE FROM paiment WHERE id_service=?")->execute([$sid]);
                $pdo->prepare("DELETE FROM facture WHERE id_service=?")->execute([$sid]);
                $pdo->prepare("DELETE FROM avis WHERE id_service=? AND id_client=?")->execute([$sid,$client_id]);
                $tq=$pdo->prepare("SELECT t.id_user as tuid,u.langue FROM service s JOIN technicien t ON t.id_technicien=s.id_technicien JOIN utilisateur u ON u.id_user=t.id_user WHERE s.id_service=?");
                $tq->execute([$sid]); $tr=$tq->fetch();
                if ($tr) { $tl=$tr['langue']??'fr'; notifier($pdo,$tr['tuid'],$tl==='ar'?'حجز ملغى':'Réservation annulée',$tl==='ar'?'قام العميل بإلغاء الحجز رقم #'.$sid.'.':'Le client a annulé la réservation #'.$sid.'.','dashboard-tech.php?tab=demandes'); }
                log_action($pdo,$user_id,'cancel_service','Service #'.$sid);
                $_SESSION['toast']=['msg'=>$lang==='ar'?'تم إلغاء الحجز':'Réservation annulée','type'=>'err'];
                $pdo->commit();
            }
        } catch(Exception $e) { if($pdo->inTransaction())$pdo->rollBack(); $_SESSION['toast']=['msg'=>$lang==='ar'?'خطأ':'Erreur','type'=>'err']; }
    }
    if ($act==='update_service_prestations'&&isset($_POST['id_service'])) {
        $sid=intval($_POST['id_service']);
        $chk=$pdo->prepare("SELECT statut_service FROM service WHERE id_service=? AND id_client=?"); $chk->execute([$sid,$client_id]); $svc=$chk->fetch();
        if ($svc&&$svc['statut_service']==='planifié') {
            $pdo->prepare("DELETE FROM service_prestation WHERE id_service=?")->execute([$sid]);
            if (!empty($_POST['prestations'])&&is_array($_POST['prestations'])) { $ins=$pdo->prepare("INSERT INTO service_prestation (id_service,id_prestation,quantite) VALUES (?,?,?)"); foreach($_POST['prestations'] as $pid) $ins->execute([$sid,intval($pid),max(1,intval($_POST['qty_'.intval($pid)]??1))]); }
            $_SESSION['toast']=['msg'=>$lang==='ar'?'تم تحديث الخدمات ✓':'Prestations mises à jour ✓','type'=>'ok'];
        } else $_SESSION['toast']=['msg'=>$lang==='ar'?'تعذر التعديل':'Impossible de modifier','type'=>'err'];
    }
    if ($act==='rate') {
        $it=intval($_POST['id_technicien']??0); $is=intval($_POST['id_service']??0); $nt=intval($_POST['note']??5); $cm=trim($_POST['commentaire']??'');
        if ($it&&$is&&$nt>=1&&$nt<=5) {
            $cs=$pdo->prepare("SELECT id_service FROM service WHERE id_service=? AND id_client=? AND statut_service='terminé'"); $cs->execute([$is,$client_id]);
            if ($cs->fetch()) { $ck=$pdo->prepare("SELECT id_avis FROM avis WHERE id_client=? AND id_service=?"); $ck->execute([$client_id,$is]);
                if (!$ck->fetch()) { $pdo->prepare("INSERT INTO avis (id_client,id_technicien,id_service,note,commentaire,date_avis,visible) VALUES (?,?,?,?,?,CURDATE(),1)")->execute([$client_id,$it,$is,$nt,$cm]); log_action($pdo,$user_id,'rate_tech','Tech #'.$it.' note '.$nt); $_SESSION['toast']=['msg'=>$lang==='ar'?'تم إرسال التقييم ✓':'Avis envoyé ✓','type'=>'ok']; }
                else $_SESSION['toast']=['msg'=>$lang==='ar'?'قُيِّمت مسبقاً':'Déjà évalué','type'=>'err'];
            } else $_SESSION['toast']=['msg'=>$lang==='ar'?'الخدمة غير موجودة':'Service introuvable','type'=>'err'];
        }
    }
    if ($act==='send_message'&&!empty($_POST['id_destinataire'])&&!empty($_POST['contenu'])) {
        $pdo->prepare("INSERT INTO message (id_message,id_expediteur,id_destinataire,contenu,data_envoi,lu) SELECT COALESCE(MAX(id_message),0)+1, ?, ?, ?, NOW(), 0 FROM message")->execute([$user_id,$_POST['id_destinataire'],$_POST['contenu']]);
    }
    if ($act==='mark_all_read') { $pdo->prepare("UPDATE notification SET lu=1 WHERE id_user=?")->execute([$user_id]); $_SESSION['toast']=['msg'=>$lang==='ar'?'تم تحديد الكل كمقروء':'Tout marqué lu','type'=>'ok']; }
    if ($act==='update_profile') {
        $nl=in_array($_POST['langue']??'fr',['fr','ar'])?$_POST['langue']:'fr';
        $pdo->prepare("UPDATE utilisateur SET nom=?,prenom=?,email=?,telephone=?,ville=?,langue=? WHERE id_user=?")->execute([$_POST['nom']??$client['nom'],$_POST['prenom']??$client['prenom'],$_POST['email']??$client['email'],$_POST['telephone']??$client['telephone'],$_POST['ville']??$client['ville'],$nl,$user_id]);
        if (!empty($_POST['new_password'])) $pdo->prepare("UPDATE utilisateur SET mot_de_passe=? WHERE id_user=?")->execute([password_hash($_POST['new_password'],PASSWORD_DEFAULT),$user_id]);
        $_SESSION['user_lang']=$nl; $lang=$nl; log_action($pdo,$user_id,'update_profile','');
        $_SESSION['toast']=['msg'=>$lang==='ar'?'تم تحديث الملف ✓':'Profil mis à jour ✓','type'=>'ok'];
    }
    $tb=$_POST['tab']??'accueil'; $ex=isset($_POST['with_redirect'])?'&with='.$_POST['with_redirect']:'';
    header("Location: dashboard-client.php?tab=$tb$ex"); exit();
}

$active_tab=$_GET['tab']??'accueil';
$dir=$lang==='ar'?'rtl':'ltr';

// Reservations
$reservations=$pdo->prepare("SELECT s.*,t.specialité,t.id_technicien,u.nom as tn,u.prenom as tp,u.id_user as tuid,a.id_avis,f.id_facture,p.id_paiment,p.statut_paiment FROM service s JOIN technicien t ON s.id_technicien=t.id_technicien JOIN utilisateur u ON t.id_user=u.id_user LEFT JOIN avis a ON a.id_service=s.id_service AND a.id_client=? LEFT JOIN facture f ON f.id_facture=(SELECT MAX(f2.id_facture) FROM facture f2 WHERE f2.id_service=s.id_service) LEFT JOIN paiment p ON p.id_service=s.id_service WHERE s.id_client=? ORDER BY FIELD(s.statut_service,'planifié','en cours','terminé','annulé'),s.id_service DESC");
$reservations->execute([$client_id,$client_id]); $reservations=$reservations->fetchAll();
$cli_res_ids=array_values(array_column($reservations,'id_service'));
$cli_res_prests=[];
if (!empty($cli_res_ids)) {
    $in=implode(',',array_fill(0,count($cli_res_ids),'?'));
    $sp=$pdo->prepare("SELECT sp.id_service,sp.id_prestation,sp.quantite,pr.nom,pr.prix_unitaire,pr.unite,COALESCE(pc.nom_ar,'') as nom_ar FROM service_prestation sp JOIN prestation pr ON pr.id_prestation=sp.id_prestation LEFT JOIN prestation_catalogue pc ON pc.id_catalogue=pr.id_catalogue WHERE sp.id_service IN ($in)");
    $sp->execute($cli_res_ids);
    foreach($sp->fetchAll() as $row) $cli_res_prests[(int)$row['id_service']][]=$row;
}
$total_res=count($reservations);
$done_res=count(array_filter($reservations,fn($r)=>$r['statut_service']==='terminé'));
$active_res=count(array_filter($reservations,fn($r)=>in_array($r['statut_service'],['planifié','en cours'])));
$avis_count=count(array_filter($reservations,fn($r)=>$r['id_avis']));
$today_fr = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'][date('l')] ?? 'Lundi';
$all_techs_q=$pdo->prepare("SELECT t.*,u.nom,u.prenom,u.ville,u.id_user as tuid,COALESCE(AVG(a.note),0) as avg_note,COUNT(DISTINCT a.id_avis) as nb_avis,MAX(CASE WHEN d.id_disponibilite IS NOT NULL THEN 1 ELSE 0 END) as available_now FROM technicien t JOIN utilisateur u ON t.id_user=u.id_user LEFT JOIN avis a ON a.id_technicien=t.id_technicien LEFT JOIN disponibilite d ON d.id_technicien=t.id_technicien AND d.jour=? AND CURTIME() BETWEEN d.heure_debut AND d.heure_fin WHERE t.verifie=1 AND u.statut_compte='actif' GROUP BY t.id_technicien ORDER BY avg_note DESC");
$all_techs_q->execute([$today_fr]);
$all_techs=$all_techs_q->fetchAll();

// Tech profile
$sel_tech_id=intval($_GET['tech']??0); $sel_tech=null; $prestations=[]; $avis_tech=[];
if ($sel_tech_id) {
  $st=$pdo->prepare("SELECT t.*,u.nom,u.prenom,u.ville,u.telephone,u.email,u.id_user as tuid,COALESCE(AVG(a.note),0) as avg_note,COUNT(DISTINCT a.id_avis) as nb_avis,MAX(CASE WHEN d.id_disponibilite IS NOT NULL THEN 1 ELSE 0 END) as available_now,MAX(CASE WHEN s.id_service IS NOT NULL THEN 1 ELSE 0 END) as en_cours FROM technicien t JOIN utilisateur u ON t.id_user=u.id_user LEFT JOIN avis a ON a.id_technicien=t.id_technicien LEFT JOIN disponibilite d ON d.id_technicien=t.id_technicien AND d.jour=? AND CURTIME() BETWEEN d.heure_debut AND d.heure_fin LEFT JOIN service s ON s.id_technicien=t.id_technicien AND s.statut_service='en cours' WHERE t.id_technicien=? GROUP BY t.id_technicien");
  $st->execute([$today_fr,$sel_tech_id]); $sel_tech=$st->fetch();
    $pr=$pdo->prepare("SELECT p.*,COALESCE(pc.nom_ar,'') as nom_ar,CASE WHEN p.id_catalogue IS NULL THEN 1 ELSE 0 END as is_custom FROM prestation p LEFT JOIN prestation_catalogue pc ON pc.id_catalogue=p.id_catalogue WHERE p.id_technicien=? AND p.actif=1 ORDER BY p.id_catalogue IS NULL DESC,p.id_prestation");
    $pr->execute([$sel_tech_id]); $prestations=$pr->fetchAll();
    $av=$pdo->prepare("SELECT a.*,u.nom,u.prenom FROM avis a JOIN utilisateur u ON u.id_user=(SELECT id_user FROM client WHERE id_client=a.id_client LIMIT 1) WHERE a.id_technicien=? AND a.visible=1 ORDER BY a.date_avis DESC LIMIT 5");
    $av->execute([$sel_tech_id]); $avis_tech=$av->fetchAll();
}

// Litiges
$litiges=$pdo->prepare("SELECT l.*,s.description as svc_desc,s.date_service,u.nom as tn,u.prenom as tp FROM litige l JOIN service s ON s.id_service=l.id_service JOIN technicien t ON t.id_technicien=l.id_technicien JOIN utilisateur u ON u.id_user=t.id_user WHERE l.id_client=? ORDER BY l.date_overture DESC");
$litiges->execute([$client_id]); $litiges=$litiges->fetchAll();
$eligible=$pdo->prepare("SELECT s.*,u.nom as tn,u.prenom as tp,t.specialité FROM service s JOIN technicien t ON t.id_technicien=s.id_technicien JOIN utilisateur u ON u.id_user=t.id_user WHERE s.id_client=? AND s.statut_service='terminé' AND s.id_service NOT IN (SELECT id_service FROM litige WHERE id_client=?) ORDER BY s.date_service DESC");
$eligible->execute([$client_id,$client_id]); $eligible=$eligible->fetchAll();

// Messages
$convs=$pdo->prepare("SELECT DISTINCT CASE WHEN m.id_expediteur=? THEN m.id_destinataire ELSE m.id_expediteur END as other_id,u.nom,u.prenom,u.role,MAX(m.data_envoi) as last_date,SUM(CASE WHEN m.lu=0 AND m.id_destinataire=? THEN 1 ELSE 0 END) as unread FROM message m JOIN utilisateur u ON u.id_user=CASE WHEN m.id_expediteur=? THEN m.id_destinataire ELSE m.id_expediteur END WHERE m.id_expediteur=? OR m.id_destinataire=? GROUP BY other_id ORDER BY last_date DESC");
$convs->execute([$user_id,$user_id,$user_id,$user_id,$user_id]); $convs=$convs->fetchAll();
$unread_msg=array_sum(array_column($convs,'unread'));
$sel_uid=$_GET['with']??($convs[0]['other_id']??null);
$chat_msgs=[]; $chat_user=null;
if ($active_tab==='messages'&&$sel_uid) {
    $cu=$pdo->prepare("SELECT * FROM utilisateur WHERE id_user=?"); $cu->execute([$sel_uid]); $chat_user=$cu->fetch();
    $cm=$pdo->prepare("SELECT m.*,u.nom,u.prenom FROM message m JOIN utilisateur u ON u.id_user=m.id_expediteur WHERE (m.id_expediteur=? AND m.id_destinataire=?) OR (m.id_expediteur=? AND m.id_destinataire=?) ORDER BY m.data_envoi ASC,m.id_message ASC");
    $cm->execute([$user_id,$sel_uid,$sel_uid,$user_id]); $chat_msgs=$cm->fetchAll();
    $pdo->prepare("UPDATE message SET lu=1 WHERE id_destinataire=? AND id_expediteur=?")->execute([$user_id,$sel_uid]);
}

// Notifications
$all_notifs_q=$pdo->prepare("SELECT * FROM notification WHERE id_user=? ORDER BY date_notif DESC LIMIT 50");
$all_notifs_q->execute([$user_id]); $all_notifs=$all_notifs_q->fetchAll();
$notif_count=count(array_filter($all_notifs,fn($n)=>!$n['lu']));
if ($active_tab==='notifications') { $pdo->prepare("UPDATE notification SET lu=1 WHERE id_user=?")->execute([$user_id]); $notif_count=0; }
if (isset($_GET['reset'])&&$_GET['reset']=='1') $_SESSION['toast']=['msg'=>$lang==='ar'?'🔒 تم تحديث كلمة المرور !':'🔒 Mot de passe mis à jour !','type'=>'ok'];
$toast=$_SESSION['toast']??null; unset($_SESSION['toast']);
$_q=$pdo->prepare("SELECT COALESCE(MAX(id_notif),0) FROM notification WHERE id_user=?"); $_q->execute([$user_id]); $seed_notif_id=$_q->fetchColumn();
$_q=$pdo->prepare("SELECT COALESCE(MAX(id_message),0) FROM message WHERE id_destinataire=?"); $_q->execute([$user_id]); $seed_msg_id=$_q->fetchColumn();

// Search
$sw=trim($_GET['wilaya']??''); $sc=trim($_GET['cat']??''); $ss=$_GET['sort']??'prix_asc';
$sw_norm = wilaya_norm($sw);
$si=array_map('intval',(array)($_GET['items']??[]));
$catalogue=[]; $search_results=[];
if ($active_tab==='recherche'&&!$sel_tech_id) {
    if ($sw&&$sc) { $cq=$pdo->prepare("SELECT id_catalogue,nom,COALESCE(nom_ar,'') as nom_ar FROM prestation_catalogue WHERE specialite=? ORDER BY nom"); $cq->execute([$sc]); $catalogue=$cq->fetchAll(); }
    if ($sw&&$sc&&!empty($si)) {
        $in=implode(',',array_fill(0,count($si),'?'));
        $cnq=$pdo->prepare("SELECT nom,COALESCE(nom_ar,'') as nom_ar FROM prestation_catalogue WHERE id_catalogue IN ($in)"); $cnq->execute($si);
        $crows=$cnq->fetchAll(); $cnames=array_column($crows,'nom');
        if (!empty($cnames)) {
            $ord=match($ss){'prix_desc'=>"p.prix_unitaire DESC",'note'=>"avg_note DESC,p.prix_unitaire ASC",default=>"p.prix_unitaire ASC"};
            $nin=implode(',',array_fill(0,count($cnames),'?'));
            $srq=$pdo->prepare("SELECT p.id_prestation,p.nom,p.description,p.prix_unitaire,p.unite,COALESCE(pc.nom_ar,'') as nom_ar,t.id_technicien,t.specialité,t.experience,t.verifie,u.nom AS unom,u.prenom AS uprenom,u.ville,u.id_user AS tuid,COALESCE(AVG(a.note),0) AS avg_note,COUNT(DISTINCT a.id_avis) AS nb_avis,MAX(CASE WHEN d.id_disponibilite IS NOT NULL THEN 1 ELSE 0 END) as available_now FROM prestation p JOIN technicien t ON t.id_technicien=p.id_technicien JOIN utilisateur u ON u.id_user=t.id_user LEFT JOIN avis a ON a.id_technicien=t.id_technicien LEFT JOIN disponibilite d ON d.id_technicien=t.id_technicien AND d.jour=? AND CURTIME() BETWEEN d.heure_debut AND d.heure_fin LEFT JOIN prestation_catalogue pc ON pc.id_catalogue=p.id_catalogue WHERE t.verifie=1 AND u.statut_compte='actif' AND t.specialité=? AND p.actif=1 AND p.nom IN ($nin) GROUP BY p.id_prestation ORDER BY $ord");
            $srq->execute(array_merge([$today_fr,$sc],$cnames));
            foreach($srq->fetchAll() as $row) {
              if ($sw_norm !== '' && wilaya_norm((string)($row['ville'] ?? '')) !== $sw_norm) continue;
                $tid=$row['id_technicien'];
              if (!isset($search_results[$tid])) { $search_results[$tid]=['tech'=>['id'=>$tid,'tuid'=>$row['tuid'],'nom'=>$row['unom'],'prenom'=>$row['uprenom'],'ville'=>$row['ville'],'specialite'=>$row['specialité'],'exp'=>$row['experience'],'avg_note'=>round($row['avg_note'],1),'nb_avis'=>$row['nb_avis'],'verifie'=>$row['verifie'],'available_now'=>(bool)$row['available_now']],'prestations'=>[]]; }
                $search_results[$tid]['prestations'][]=['id'=>$row['id_prestation'],'nom'=>$row['nom'],'nom_ar'=>$row['nom_ar'],'desc'=>$row['description'],'prix'=>$row['prix_unitaire'],'unite'=>$row['unite']];
            }
            foreach($search_results as $tid=>&$entry) { $sq=$pdo->prepare("SELECT id_service FROM service WHERE id_technicien=? AND statut_service='en cours' LIMIT 1"); $sq->execute([$tid]); $entry['en_cours']=(bool)$sq->fetch(); } unset($entry);
        }
    }
}

function ini($n,$p){return strtoupper(substr($p??'',0,1).substr($n??'',0,1));}
function stars($n){$n=round($n);$s='';for($i=1;$i<=5;$i++)$s.=$i<=$n?'★':'☆';return $s;}
function ul($u,$l){$u=strtolower(trim((string)$u));if($l==='ar'){$m=['heure'=>'ساعة','jour'=>'يوم','unité'=>'وحدة','intervention'=>'تدخل'];return $m[$u]??$u;}return $u?:'u';}
function pn($p,$l){return($l==='ar'&&!empty($p['nom_ar']))?$p['nom_ar']:$p['nom'];}
function chip($s){global $lang;$ar=$lang==='ar';$m=['planifié'=>['chip-blue',$ar?'مجدول':'Planifié'],'en cours'=>['chip-amber',$ar?'جارٍ':'En cours'],'terminé'=>['chip-green',$ar?'منتهي':'Terminé'],'annulé'=>['chip-red',$ar?'ملغى':'Annulé'],'ouvert'=>['chip-red',$ar?'مفتوح':'Ouvert'],'resolu'=>['chip-green',$ar?'محلول':'Résolu'],'fermé'=>['chip-amber',$ar?'مغلق':'Fermé']];[$c,$l]=$m[strtolower($s??'')]??['chip-amber',ucfirst($s)];return "<span class=\"chip $c\">$l</span>";}
$T=$lang==='ar'?['accueil'=>'الرئيسية','recherche'=>'بحث عن تقني','reservations'=>'حجوزاتي','messages'=>'الرسائل','profil'=>'ملفي الشخصي','litiges'=>'النزاعات','notifications'=>'الإشعارات']:['accueil'=>'Accueil','recherche'=>'Rechercher un technicien','reservations'=>'Mes réservations','messages'=>'Messages','profil'=>'Mon profil','litiges'=>'Mes litiges','notifications'=>'Notifications'];
?>
<!DOCTYPE html>
<html lang="<?=$lang?>" dir="<?=$dir?>">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<link rel="manifest" href="manifest.json"><meta name="theme-color" content="#7c3aed">
<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Khidma DZ"><link rel="apple-touch-icon" href="icons/icon-152.png">
<title><?=$T[$active_tab]??($lang==='ar'?'لوحة العميل':'Espace Client')?> — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
:root{--pr:#7c3aed;--prd:#6d28d9;--prl:rgba(124,58,237,.09);--sec:#4f46e5;--bg:#faf8ff;--sf:#fff;--sf2:#f5f3ff;--bd:#ede9fe;--tx:#1f2933;--mu:#6b7280;--gr:#16a34a;--grl:rgba(22,163,74,.1);--rd:#ef4444;--rdl:rgba(239,68,68,.1);--am:#f59e0b;--aml:rgba(245,158,11,.1);--bl:#1a73e8;--bll:rgba(26,115,232,.1);--sw:250px;--ra:12px;--sh:0 4px 16px rgba(15,23,42,.07);--shl:0 10px 30px rgba(15,23,42,.12);}
*{margin:0;padding:0;box-sizing:border-box;}html,body{height:100%;overflow:hidden;}body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);display:flex;width:100%;}
[dir=rtl] body,[dir=rtl] button,[dir=rtl] input,[dir=rtl] textarea,[dir=rtl] select{font-family:'Cairo',sans-serif;}
.sb{width:var(--sw);background:var(--sf);border-right:1px solid var(--bd);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;bottom:0;z-index:50;box-shadow:2px 0 12px rgba(15,23,42,.04);}
[dir=rtl] .sb{border-right:none;border-left:1px solid var(--bd);left:auto;right:0;}
.logo{padding:22px 20px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--bd);}
.logo-i{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--pr),var(--sec));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:13px;color:#fff;flex-shrink:0;}
.logo-t{font-family:'Syne',sans-serif;font-weight:700;font-size:16px;}.logo-t span{color:var(--pr);}
.nav{flex:1;padding:14px 10px;display:flex;flex-direction:column;gap:2px;overflow-y:auto;}
.ns{font-size:10px;font-weight:700;color:var(--mu);letter-spacing:1.5px;text-transform:uppercase;padding:10px 10px 4px;}
.ni{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:10px;cursor:pointer;transition:all .18s;color:var(--mu);font-size:13.5px;font-weight:500;text-decoration:none;position:relative;}
.ni:hover{background:var(--sf2);color:var(--tx);}
.ni.active{background:var(--prl);color:var(--pr);font-weight:600;}
.ni.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:18px;background:var(--pr);border-radius:0 3px 3px 0;}
[dir=rtl] .ni.active::before{left:auto;right:0;border-radius:3px 0 0 3px;}
.ni svg{width:17px;height:17px;flex-shrink:0;}
.bdg{margin-left:auto;background:var(--rd);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;line-height:1.4;}
[dir=rtl] .bdg{margin-left:0;margin-right:auto;}
.sbf{padding:14px 10px;border-top:1px solid var(--bd);}
.uch{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;cursor:pointer;transition:background .18s;text-decoration:none;color:inherit;}
.uch:hover{background:var(--sf2);}
.av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--pr),var(--sec));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:13px;color:#fff;flex-shrink:0;}
.av-n{font-size:13px;font-weight:600;}.av-r{font-size:11px;color:var(--pr);font-weight:500;}
.main{margin-left:var(--sw);flex:1;display:flex;flex-direction:column;height:100vh;overflow:hidden;}
[dir=rtl] .main{margin-left:0;margin-right:var(--sw);}
.tb{padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--bd);background:var(--sf);position:sticky;top:0;z-index:40;}
.pt{font-family:'Syne',sans-serif;font-weight:700;font-size:19px;}
.tbr{display:flex;align-items:center;gap:10px;}
[dir=rtl] .tbr{flex-direction:row-reverse;}
.ib{width:36px;height:36px;border-radius:10px;background:var(--sf2);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;text-decoration:none;color:inherit;}
.nd{position:absolute;top:7px;right:7px;width:7px;height:7px;background:var(--rd);border-radius:50%;border:1.5px solid var(--sf);}
[dir=rtl] .nd{right:auto;left:7px;}
.ct{flex:1;padding:26px 28px;overflow-y:auto;min-height:0;}.ct.msgs{padding:0;overflow:hidden;display:flex;flex-direction:column;}
.sg{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.st{background:var(--sf);border:1px solid var(--bd);border-radius:var(--ra);padding:18px 20px;position:relative;box-shadow:var(--sh);}
.sl{font-size:11px;color:var(--mu);font-weight:600;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;}
.sv{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;line-height:1;}
.si{position:absolute;right:14px;top:14px;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;}
[dir=rtl] .si{right:auto;left:14px;}
.card{background:var(--sf);border:1px solid var(--bd);border-radius:var(--ra);overflow:hidden;box-shadow:var(--sh);}
.ch{padding:14px 18px;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between;background:var(--sf2);}
.ct2{font-family:'Syne',sans-serif;font-weight:700;font-size:14px;}
.twc{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;}
.stt{font-family:'Syne',sans-serif;font-weight:700;font-size:15px;margin-bottom:12px;}
.btn{padding:7px 14px;border-radius:999px;font-size:12.5px;font-weight:600;cursor:pointer;border:none;transition:all .18s;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
[dir=rtl] .btn{font-family:'Cairo',sans-serif;}
.bp{background:var(--pr);color:#fff;box-shadow:0 4px 12px rgba(124,58,237,.25);}.bp:hover{background:var(--prd);transform:translateY(-1px);}
.bg{background:var(--sf2);color:var(--tx);border:1px solid var(--bd);}.bg:hover{background:var(--bd);}
.bgg{background:var(--grl);color:var(--gr);border:1px solid rgba(22,163,74,.25);}
.brr{background:var(--rdl);color:var(--rd);border:1px solid rgba(239,68,68,.25);}
.bsm{padding:5px 11px;font-size:12px;}
table{width:100%;border-collapse:collapse;}
thead tr{border-bottom:1px solid var(--bd);background:var(--sf2);}
thead th{padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.7px;}
[dir=rtl] thead th{text-align:right;}
tbody tr{border-bottom:1px solid var(--bd);transition:background .12s;}tbody tr:last-child{border-bottom:none;}tbody tr:hover{background:var(--sf2);}
td{padding:11px 14px;font-size:13.5px;}[dir=rtl] td{text-align:right;}
.uc{display:flex;align-items:center;gap:9px;}
.mv{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;border:1px solid var(--bd);background:var(--sf2);}
.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;}
.chip::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
.chip-green{background:var(--grl);color:var(--gr);}.chip-amber{background:var(--aml);color:var(--am);}.chip-red{background:var(--rdl);color:var(--rd);}.chip-blue{background:var(--bll);color:var(--bl);}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fgg{display:flex;flex-direction:column;gap:5px;}.fgg.full{grid-column:1/-1;}
.fl{font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.6px;}
.fi{background:var(--sf2);border:1px solid var(--bd);border-radius:9px;padding:9px 12px;color:var(--tx);font-size:13.5px;font-family:'DM Sans',sans-serif;outline:none;transition:border .18s;width:100%;}
[dir=rtl] .fi{font-family:'Cairo',sans-serif;text-align:right;}
.fi:focus{border-color:var(--pr);box-shadow:0 0 0 3px rgba(124,58,237,.12);}
textarea.fi{resize:vertical;min-height:70px;}
.ml{display:flex;flex:1;min-height:0;background:var(--sf);border:1px solid var(--bd);border-radius:var(--ra);overflow:hidden;box-shadow:var(--sh);}
.mll{width:260px;flex-shrink:0;border-right:1px solid var(--bd);overflow-y:auto;display:flex;flex-direction:column;}
[dir=rtl] .mll{border-right:none;border-left:1px solid var(--bd);}
.ms{padding:12px 14px;border-bottom:1px solid var(--bd);}
.ms input{width:100%;background:var(--sf2);border:1px solid var(--bd);border-radius:10px;padding:7px 11px;color:var(--tx);font-size:13px;outline:none;}
.ms input:focus{border-color:var(--pr);}
.mi{padding:12px 14px;border-bottom:1px solid var(--bd);cursor:pointer;display:flex;gap:9px;align-items:flex-start;text-decoration:none;color:inherit;transition:background .12s;}
.mi:hover{background:var(--sf2);}.mi.active{background:var(--prl);border-left:3px solid var(--pr);}
[dir=rtl] .mi.active{border-left:none;border-right:3px solid var(--pr);}
.ca{flex:1;display:flex;flex-direction:column;background:var(--bg);overflow:hidden;min-width:0;}
.cah{padding:14px 18px;border-bottom:1px solid var(--bd);background:var(--sf);display:flex;align-items:center;gap:10px;}
.cam{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;gap:14px;min-height:0;}
.mbw{display:flex;gap:10px;align-items:flex-end;}.mbw.me{flex-direction:row-reverse;}
.bi{display:flex;flex-direction:column;min-width:0;max-width:68%;}.mbw.me .bi{align-items:flex-end;}
.bu{padding:11px 15px;font-size:13.5px;line-height:1.55;word-break:break-word;overflow-wrap:break-word;white-space:pre-wrap;}
.bu.other{background:#fff;border:1px solid var(--bd);border-radius:18px 18px 18px 4px;color:var(--tx);}
.bu.me{background:linear-gradient(135deg,var(--pr),var(--prd));color:#fff;border-radius:18px 18px 4px 18px;}
.bt{font-size:10px;color:var(--mu);margin-top:2px;}.mbw.me .bt{text-align:right;}
.cai{padding:12px 16px;border-top:1px solid var(--bd);background:var(--sf);display:flex;align-items:flex-end;gap:8px;flex-shrink:0;}
.cai form{display:contents;}
.cai textarea{flex:1;background:var(--sf2);border:1px solid var(--bd);border-radius:10px;padding:9px 14px;color:var(--tx);font-size:13.5px;font-family:'DM Sans',sans-serif;outline:none;resize:none;min-height:44px;max-height:120px;overflow-y:auto;line-height:1.5;}
[dir=rtl] .cai textarea{font-family:'Cairo',sans-serif;text-align:right;}
.cai textarea:focus{border-color:var(--pr);}
.sb2{padding:9px 16px;background:var(--pr);color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:13px;align-self:flex-end;flex-shrink:0;}.sb2:hover{background:var(--prd);}
.ph{background:var(--sf);border:1px solid var(--bd);border-radius:var(--ra);padding:28px;display:flex;gap:22px;align-items:flex-start;margin-bottom:18px;box-shadow:var(--sh);direction:ltr;text-align:left;}
.ph-main{display:flex;flex-direction:column;align-items:flex-start;min-width:0;}
.pav{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--pr),var(--sec));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:#fff;border:3px solid var(--pr);flex-shrink:0;}
.pnm{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;margin-bottom:2px;}
.psp{color:var(--pr);font-size:13px;font-weight:600;margin-bottom:10px;}
.nit{padding:14px 18px;border-bottom:1px solid var(--bd);display:flex;gap:12px;align-items:flex-start;}
.nit.unread{background:var(--prl);}
.mo{position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:blur(4px);z-index:300;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s;}
.mo.open{opacity:1;pointer-events:all;}
.mox{background:#fff;border-radius:20px;width:500px;max-width:95vw;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.15);border:1px solid var(--bd);}
.moh{padding:20px 24px 16px;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between;background:var(--bg);}
.mot{font-family:'Syne',sans-serif;font-weight:700;font-size:16px;}
.mcb{width:28px;height:28px;border-radius:50%;background:var(--bd);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;}
.mob{padding:20px 24px;}
.tc{position:fixed;bottom:20px;right:20px;display:flex;flex-direction:column;gap:8px;z-index:400;}
[dir=rtl] .tc{right:auto;left:20px;}
.toast{background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:11px 16px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:9px;box-shadow:var(--shl);animation:tIn .25s ease;min-width:220px;}
@keyframes tIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
[dir=rtl] @keyframes tIn{from{opacity:0;transform:translateX(-16px)}to{opacity:1;transform:translateX(0)}}
.toast.ok{border-left:3px solid var(--gr);}[dir=rtl] .toast.ok{border-left:none;border-right:3px solid var(--gr);}
.toast.err{border-left:3px solid var(--rd);}[dir=rtl] .toast.err{border-left:none;border-right:3px solid var(--rd);}
::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-thumb{background:var(--bd);border-radius:4px;}
/* ── Comprehensive RTL layout fixes ── */
[dir=rtl] .btn{flex-direction:row-reverse;}
/* Stat cards */
[dir=rtl] .st{text-align:right;padding-left:54px;padding-right:20px;}
[dir=rtl] .si{right:auto;left:14px;top:14px;}
/* Two-col grid */
[dir=rtl] .twc{direction:rtl;}
/* Card head */
[dir=rtl] .ch{flex-direction:row-reverse;}
/* Profile hero */
[dir=rtl] .ph{flex-direction:row;}
/* Chat */
[dir=rtl] .mbw{flex-direction:row-reverse;}
[dir=rtl] .mbw.me{flex-direction:row;}
[dir=rtl] .mbw.me .bi{align-items:flex-start;}
[dir=rtl] .mbw.me .bt{text-align:right;}
/* Table row cells */
[dir=rtl] .uc{flex-direction:row;}
/* Form */
[dir=rtl] .fgg{align-items:flex-start;}
[dir=rtl] .ms input{text-align:right;font-family:'Cairo',sans-serif;}
/* Notif item */
[dir=rtl] .nit{flex-direction:row-reverse;}
/* Prof hero */
[dir=rtl] .ph{flex-direction:row;}
/* Sidebar user chip */
[dir=rtl] .uch{flex-direction:row-reverse;}
/* Topbar */
[dir=rtl] .pt{font-family:'Cairo',sans-serif;}
/* Nav badge */
[dir=rtl] .bdg{margin-left:0;margin-right:auto;}
/* Mobile nav badge */
[dir=rtl] .mnb{right:auto;left:calc(50% - 20px);}
/* Messages list item */
[dir=rtl] .mi{flex-direction:row-reverse;}
/* Chat area header */
[dir=rtl] .cah{flex-direction:row-reverse;}
/* Bubble inner alignment */
[dir=rtl] .bi .bt{text-align:left;}
/* Search results flex row */
[dir=rtl] .tbr{flex-direction:row-reverse;}
/* Reserve card action row */
[dir=rtl] .ractions{flex-direction:row-reverse;}

@media(max-width:768px){
  html,body{height:auto!important;overflow:auto!important;width:100%!important;}body{display:block!important;}
  .sb{position:fixed!important;top:0;left:0;bottom:0;width:260px!important;height:100vh!important;transform:translateX(-110%);transition:transform .3s cubic-bezier(.4,0,.2,1);z-index:300;overflow-y:auto;}
  .sb.open{transform:translateX(0);}[dir=rtl] .sb{left:auto;right:0;transform:translateX(110%);}[dir=rtl] .sb.open{transform:translateX(0);}
  .sbo{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:299;}.sbo.open{display:block;}
  .main{width:100%!important;min-width:0!important;height:auto!important;overflow:visible!important;display:flex;flex-direction:column;min-height:100vh;margin-left:0!important;margin-right:0!important;}
  .tb{padding:10px 12px!important;position:sticky;top:0;z-index:100;}.pt{font-size:14px!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px;}
  .tbr{gap:6px!important;}.ib{width:32px!important;height:32px!important;}.av{width:32px!important;height:32px!important;font-size:11px!important;}
  .mhb{display:flex!important;}
  .ct{padding:14px 12px 90px!important;overflow-y:auto!important;flex:1;width:100%;min-width:0;}
  .ct.msgs{padding:0!important;overflow:hidden!important;height:calc(100vh - 56px);}
  .sg{grid-template-columns:repeat(2,1fr)!important;}.twc{grid-template-columns:1fr!important;}.fg{grid-template-columns:1fr!important;}
  .mll{display:none!important;}.cam{height:calc(100vh - 175px)!important;}
  .mn{display:flex!important;position:fixed;bottom:0;left:0;right:0;background:var(--sf);border-top:1.5px solid var(--bd);z-index:200;padding-bottom:env(safe-area-inset-bottom,0px);box-shadow:0 -4px 20px rgba(0,0,0,.06);}
  .mnt{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:7px 2px 5px;text-decoration:none;color:var(--mu);font-size:9px;font-weight:600;gap:3px;position:relative;}
  .mnt svg{width:20px;height:20px;flex-shrink:0;}.mnt.active{color:var(--pr);}
  .mnb{position:absolute;top:4px;right:calc(50% - 20px);background:var(--rd);color:#fff;font-size:9px;font-weight:700;padding:1px 4px;border-radius:20px;min-width:15px;text-align:center;}
  input,select,textarea{font-size:16px!important;}
  .cai{padding:10px 12px!important;gap:8px!important;}.cai form{display:contents!important;}
  .cai textarea{min-height:40px!important;max-height:100px!important;font-size:14px!important;}
  .sb2{padding:9px 14px!important;font-size:13px!important;height:40px!important;align-self:flex-end!important;flex-shrink:0!important;white-space:nowrap;}
}
@media(min-width:769px){.mn{display:none!important;}.mhb{display:none!important;}.sbo{display:none!important;}}
</style>
</head>
<body>
<aside class="sb">
  <div class="logo"><div class="logo-i">KDZ</div><div class="logo-t">Khidma <span>DZ</span></div></div>
  <nav class="nav">
    <div class="ns"><?=$lang==='ar'?'القائمة':'Menu'?></div>
    <a class="ni <?=$active_tab==='accueil'?'active':''?>" href="?tab=accueil"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg><?=$lang==='ar'?'الرئيسية':'Accueil'?></a>
    <a class="ni <?=$active_tab==='recherche'?'active':''?>" href="?tab=recherche"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><?=$lang==='ar'?'بحث عن تقني':'Rechercher'?></a>
    <a class="ni <?=$active_tab==='reservations'?'active':''?>" href="?tab=reservations"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><?=$lang==='ar'?'حجوزاتي':'Mes réservations'?></a>
    <a class="ni <?=$active_tab==='litiges'?'active':''?>" href="?tab=litiges"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><?=$lang==='ar'?'النزاعات':'Litiges'?></a>
    <a class="ni <?=$active_tab==='messages'?'active':''?>" href="?tab=messages"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><?=$lang==='ar'?'الرسائل':'Messages'?><?php if($unread_msg>0):?><span class="bdg"><?=$unread_msg?></span><?php endif;?></a>
    <a class="ni <?=$active_tab==='notifications'?'active':''?>" href="?tab=notifications"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><?=$lang==='ar'?'الإشعارات':'Notifications'?><?php if($notif_count>0):?><span class="bdg"><?=$notif_count?></span><?php endif;?></a>
    <div class="ns"><?=$lang==='ar'?'الحساب':'Compte'?></div>
    <a class="ni <?=$active_tab==='profil'?'active':''?>" href="?tab=profil"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><?=$lang==='ar'?'ملفي الشخصي':'Mon profil'?></a>
    <a class="ni" href="index.php"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg><?=$lang==='ar'?'الموقع':'Voir le site'?></a>
    <a class="ni" href="actions/handle_logout.php"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><?=$lang==='ar'?'خروج':'Déconnexion'?></a>
  </nav>
  <div class="sbf"><a class="uch" href="?tab=profil" style="<?=$lang==='ar'?'flex-direction:row-reverse':''?>"><div class="av"><?=ini($client['nom'],$client['prenom'])?></div><div><div class="av-n"><?=htmlspecialchars($client['prenom'].' '.$client['nom'])?></div><div class="av-r"><?=$lang==='ar'?'عميل':'Client'?></div></div></a></div>
</aside>

<main class="main">
  <div class="tb">
    <button class="mhb" onclick="openSb()" style="display:none;background:none;border:none;cursor:pointer;padding:4px;color:var(--tx);margin-right:8px;flex-shrink:0"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="pt"><?=$T[$active_tab]??($lang==='ar'?'الرئيسية':'Accueil')?></div>
    <div class="tbr">
      <a href="?tab=<?=$active_tab?>&lang=<?=$lang==='ar'?'fr':'ar'?>" style="padding:6px 14px;background:var(--sf2);border:1px solid var(--bd);border-radius:9px;font-size:12px;font-weight:700;color:var(--gr);text-decoration:none;"><?=$lang==='ar'?'FR':'عربي'?></a>
      <!-- Map button -->
      <button onclick="openTechMap()" class="ib" title="<?=$lang==='ar'?'خريطة التقنيين':'Carte des techniciens'?>" style="cursor:pointer;background:var(--sf2);border:1px solid var(--bd);">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </button>
      <a href="?tab=notifications" class="ib"><?php if($notif_count>0):?><div class="nd"></div><?php endif;?><svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></a>
      <a href="?tab=profil" class="av" style="text-decoration:none"><?=ini($client['nom'],$client['prenom'])?></a>
    </div>
  </div>
  <div class="ct <?php if($active_tab==='messages') echo 'msgs'; ?>">

<?php if($active_tab==='accueil'): ?>
<div style="margin-bottom:20px"><div style="font-family:'Syne',sans-serif;font-size:22px;font-weight:800;margin-bottom:3px"><?=$lang==='ar'?'مرحبا':'Bonjour'?>, <?=htmlspecialchars($client['prenom'])?> 👋</div><div style="color:var(--mu);font-size:13px"><?=$lang==='ar'?'ماذا تبحث عنه اليوم؟':'Que cherchez-vous aujourd\'hui ?'?></div></div>
<div class="sg">
  <div class="st" style="<?=$lang==='ar'?'padding-left:54px;padding-right:20px;text-align:right':''?>"><div class="si" style="background:var(--bll)">📋</div><div class="sl"><?=$lang==='ar'?'الحجوزات':'Réservations'?></div><div class="sv"><?=$total_res?></div></div>
  <div class="st" style="<?=$lang==='ar'?'padding-left:54px;padding-right:20px;text-align:right':''?>"><div class="si" style="background:var(--grl)">✅</div><div class="sl"><?=$lang==='ar'?'منتهية':'Terminées'?></div><div class="sv"><?=$done_res?></div></div>
  <div class="st" style="<?=$lang==='ar'?'padding-left:54px;padding-right:20px;text-align:right':''?>"><div class="si" style="background:var(--aml)">⏳</div><div class="sl"><?=$lang==='ar'?'جارية':'En cours'?></div><div class="sv"><?=$active_res?></div></div>
  <div class="st" style="<?=$lang==='ar'?'padding-left:54px;padding-right:20px;text-align:right':''?>"><div class="si" style="background:var(--aml)">⭐</div><div class="sl"><?=$lang==='ar'?'تقييمات':'Avis donnés'?></div><div class="sv"><?=$avis_count?></div></div>
</div>
<div class="twc" style="<?=$lang==='ar'?'direction:rtl':'' ?>">
  <div><div class="stt"><?=$lang==='ar'?'آخر الحجوزات':'Dernières réservations'?></div>
    <div class="card"><?php if(empty($reservations)):?><div style="padding:30px;text-align:center;color:var(--mu)"><?=$lang==='ar'?'لا توجد حجوزات':'Aucune réservation'?></div><?php else:foreach(array_slice($reservations,0,4) as $r):?>
    <a href="?tab=reservations" style="padding:13px 16px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;transition:background .12s;<?=$lang==='ar'?'flex-direction:row-reverse;text-align:right':'' ?>" onmouseover="this.style.background='var(--sf2)'" onmouseout="this.style.background=''">
      <?php if($lang==='ar'):?><?=chip($r['statut_service'])?><?php endif;?>
      <div style="flex:1"><div style="font-size:13px;font-weight:600"><?=htmlspecialchars($r['tp'].' '.$r['tn'])?></div><div style="font-size:12px;color:var(--mu)"><?=htmlspecialchars(svc_label($r['specialité']??'',$lang)?:($r['specialité']??'—'))?></div></div>
      <?php if($lang==='ar'):?><div class="mv" style="background:var(--bll);color:var(--bl)"><?=ini($r['tn'],$r['tp'])?></div><?php else:?><div class="mv" style="background:var(--bll);color:var(--bl)"><?=ini($r['tn'],$r['tp'])?></div><?=chip($r['statut_service'])?><?php endif;?>
    </a><?php endforeach;endif;?></div>
  </div>
  <div><div class="stt"><?=$lang==='ar'?'تقنيون موصى بهم':'Techniciens recommandés'?></div>
    <div class="card"><?php if(empty($all_techs)):?><div style="padding:30px;text-align:center;color:var(--mu)"><?=$lang==='ar'?'لا يوجد تقنيون':'Aucun technicien'?></div><?php else:foreach(array_slice($all_techs,0,4) as $t):?>
    <a href="?tab=recherche&tech=<?=$t['id_technicien']?>" style="padding:13px 16px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;transition:background .12s;<?=$lang==='ar'?'flex-direction:row-reverse;text-align:right':'' ?>" onmouseover="this.style.background='var(--sf2)'" onmouseout="this.style.background=''">
      <?php if($lang==='ar'):?><span style="color:var(--am);font-size:12px"><?=stars($t['avg_note'])?></span><?php endif;?>
      <div style="flex:1"><div style="font-size:13px;font-weight:600"><?=htmlspecialchars($t['prenom'].' '.$t['nom'])?></div><div style="font-size:12px;color:var(--pr)"><?=htmlspecialchars(svc_label($t['specialité']??'',$lang)?:($t['specialité']??'—'))?> · <?=htmlspecialchars($t['ville']??'—')?></div></div>
      <?php if($lang==='ar'):?><div class="mv" style="background:var(--prl);color:var(--pr)"><?=ini($t['nom'],$t['prenom'])?></div><?php else:?><div class="mv" style="background:var(--prl);color:var(--pr)"><?=ini($t['nom'],$t['prenom'])?></div><span style="color:var(--am);font-size:12px"><?=stars($t['avg_note'])?></span><?php endif;?>
    </a><?php endforeach;endif;?></div>
  </div>
</div>

<?php elseif($active_tab==='recherche'): ?>
<?php if($sel_tech): ?>
<?php $back='?tab=recherche'.($sw&&$sc?'&wilaya='.urlencode($sw).'&cat='.urlencode($sc):''); ?>
<div style="margin-bottom:16px"><a href="<?=$back?>" class="btn bg bsm"><?=$lang==='ar'?'→ العودة':'← Retour aux résultats'?></a></div>
<div style="background:linear-gradient(135deg,var(--pr),var(--prd));border-radius:18px;padding:26px;color:#fff;margin-bottom:16px;position:relative;overflow:hidden">
  <div style="position:absolute;top:-30px;right:-30px;width:160px;height:160px;background:rgba(255,255,255,.05);border-radius:50%"></div>
  <div style="display:flex;gap:18px;align-items:flex-start;position:relative;<?=$lang==='ar'?'flex-direction:row-reverse':''?>">
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:24px;font-weight:800;flex-shrink:0"><?=ini($sel_tech['nom'],$sel_tech['prenom'])?></div>
    <div style="flex:1">
      <div style="font-family:'Syne',sans-serif;font-size:22px;font-weight:800;line-height:1.2"><?=htmlspecialchars($sel_tech['prenom'].' '.$sel_tech['nom'])?></div>
      <div style="opacity:.9;font-size:13px;margin-top:4px"><?=htmlspecialchars(svc_label($sel_tech['specialité']??'',$lang)?:($lang==='ar'?'تقني':'Technicien'))?> · 📍 <?=htmlspecialchars($sel_tech['ville']??'—')?></div>
      <div style="margin-top:7px;font-size:15px"><?=stars($sel_tech['avg_note'])?> <span style="opacity:.75;font-size:12px">(<?=$sel_tech['nb_avis']?> <?=$lang==='ar'?'تقييم':'avis'?>)</span></div>
      <?php $tech_status = ($sel_tech['en_cours'] ?? false) ? ($lang==='ar'?'في مهمة':'En intervention') : (($sel_tech['available_now'] ?? false) ? ($lang==='ar'?'متاح':'Disponible') : ($lang==='ar'?'غير متاح':'Indisponible')); $tech_status_bg = ($sel_tech['en_cours'] ?? false) ? 'rgba(239,68,68,.18)' : (($sel_tech['available_now'] ?? false) ? 'rgba(22,163,74,.18)' : 'rgba(156,163,175,.22)'); $tech_status_color = ($sel_tech['en_cours'] ?? false) ? '#fecaca' : (($sel_tech['available_now'] ?? false) ? '#dcfce7' : '#e5e7eb'); ?>
      <div style="margin-top:10px;display:inline-flex;align-items:center;gap:8px;padding:5px 12px;border-radius:999px;background:<?=$tech_status_bg?>;border:1px solid rgba(255,255,255,.22);font-size:12px;font-weight:700;color:<?=$tech_status_color?>">⚑ <?=$tech_status?></div>
      <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;<?=$lang==='ar'?'flex-direction:row-reverse':''?>">
        <?php if($sel_tech['experience']):?><span style="background:rgba(255,255,255,.18);border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600">📅 <?=$sel_tech['experience']?> <?=$lang==='ar'?'سنوات خبرة':"ans d'exp."?></span><?php endif;?>
        <?php if($sel_tech['telephone']):?><span style="background:rgba(255,255,255,.18);border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600">📞 <?=htmlspecialchars($sel_tech['telephone'])?></span><?php endif;?>
        <span style="background:rgba(255,255,255,.18);border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600">✓ <?=$lang==='ar'?'موثوق':'Vérifié'?></span>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0">
      <a href="?tab=messages&with=<?=$sel_tech['tuid']?>" class="btn bsm" style="background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.4)">💬 <?=$lang==='ar'?'تواصل':'Contacter'?></a>
      <button onclick="document.getElementById('sig-modal').classList.add('open')" class="btn bsm" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);border:1.5px solid rgba(255,255,255,.25);font-size:11px">🚩 <?=$lang==='ar'?'إبلاغ':'Signaler'?></button>
    </div>
  </div>
  <?php if($sel_tech['description_profil']):?><div style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,.2);font-size:13.5px;line-height:1.65;opacity:.9"><?=htmlspecialchars($sel_tech['description_profil'])?></div><?php endif;?>
</div>
<div style="background:#fff;border:1.5px solid var(--bd);border-radius:18px;overflow:hidden;margin-bottom:16px">
  <div style="padding:18px 22px;border-bottom:1px solid var(--bd);background:var(--sf2)">
    <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:15px">🛠 <?=$lang==='ar'?'خدمات '.htmlspecialchars($sel_tech['prenom']):'Prestations de '.htmlspecialchars($sel_tech['prenom'])?></div>
    <div style="font-size:12.5px;color:var(--mu);margin-top:3px"><?=$lang==='ar'?'اختر الخدمات وعدّل الكميات ثم احجز.':'Cochez les prestations, ajustez les quantités, puis réservez.'?></div>
  </div>
  <form method="POST" style="padding:20px 22px">
    <input type="hidden" name="action" value="book"><input type="hidden" name="tab" value="reservations"><input type="hidden" name="id_technicien" value="<?=$sel_tech['id_technicien']?>">
    <?php if(empty($prestations)):?>
    <div style="padding:32px;text-align:center;color:var(--mu)"><div style="font-size:36px;margin-bottom:10px">🛠</div><div style="font-weight:600"><?=$lang==='ar'?'لم يضف هذا التقني خدماته بعد':'Ce technicien n\'a pas encore ajouté de prestations'?></div><a href="?tab=messages&with=<?=$sel_tech['tuid']?>" class="btn bp" style="margin-top:14px;display:inline-flex">💬 <?=$lang==='ar'?'إرسال رسالة':'Envoyer un message'?></a></div>
    <?php else:?>
    <?php $cp=array_filter($prestations,fn($p)=>!$p['is_custom']); $cup=array_filter($prestations,fn($p)=>$p['is_custom']); ?>
    <?php if(!empty($cp)):?>
    <div style="margin-bottom:20px"><div style="font-size:13px;font-weight:700;margin-bottom:12px">📋 <?=$lang==='ar'?'الخدمات الأساسية':'Prestations standards'?></div>
      <div style="display:flex;flex-direction:column;gap:9px" id="pp-cat">
        <?php foreach($cp as $p):?>
        <label data-price="<?=$p['prix_unitaire']??0?>" style="display:flex;align-items:center;gap:13px;padding:13px 15px;background:var(--sf2);border:2px solid var(--bd);border-radius:13px;cursor:pointer;transition:all .2s;user-select:none" onclick="pps(this)">
          <input type="checkbox" name="prestations[]" value="<?=$p['id_prestation']?>" style="display:none">
          <div class="ppc" style="width:22px;height:22px;border-radius:7px;border:2px solid var(--bd);background:#fff;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;color:#fff;transition:all .2s"></div>
          <div style="flex:1;min-width:0;<?=$lang==='ar'?'text-align:right':''?>"><div style="font-size:14px;font-weight:700;color:var(--tx)"><?=htmlspecialchars(pn($p,$lang))?></div><?php if($p['description']):?><div style="font-size:12px;color:var(--mu);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($p['description'])?></div><?php endif;?></div>
          <div style="text-align:right;flex-shrink:0"><div style="font-size:15px;font-weight:800;color:var(--pr)"><?=money($p['prix_unitaire']??0,$lang)?></div><div style="font-size:11px;color:var(--mu);margin-top:1px">/ <?=htmlspecialchars(ul($p['unite']??'intervention',$lang))?></div></div>
          <?php if(in_array(strtolower(($p['unite']??'')), ['heure','jour','unité','unite','u'])):?><div class="ppq" style="display:none;align-items:center;gap:7px;flex-shrink:0" onclick="event.stopPropagation()"><button type="button" onclick="ppqc(this,-1)" style="width:28px;height:28px;border-radius:8px;border:1.5px solid var(--bd);background:#fff;font-weight:800;font-size:14px;cursor:pointer;line-height:1">−</button><span class="ppqv" style="font-weight:800;font-size:14px;min-width:18px;text-align:center">1</span><input type="hidden" name="qty_<?=$p['id_prestation']?>" class="ppqi" value="1"><button type="button" onclick="ppqc(this,1)" style="width:28px;height:28px;border-radius:8px;border:1.5px solid var(--bd);background:#fff;font-weight:800;font-size:14px;cursor:pointer;line-height:1">+</button></div><?php else:?><input type="hidden" name="qty_<?=$p['id_prestation']?>" class="ppqi" value="1"><?php endif;?>
        </label>
        <?php endforeach;?>
      </div>
    </div>
    <?php endif;?>
    <?php if(!empty($cup)):?>
    <div style="margin-bottom:20px"><div style="font-size:13px;font-weight:700;margin-bottom:12px">✨ <?=$lang==='ar'?'خدمات إضافية':'Autres prestations'?></div>
      <div style="display:flex;flex-direction:column;gap:9px" id="pp-cus">
        <?php foreach($cup as $p):?>
        <label data-price="<?=$p['prix_unitaire']??0?>" style="display:flex;align-items:center;gap:13px;padding:13px 15px;background:#fef3c7;border:2px solid #f59e0b;border-radius:13px;cursor:pointer;transition:all .2s;user-select:none" onclick="pps(this)">
          <input type="checkbox" name="prestations[]" value="<?=$p['id_prestation']?>" style="display:none">
          <div class="ppc" style="width:22px;height:22px;border-radius:7px;border:2px solid #d97706;background:#fff;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;color:#fff;transition:all .2s"></div>
          <div style="flex:1;min-width:0;<?=$lang==='ar'?'text-align:right':''?>"><div style="font-size:14px;font-weight:700;color:#92400e"><?=htmlspecialchars(pn($p,$lang))?></div><?php if($p['description']):?><div style="font-size:12px;color:#92400e;opacity:.8;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($p['description'])?></div><?php endif;?></div>
          <div style="text-align:right;flex-shrink:0"><div style="font-size:15px;font-weight:800;color:#dc2626"><?=money($p['prix_unitaire']??0,$lang)?></div><div style="font-size:11px;color:#92400e;opacity:.8;margin-top:1px">/ <?=htmlspecialchars(ul($p['unite']??'intervention',$lang))?></div></div>
          <?php if(in_array(strtolower(($p['unite']??'')), ['heure','jour','unité','unite','u'])):?><div class="ppq" style="display:none;align-items:center;gap:7px;flex-shrink:0" onclick="event.stopPropagation()"><button type="button" onclick="ppqc(this,-1)" style="width:28px;height:28px;border-radius:8px;border:1.5px solid #d97706;background:#fff;font-weight:800;font-size:14px;cursor:pointer;line-height:1">−</button><span class="ppqv" style="font-weight:800;font-size:14px;min-width:18px;text-align:center">1</span><input type="hidden" name="qty_<?=$p['id_prestation']?>" class="ppqi" value="1"><button type="button" onclick="ppqc(this,1)" style="width:28px;height:28px;border-radius:8px;border:1.5px solid #d97706;background:#fff;font-weight:800;font-size:14px;cursor:pointer;line-height:1">+</button></div><?php else:?><input type="hidden" name="qty_<?=$p['id_prestation']?>" class="ppqi" value="1"><?php endif;?>
        </label>
        <?php endforeach;?>
      </div>
    </div>
    <?php endif;?>
    <div id="pp-tot" style="display:none;background:var(--prl);border:2px solid var(--bd);border-radius:12px;padding:12px 16px;margin-bottom:16px"><div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px;color:var(--pr);font-weight:600"><?=$lang==='ar'?'المجموع التقديري':'Total estimé'?></span><span id="pp-tv" style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:var(--pr)">0 DA</span></div></div>
    <div id="pp-bf" style="display:none;margin-top:16px;padding-top:18px;border-top:1.5px solid var(--bd)">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div><div class="fl" style="margin-bottom:6px"><?=$lang==='ar'?'التاريخ المرغوب':'Date souhaitée'?></div><input type="date" name="date_service" min="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>" required class="fi"></div>
        <div><div class="fl" style="margin-bottom:6px"><?=$lang==='ar'?'الوصف':'Description'?></div><textarea name="description" rows="2" placeholder="<?=$lang==='ar'?'صف مشكلتك...':'Décrivez votre problème…'?>" class="fi" style="min-height:44px"></textarea></div>
      </div>
      <button type="submit" class="btn bp" style="width:100%;justify-content:center;font-size:14px;padding:12px;border-radius:11px;background:linear-gradient(135deg,var(--pr),var(--prd));font-family:'Syne',sans-serif;font-weight:800">✓ <?=$lang==='ar'?'تأكيد الحجز':'Confirmer la réservation'?></button>
    </div>
    <?php endif;?>
  </form>
</div>
<?php if(!empty($avis_tech)):?>
<div style="background:#fff;border:1.5px solid var(--bd);border-radius:18px;padding:20px 22px;margin-bottom:16px">
  <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:15px;margin-bottom:14px">⭐ <?=$lang==='ar'?'تقييمات العملاء':'Avis clients'?> (<?=count($avis_tech)?>)</div>
  <?php foreach($avis_tech as $a):?><div style="padding:13px 16px;background:var(--sf2);border:1px solid var(--bd);border-radius:12px;margin-bottom:8px"><div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:5px"><strong style="font-size:13.5px"><?=htmlspecialchars(($a['prenom']??'').' '.$a['nom'])?></strong><div style="display:flex;align-items:center;gap:8px"><span style="color:var(--am);font-size:13px"><?=stars($a['note'])?></span><span style="font-size:11px;color:var(--mu)"><?=$a['date_avis']?date('d/m/Y',strtotime($a['date_avis'])):''?></span></div></div><?php if($a['commentaire']):?><div style="font-size:13px;color:var(--mu);line-height:1.6"><?=htmlspecialchars($a['commentaire'])?></div><?php endif;?></div><?php endforeach;?>
</div>
<?php endif;?>
<div class="mo" id="sig-modal"><div class="mox"><div class="moh" style="<?=$lang==='ar'?'flex-direction:row-reverse':''?>"><div class="mot">🚩 <?=$lang==='ar'?'الإبلاغ عن تقني':'Signaler un technicien'?></div><div class="mcb" onclick="document.getElementById('sig-modal').classList.remove('open')">✕</div></div>
<div class="mob"><form method="POST" action="actions/handle_signalement.php"><input type="hidden" name="id_user_signale" value="<?=$sel_tech['tuid']?>"><div style="margin-bottom:12px"><div class="fl" style="margin-bottom:5px"><?=$lang==='ar'?'السبب':'Motif'?></div><select name="motif" class="fi"><option value="comportement"><?=$lang==='ar'?'سلوك غير لائق':'Comportement inapproprié'?></option><option value="fraude"><?=$lang==='ar'?'احتيال':'Fraude'?></option><option value="qualite"><?=$lang==='ar'?'جودة رديئة':'Mauvaise qualité'?></option><option value="absence"><?=$lang==='ar'?'غياب':'Absence'?></option><option value="autre"><?=$lang==='ar'?'أخرى':'Autre'?></option></select></div><div style="margin-bottom:14px"><div class="fl" style="margin-bottom:5px"><?=$lang==='ar'?'الوصف':'Description'?></div><textarea name="description" rows="3" class="fi" placeholder="<?=$lang==='ar'?'صف المشكلة...':'Décrivez le problème…'?>"></textarea></div><div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn bg" onclick="document.getElementById('sig-modal').classList.remove('open')"><?=$lang==='ar'?'إلغاء':'Annuler'?></button><button type="submit" style="background:#dc2626;color:#fff;border:none;border-radius:10px;padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer"><?=$lang==='ar'?'إرسال':'Envoyer'?></button></div></form></div></div></div>

<?php else: ?>
<div style="background:linear-gradient(135deg,var(--pr),var(--prd));border-radius:18px;padding:22px;margin-bottom:18px;color:#fff;box-shadow:0 8px 32px rgba(124,58,237,.2)">
  <div style="font-family:'Syne',sans-serif;font-size:19px;font-weight:800;margin-bottom:3px">🔍 <?=$lang==='ar'?'ابحث عن تقني':'Trouver un technicien'?></div>
  <div style="font-size:13px;opacity:.8;margin-bottom:16px"><?=$lang==='ar'?'اختر الولاية ونوع الخدمة':'Choisissez votre wilaya et le type de service'?></div>
  <form method="GET"><input type="hidden" name="tab" value="recherche">
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <select name="wilaya" required style="flex:1;min-width:160px;padding:10px 14px;border-radius:11px;border:2px solid rgba(255,255,255,.45);background:#fff;color:var(--pr);font-weight:600;font-size:13px;cursor:pointer">
        <option value=""><?=$lang==='ar'?'📍 الولاية ':'📍 Wilaya '?></option>
        <?php $wfrl=wilayas_fr();$warl=wilayas_ar();foreach($wfrl as $num=>$fr):$ar=$warl[$num]??$fr;$sel=(wilaya_norm($sw)===wilaya_norm($fr))?' selected':'';$lbl=$lang==='ar'?$num.' — '.$ar:$num.' — '.$fr;?><option value="<?=htmlspecialchars($fr)?>"<?=$sel?>><?=htmlspecialchars($lbl)?></option><?php endforeach;?>
      </select>
      <select name="cat" required style="flex:2;min-width:180px;padding:10px 14px;border-radius:11px;border:2px solid rgba(255,255,255,.45);background:#fff;color:var(--pr);font-weight:600;font-size:13px;cursor:pointer">
        <option value=""><?=$lang==='ar'?'🔧 نوع الخدمة ':'🔧 Type de service '?></option>
        <?php foreach(all_services() as $key=>$svc):?><option value="<?=htmlspecialchars($key)?>" <?=$sc===$key?'selected':''?>><?=$svc['emoji']?> <?=htmlspecialchars($lang==='ar'?$svc['ar']:$svc['fr'])?></option><?php endforeach;?>
      </select>
      <button type="submit" style="padding:10px 22px;border-radius:11px;border:none;background:#fff;color:var(--pr);font-weight:800;font-size:14px;cursor:pointer;white-space:nowrap;font-family:'Syne',sans-serif"><?=$lang==='ar'?'← التالي':'Suivant →'?></button>
    </div>
  </form>
</div>

<?php if($sw&&$sc): ?>
<?php if(empty($si)): ?>
<div style="margin-bottom:14px"><div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:800;margin-bottom:4px"><?=htmlspecialchars(all_services()[$sc]['emoji']??'🔧')?> <?=htmlspecialchars($lang==='ar'?(all_services()[$sc]['ar']??$sc):(all_services()[$sc]['fr']??$sc))?> <span style="font-size:13px;font-weight:500;color:var(--mu)"> <?=$lang==='ar'?'في':'à'?> <?=htmlspecialchars($sw)?></span></div><div style="font-size:13px;color:var(--mu)"><?=$lang==='ar'?'اختر الخدمات التي تحتاجها':'Sélectionnez les prestations dont vous avez besoin'?></div></div>
<?php if(empty($catalogue)): ?>
<div class="card" style="padding:40px;text-align:center;color:var(--mu)"><div style="font-size:36px;margin-bottom:10px">🔧</div><div style="font-weight:600"><?=$lang==='ar'?'لا توجد خدمات متاحة لهذا النوع':'Aucune prestation disponible pour ce service'?></div></div>
<?php else: ?>
<form method="GET"><input type="hidden" name="tab" value="recherche"><input type="hidden" name="wilaya" value="<?=htmlspecialchars($sw)?>"><input type="hidden" name="cat" value="<?=htmlspecialchars($sc)?>">
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:16px">
    <?php foreach($catalogue as $ci):$dn=($lang==='ar'&&!empty($ci['nom_ar']))?$ci['nom_ar']:$ci['nom'];?>
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:14px 10px;background:#fff;border:2px solid var(--bd);border-radius:14px;cursor:pointer;text-align:center;transition:all .18s;min-height:72px;position:relative;user-select:none" onclick="tc(this,<?=$ci['id_catalogue']?>)">
      <input type="checkbox" name="items[]" value="<?=$ci['id_catalogue']?>" style="display:none" class="cat-cb">
      <div style="font-size:13px;font-weight:700;color:var(--tx);line-height:1.3"><?=htmlspecialchars($dn)?></div>
      <div class="cat-ck" style="position:absolute;top:7px;right:7px;width:16px;height:16px;border-radius:5px;border:2px solid var(--bd);background:#fff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;color:#fff;transition:all .15s"></div>
    </div>
    <?php endforeach;?>
  </div>
  <div style="display:flex;align-items:center;justify-content:space-between;background:#fff;border:1.5px solid var(--bd);border-radius:14px;padding:12px 16px">
    <span id="cat-cnt" style="font-size:13px;color:var(--mu)"><?=$lang==='ar'?'لم يتم اختيار أي خدمة':'Aucune prestation sélectionnée'?></span>
    <button type="submit" id="cat-sub" disabled style="padding:9px 20px;border-radius:10px;border:none;background:var(--pr);color:#fff;font-weight:700;font-size:13px;cursor:pointer;opacity:.4;transition:all .18s"><?=$lang==='ar'?'← عرض التقنيين':'Voir les techniciens →'?></button>
  </div>
</form>
<?php endif;?>

<?php else: ?>
<?php
$snd=[];
if(!empty($si)){$in2=implode(',',array_fill(0,count($si),'?'));$snq=$pdo->prepare("SELECT nom,COALESCE(nom_ar,'') as nom_ar FROM prestation_catalogue WHERE id_catalogue IN ($in2)");$snq->execute($si);$snq_rows=$snq->fetchAll();$snd=array_map(fn($r)=>($lang==='ar'&&!empty($r['nom_ar']))?$r['nom_ar']:$r['nom'],$snq_rows);}
$tp=array_sum(array_map(fn($e)=>count($e['prestations']),$search_results));
?>
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px">
  <div><span style="font-size:15px;font-weight:800"><?=$tp?> <?=$lang==='ar'?'خدمة':'prestation'?><?=($tp!=1&&$lang!=='ar')?'s':''?></span> <span style="font-size:13px;color:var(--mu)">· <?=count($search_results)?> <?=$lang==='ar'?'تقني':'tech'?> <?=$lang==='ar'?'في':'à'?> <strong><?=htmlspecialchars($sw)?></strong></span></div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;<?=$lang==='ar'?'flex-direction:row-reverse':'' ?>">
    <form method="GET" style="display:contents"><input type="hidden" name="tab" value="recherche"><input type="hidden" name="wilaya" value="<?=htmlspecialchars($sw)?>"><input type="hidden" name="cat" value="<?=htmlspecialchars($sc)?>"><?php foreach($si as $s):?><input type="hidden" name="items[]" value="<?=$s?>"><?php endforeach;?>
      <select name="sort" onchange="this.form.submit()" style="padding:7px 12px;border-radius:9px;border:1.5px solid var(--bd);background:#fff;font-size:12.5px;font-weight:600;color:var(--tx);cursor:pointer;outline:none">
        <option value="prix_asc" <?=$ss==='prix_asc'?'selected':''?>>💰 <?=$lang==='ar'?'السعر تصاعدياً':'Prix croissant'?></option>
        <option value="prix_desc" <?=$ss==='prix_desc'?'selected':''?>>💰 <?=$lang==='ar'?'السعر تنازلياً':'Prix décroissant'?></option>
        <option value="note" <?=$ss==='note'?'selected':''?>>⭐ <?=$lang==='ar'?'الأعلى تقييماً':'Mieux notés'?></option>
      </select>
    </form>
    <a href="?tab=recherche&wilaya=<?=urlencode($sw)?>&cat=<?=urlencode($sc)?>" style="font-size:12.5px;font-weight:600;color:var(--pr);text-decoration:none;padding:7px 12px;border:1.5px solid var(--bd);border-radius:9px;background:#fff;white-space:nowrap"><?=$lang==='ar'?'← تعديل':'← Modifier'?></a>
  </div>
</div>
<?php if(!empty($snd)):?><div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px"><?php foreach($snd as $sn):?><span style="background:var(--prl);color:var(--pr);border:1.5px solid var(--bd);border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700"><?=htmlspecialchars($sn)?></span><?php endforeach;?></div><?php endif;?>
<?php if(empty($search_results)): ?>
<div class="card" style="padding:48px;text-align:center;color:var(--mu)"><div style="font-size:40px;margin-bottom:10px">🔍</div><div style="font-weight:600;margin-bottom:6px"><?=$lang==='ar'?'لم يُعثر على أي تقني':'Aucun technicien trouvé'?></div><a href="?tab=recherche&wilaya=<?=urlencode($sw)?>&cat=<?=urlencode($sc)?>" class="btn bg bsm" style="margin-top:10px"><?=$lang==='ar'?'← تعديل':'← Modifier'?></a></div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach($search_results as $tid=>$entry):$ts=$entry['tech'];$ps=$entry['prestations'];?>
<div style="background:#fff;border:1.5px solid var(--bd);border-radius:18px;overflow:hidden;box-shadow:0 2px 12px rgba(124,58,237,.05)">
  <div style="padding:13px 18px;background:var(--prl);border-bottom:1.5px solid var(--bd);display:flex;align-items:center;gap:12px;<?=$lang==='ar'?'flex-direction:row-reverse;text-align:right':'' ?>">
    <div style="width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,var(--pr),var(--prd));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:14px;color:#fff;flex-shrink:0"><?=ini($ts['nom'],$ts['prenom'])?></div>
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
        <span style="font-weight:800;font-size:14px;color:var(--tx)"><?=htmlspecialchars($ts['prenom'].' '.$ts['nom'])?></span>
        <?php if($ts['verifie']):?><span style="background:var(--grl);color:var(--gr);border:1px solid rgba(22,163,74,.2);border-radius:20px;padding:2px 8px;font-size:10px;font-weight:700">✓ <?=$lang==='ar'?'موثوق':'Vérifié'?></span><?php endif;?>
        <span style="font-size:12px;color:var(--am)"><?=stars($ts['avg_note'])?></span><span style="font-size:11.5px;color:var(--mu)"><?=number_format($ts['avg_note'],1)?>/5</span>
      </div>
      <div style="font-size:11.5px;color:var(--mu);margin-top:1px"><?=$ts['exp']?$ts['exp'].' '.($lang==='ar'?'سنوات خبرة':"ans d'exp."):''?></div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0">
      <?php if($entry['en_cours']??false):?><span style="background:var(--rdl);color:#dc2626;border:1.5px solid rgba(239,68,68,.2);border-radius:20px;padding:3px 10px;font-size:10px;font-weight:700">🔴 <?=$lang==='ar'?'في مهمة':'En intervention'?></span><?php elseif(($entry['tech']['available_now']??false) || ($entry['available_now']??false)):?><span style="background:var(--grl);color:var(--gr);border:1.5px solid rgba(22,163,74,.2);border-radius:20px;padding:3px 10px;font-size:10px;font-weight:700">🟢 <?=$lang==='ar'?'متاح':'Disponible'?></span><?php else:?><span style="background:var(--rdl);color:#b91c1c;border:1.5px solid rgba(239,68,68,.2);border-radius:20px;padding:3px 10px;font-size:10px;font-weight:700">⛔ <?=$lang==='ar'?'غير متاح':'Indisponible'?></span><?php endif;?>
      <a href="?tab=recherche&tech=<?=$ts['id']?>&wilaya=<?=urlencode($sw)?>&cat=<?=urlencode($sc)?>" style="font-size:12px;font-weight:700;color:var(--pr);text-decoration:none;white-space:nowrap;padding:5px 11px;border:1.5px solid var(--bd);border-radius:9px;background:#fff" onmouseover="this.style.background='var(--pr)';this.style.color='#fff'" onmouseout="this.style.background='#fff';this.style.color='var(--pr)'"><?=$lang==='ar'?'← الملف الشخصي':'Profil →'?></a>
    </div>
  </div>
  <form method="POST" style="padding:0">
    <input type="hidden" name="action" value="book"><input type="hidden" name="tab" value="reservations"><input type="hidden" name="id_technicien" value="<?=$ts['id']?>">
    <div id="pl-<?=$tid?>" style="padding:8px 12px;display:flex;flex-direction:column;gap:4px">
      <?php foreach($ps as $px):$pxn=($lang==='ar'&&!empty($px['nom_ar']))?$px['nom_ar']:$px['nom'];?>
      <label data-price="<?=$px['prix']?>" style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--sf2);border:1.5px solid var(--bd);border-radius:11px;cursor:pointer;transition:all .15s;user-select:none" onclick="sp(this,<?=$px['id']?>,<?=$tid?>)">
        <input type="checkbox" name="prestations[]" value="<?=$px['id']?>" style="display:none" class="pc-<?=$tid?>">
        <div id="pck-<?=$px['id']?>" style="width:20px;height:20px;border-radius:6px;border:2px solid var(--bd);background:#fff;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;transition:all .15s"></div>
        <div style="flex:1;min-width:0;<?=$lang==='ar'?'text-align:right':''?>"><div style="font-size:13.5px;font-weight:700;color:var(--tx)"><?=htmlspecialchars($pxn)?></div><?php if($px['desc']):?><div style="font-size:11.5px;color:var(--mu);margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($px['desc'])?></div><?php endif;?></div>
        <div style="background:var(--prl);border:1.5px solid var(--bd);border-radius:20px;padding:4px 11px;flex-shrink:0"><span style="font-family:'Syne',sans-serif;font-weight:800;color:var(--pr);font-size:14px"><?=number_format($px['prix'],0)?></span><span style="font-size:10px;color:var(--mu);<?=$lang==='ar'?'margin-right:2px':'margin-left:2px'?>"><?=$lang==='ar'?'دج':'DA'?>/<?=htmlspecialchars(ul($px['unite']??'u',$lang))?></span></div>
        <?php if(in_array(strtolower(($px['unite']??'')), ['heure','jour','unité','unite','u'])):?><div id="qw-<?=$px['id']?>" style="display:none;align-items:center;gap:5px;flex-shrink:0" onclick="event.stopPropagation()"><button type="button" style="width:26px;height:26px;border-radius:7px;border:1.5px solid var(--bd);background:#fff;font-weight:800;font-size:14px;cursor:pointer;line-height:1;color:var(--pr)" onclick="cq(<?=$px['id']?>,-1,<?=$px['prix']?>,<?=$tid?>)">−</button><span id="qv-<?=$px['id']?>" style="font-weight:800;font-size:13px;min-width:18px;text-align:center">1</span><input type="hidden" name="qty_<?=$px['id']?>" id="qi-<?=$px['id']?>" value="1"><button type="button" style="width:26px;height:26px;border-radius:7px;border:1.5px solid var(--bd);background:#fff;font-weight:800;font-size:14px;cursor:pointer;line-height:1;color:var(--pr)" onclick="cq(<?=$px['id']?>,1,<?=$px['prix']?>,<?=$tid?>)">+</button></div><?php else:?><input type="hidden" name="qty_<?=$px['id']?>" id="qi-<?=$px['id']?>" value="1"><?php endif;?>
      </label>
      <?php endforeach;?>
    </div>
    <div id="tot-<?=$tid?>" style="display:none;margin:0 12px 8px;background:var(--prl);border:2px solid var(--bd);border-radius:11px;padding:10px 14px"><div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.5px"><?=$lang==='ar'?'المجموع التقديري':'Total estimé'?></span><span id="tv-<?=$tid?>" style="font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--pr)">0 DA</span></div></div>
    <div id="bf-<?=$tid?>" style="display:none;border-top:1.5px solid var(--bd);padding:14px 18px 18px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div><div class="fl" style="margin-bottom:5px"><?=$lang==='ar'?'التاريخ المرغوب':'Date souhaitée'?></div><input type="date" name="date_service" min="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>" required class="fi"></div>
        <div><div class="fl" style="margin-bottom:5px"><?=$lang==='ar'?'الوصف':'Description'?></div><textarea name="description" rows="2" placeholder="<?=$lang==='ar'?'صف مشكلتك...':'Décrivez votre problème…'?>" class="fi" style="min-height:44px"></textarea></div>
      </div>
      <button class="btn bp" type="submit" style="width:100%;justify-content:center;font-size:14px;padding:12px;border-radius:11px;background:linear-gradient(135deg,var(--pr),var(--prd));font-family:'Syne',sans-serif;font-weight:800">✓ <?=$lang==='ar'?'احجز مع '.htmlspecialchars($ts['prenom']):'Réserver avec '.htmlspecialchars($ts['prenom'])?></button>
    </div>
  </form>
</div>
<?php endforeach;?>
</div>
<?php endif;?>
<?php endif;?>
<?php endif;?>
<?php endif;?>

<?php elseif($active_tab==='reservations'): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><div class="stt"><?=$lang==='ar'?'حجوزاتي':'Mes réservations'?> (<?=count($reservations)?>)</div><a href="?tab=recherche" class="btn bp bsm">+ <?=$lang==='ar'?'حجز جديد':'Nouvelle réservation'?></a></div>
<?php if(empty($reservations)):?>
<div class="card"><div style="padding:48px;text-align:center;color:var(--mu)"><div style="font-size:40px;margin-bottom:10px">📭</div><div><?=$lang==='ar'?'لا توجد حجوزات':'Aucune réservation'?></div><a href="?tab=recherche" class="btn bp" style="margin-top:12px"><?=$lang==='ar'?'ابحث عن تقني':'Trouver un technicien'?></a></div></div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:12px">
<?php foreach($reservations as $r):
  $prs=$cli_res_prests[(int)$r['id_service']]??[];
  $isa=in_array($r['statut_service'],['planifié','en cours']);$isd=$r['statut_service']==='terminé';$isc=$r['statut_service']==='annulé';
  $bdc=$r['statut_service']==='planifié'?'var(--pr)':($r['statut_service']==='en cours'?'#f59e0b':'var(--bd)');
  $hp=($r['statut_service']==='terminé'&&!empty($r['prix_final']));
?>
<div style="background:#fff;border:1.5px solid <?=$bdc?>;border-radius:16px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.04)">
  <div style="padding:15px 20px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--bd);<?=$lang==='ar'?'flex-direction:row-reverse;text-align:right':'' ?>;background:<?=$r['statut_service']==='planifié'?'var(--prl)':'#fff'?>">
    <div class="mv" style="width:42px;height:42px;font-size:14px;flex-shrink:0;background:var(--bll);color:var(--bl)"><?=ini($r['tn'],$r['tp'])?></div>
    <div style="flex:1;min-width:0"><div style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px"><?=htmlspecialchars($r['tp'].' '.$r['tn'])?></div><div style="font-size:12px;color:var(--mu);margin-top:2px"><?=htmlspecialchars(svc_label($r['specialité']??'',$lang)?:($r['specialité']??'—'))?> · 📅 <?=$r['date_service']?date('d/m/Y',strtotime($r['date_service'])):'—'?></div></div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0"><?=chip($r['statut_service'])?><?php if($hp):?><span style="font-size:13px;font-weight:700;color:var(--gr)"><?=money($r['prix_final'],$lang)?></span><?php elseif($r['prix_estime']):?><span style="font-size:12px;color:var(--mu)">~<?=money($r['prix_estime'],$lang)?></span><?php endif;?></div>
  </div>
  <?php if(!empty($prs)):?>
  <div style="padding:10px 20px;border-bottom:1px solid var(--bd);background:rgba(6,182,212,.03)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
      <div style="font-size:10px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.8px">🔧 <?=$lang==='ar'?'الخدمات المطلوبة':'Prestations demandées'?></div>
      <?php if($r['statut_service']==='planifié'):?><button onclick="ter(<?=$r['id_service']?>)" class="btn bg bsm" style="font-size:11px;padding:3px 9px">✏️ <?=$lang==='ar'?'تعديل':'Modifier'?></button><?php endif;?>
    </div>
    <div id="rv-<?=$r['id_service']?>"><div style="display:flex;flex-wrap:wrap;gap:5px">
      <?php $lt=0;foreach($prs as $pr):$st=($pr['prix_unitaire']??0)*($pr['quantite']??1);$lt+=$st;$prn=($lang==='ar'&&!empty($pr['nom_ar']))?$pr['nom_ar']:$pr['nom'];?>
      <span style="background:#fff;border:1.5px solid var(--bd);border-radius:7px;padding:3px 10px;font-size:12px;font-weight:600;color:var(--pr)"><?=htmlspecialchars($prn)?><?php if($pr['prix_unitaire']??0):?><span style="opacity:.6;font-weight:400"> · <span dir="ltr" style="unicode-bidi:embed"><?=number_format($pr['prix_unitaire'],0)?></span> <?=$lang==='ar'?'دج':'DA'?>/<?=htmlspecialchars(ul($pr['unite'],$lang))?><?php if(($pr['quantite']??1)>1):?> × <?=$pr['quantite']?><strong style="color:var(--gr)"> = <span dir="ltr" style="unicode-bidi:embed"><?=number_format($st,0)?></span> <?=$lang==='ar'?'دج':'DA'?></strong><?php endif;?></span><?php endif;?></span>
      <?php endforeach;?>
    </div><?php if($lt>0):?><div style="margin-top:8px;font-size:12px;font-weight:700;color:var(--pr)"><?=$lang==='ar'?'المجموع':'Total'?> : <?=money($lt,$lang)?></div><?php endif;?></div>
    <?php if($r['statut_service']==='planifié'):?>
    <div id="re-<?=$r['id_service']?>" style="display:none;margin-top:10px">
      <?php $tpq=$pdo->prepare("SELECT p.*,COALESCE(pc.nom_ar,'') as nom_ar FROM prestation p LEFT JOIN prestation_catalogue pc ON pc.id_catalogue=p.id_catalogue WHERE p.id_technicien=? AND p.actif=1"); $tpq->execute([$r['id_technicien']]); $tpa=$tpq->fetchAll(); $cids=array_column($prs,'id_prestation'); $cqty=array_column($prs,'quantite','id_prestation');?>
      <form method="POST"><input type="hidden" name="action" value="update_service_prestations"><input type="hidden" name="tab" value="reservations"><input type="hidden" name="id_service" value="<?=$r['id_service']?>">
        <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px">
          <?php foreach($tpa as $tp2):$ic=in_array($tp2['id_prestation'],$cids);$qty=$cqty[$tp2['id_prestation']]??1;$tpn=($lang==='ar'&&!empty($tp2['nom_ar']))?$tp2['nom_ar']:$tp2['nom'];?>
          <label style="display:flex;align-items:center;gap:10px;padding:8px 11px;background:<?=$ic?'rgba(6,182,212,.06)':'var(--sf2)'?>;border:1.5px solid var(--bd);border-radius:9px;cursor:pointer" id="rpl-<?=$r['id_service']?>-<?=$tp2['id_prestation']?>">
            <input type="checkbox" name="prestations[]" value="<?=$tp2['id_prestation']?>" <?=$ic?'checked':''?> onchange="trq(this,<?=$r['id_service']?>,<?=$tp2['id_prestation']?>)" style="accent-color:var(--pr)">
            <div style="flex:1;font-size:13px;font-weight:600"><?=htmlspecialchars($tpn)?></div>
            <div style="font-size:12px;color:var(--pr);font-weight:700"><span dir="ltr" style="unicode-bidi:embed"><?=number_format($tp2['prix_unitaire']??0,0)?></span> <?=$lang==='ar'?'دج':'DA'?>/<?=htmlspecialchars(ul($tp2['unite']??'u',$lang))?></div>
            <?php if(in_array(strtolower(($tp2['unite']??'')), ['heure','jour','unité','unite','u'])):?><div id="rqw-<?=$r['id_service']?>-<?=$tp2['id_prestation']?>" style="display:<?=$ic?'flex':'none'?>;align-items:center;gap:5px" onclick="event.stopPropagation()"><button type="button" style="width:24px;height:24px;border-radius:5px;border:1px solid var(--bd);cursor:pointer" onclick="crq(<?=$r['id_service']?>,<?=$tp2['id_prestation']?>,-1)">−</button><span id="rqv-<?=$r['id_service']?>-<?=$tp2['id_prestation']?>" style="font-weight:700;font-size:12px;min-width:16px;text-align:center"><?=$qty?></span><input type="hidden" name="qty_<?=$tp2['id_prestation']?>" id="rqi-<?=$r['id_service']?>-<?=$tp2['id_prestation']?>" value="<?=$qty?>"><button type="button" style="width:24px;height:24px;border-radius:5px;border:1px solid var(--bd);cursor:pointer" onclick="crq(<?=$r['id_service']?>,<?=$tp2['id_prestation']?>,1)">+</button></div><?php else:?><input type="hidden" name="qty_<?=$tp2['id_prestation']?>" id="rqi-<?=$r['id_service']?>-<?=$tp2['id_prestation']?>" value="1"><?php endif;?>
          </label>
          <?php endforeach;?>
        </div>
        <div style="display:flex;gap:8px"><button class="btn bp bsm" type="submit">✓ <?=$lang==='ar'?'حفظ':'Enregistrer'?></button><button type="button" class="btn bg bsm" onclick="ter(<?=$r['id_service']?>)"><?=$lang==='ar'?'إلغاء':'Annuler'?></button></div>
      </form>
    </div>
    <?php endif;?>
  </div>
  <?php endif;?>
  <?php if($r['description']):?><div style="padding:10px 20px;border-bottom:1px solid var(--bd)"><div style="font-size:12.5px;color:var(--mu);line-height:1.6"><?=htmlspecialchars($r['description'])?></div></div><?php endif;?>
  <div style="padding:12px 20px;display:flex;gap:7px;flex-wrap:wrap;align-items:center;background:var(--sf2);<?=$lang==='ar'?'flex-direction:row-reverse':''?>">
    <?php if($isd&&!$r['id_avis']):?><button class="btn bg bsm" onclick="orat(<?=$r['id_service']?>,<?=$r['id_technicien']?>,'<?=htmlspecialchars($r['tp'].' '.$r['tn'],ENT_QUOTES)?>')">⭐ <?=$lang==='ar'?'تقييم':'Évaluer'?></button><?php elseif($isd&&$r['id_avis']):?><span style="font-size:12px;color:var(--mu);background:var(--sf2);border:1px solid var(--bd);border-radius:7px;padding:4px 10px">✓ <?=$lang==='ar'?'تم التقييم':'Évalué'?></span><?php endif;?>
    <?php if($isd):?><a href="paiment.php?id_service=<?=$r['id_service']?>" class="btn bsm" style="background:var(--prl);color:var(--pr);border:1.5px solid var(--bd)">💳 <?=$lang==='ar'?'الدفع':'Paiement'?></a><?php if($r['id_facture']):?><a href="facture.php?id=<?=$r['id_facture']?>" target="_blank" class="btn bg bsm">📄 <?=$lang==='ar'?'الفاتورة':'Facture'?></a><?php endif;?><?php endif;?>
    <?php if($isa):?><form method="POST" style="display:inline" onsubmit="return confirm('<?=$lang==='ar'?'تأكيد الإلغاء؟':'Confirmer l\'annulation ?'?>')"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id_service" value="<?=$r['id_service']?>"><input type="hidden" name="tab" value="reservations"><button class="btn brr bsm"><?=$lang==='ar'?'إلغاء':'Annuler'?></button></form><?php elseif($isc):?><span style="font-size:12px;color:#dc2626;font-weight:600;background:var(--rdl);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:5px 12px">✕ <?=$lang==='ar'?'الخدمة ملغاة':'Service annulé'?></span><?php endif;?>
    <div style="flex:1"></div>
    <a href="?tab=messages&with=<?=$r['tuid']?>" class="btn bg bsm">💬 <?=$lang==='ar'?'رسالة':'Message'?></a>
  </div>
</div>
<?php endforeach;?>
</div>
<?php endif;?>

<?php elseif($active_tab==='litiges'): ?>
<?php if(!empty($eligible)):?>
<div style="background:var(--sf);border:1.5px solid var(--bd);border-radius:var(--ra);padding:22px;margin-bottom:20px;box-shadow:var(--sh)">
  <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px;margin-bottom:16px">⚠️ <?=$lang==='ar'?'فتح نزاع':'Ouvrir un litige'?></div>
  <form method="POST" action="actions/handle_litige.php"><input type="hidden" name="action" value="open">
    <div style="margin-bottom:12px"><div class="fl" style="margin-bottom:5px"><?=$lang==='ar'?'الخدمة المعنية':'Service concerné'?></div><select name="id_service" class="fi" required><option value=""><?=$lang==='ar'?'اختر خدمة':'— Choisir un service —'?></option><?php foreach($eligible as $e):?><option value="<?=$e['id_service']?>">#<?=$e['id_service']?> — <?=htmlspecialchars($e['tp'].' '.$e['tn'])?> (<?=htmlspecialchars(svc_label($e['specialité']??'',$lang)?:($e['specialité']??'—'))?>) · <?=date('d/m/Y',strtotime($e['date_service']))?></option><?php endforeach;?></select></div>
    <div style="margin-bottom:16px"><div class="fl" style="margin-bottom:5px"><?=$lang==='ar'?'وصف المشكلة':'Description du problème'?></div><textarea name="description" rows="3" class="fi" placeholder="<?=$lang==='ar'?'اشرح مشكلتك بالتفصيل...':'Décrivez le problème en détail…'?>" required></textarea></div>
    <button class="btn bp" type="submit">⚠️ <?=$lang==='ar'?'إرسال النزاع':'Soumettre le litige'?></button>
  </form>
</div>
<?php endif;?>
<div class="card"><div class="ch"><div class="ct2"><?=$lang==='ar'?'نزاعاتي':'Mes litiges'?> (<?=count($litiges)?>)</div></div>
<?php if(empty($litiges)):?><div style="padding:48px;text-align:center;color:var(--mu)"><div style="font-size:40px;margin-bottom:10px">⚖️</div><div><?=$lang==='ar'?'لا توجد نزاعات':'Aucun litige'?></div></div>
<?php else:?><table><thead><tr><th>#</th><th><?=$lang==='ar'?'التقني':'Technicien'?></th><th><?=$lang==='ar'?'الخدمة':'Service'?></th><th><?=$lang==='ar'?'التاريخ':'Date'?></th><th><?=$lang==='ar'?'الحالة':'Statut'?></th><th><?=$lang==='ar'?'القرار':'Résolution'?></th></tr></thead><tbody>
<?php foreach($litiges as $l):?><tr><td style="color:var(--mu);font-size:12px">#<?=$l['id_litige']?></td><td><div class="uc"><div class="mv"><?=ini($l['tn'],$l['tp'])?></div><?=htmlspecialchars($l['tp'].' '.$l['tn'])?></div></td><td style="color:var(--mu);font-size:13px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($l['svc_desc']??'—')?></td><td style="font-size:12px;color:var(--mu)"><?=$l['date_overture']?date('d/m/Y',strtotime($l['date_overture'])):'—'?></td><td><?=chip($l['statut_litige']??'ouvert')?></td><td style="font-size:13px;color:var(--mu)"><?=htmlspecialchars($l['resolution']??($lang==='ar'?'في انتظار المعالجة':'En attente de traitement'))?></td></tr><?php endforeach;?></tbody></table><?php endif;?></div>

<?php elseif($active_tab==='messages'): ?>
<div class="ml">
  <div class="mll">
    <div class="ms"><input type="text" placeholder="<?=$lang==='ar'?'بحث...':'Rechercher…'?>"/></div>
    <?php if(empty($convs)):?><div style="padding:20px;text-align:center;color:var(--mu);font-size:13px"><?=$lang==='ar'?'لا توجد محادثات':'Aucune conversation'?></div>
    <?php else:foreach($convs as $conv):?>
      <a href="?tab=messages&with=<?=$conv['other_id']?>" class="mi <?=$conv['other_id']==$sel_uid?'active':''?>">
        <div class="mv" style="width:34px;height:34px;background:var(--prl);color:var(--pr)"><?=ini($conv['nom'],$conv['prenom'])?></div>
        <div style="flex:1;min-width:0"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px"><span style="font-size:13px;font-weight:600"><?=htmlspecialchars($conv['prenom'].' '.$conv['nom'])?></span><span style="font-size:11px;color:var(--mu)"><?=$conv['last_date']?date('d/m',strtotime($conv['last_date'])):''?></span></div><div style="font-size:12px;color:var(--mu)"><?=ucfirst($conv['role'])?></div></div>
        <?php if($conv['unread']>0):?><div style="width:8px;height:8px;background:var(--pr);border-radius:50%;margin-top:4px;flex-shrink:0"></div><?php endif;?>
      </a>
    <?php endforeach;endif;?>
  </div>
  <div class="ca">
    <?php if($chat_user):?>
    <div class="cah" style="<?=$lang==='ar'?'flex-direction:row-reverse':''?>"><div class="mv" style="width:36px;height:36px;background:var(--prl);color:var(--pr)"><?=ini($chat_user['nom'],$chat_user['prenom'])?></div><div><div style="font-weight:600;font-size:14px"><?=htmlspecialchars($chat_user['prenom'].' '.$chat_user['nom'])?></div><div style="font-size:11px;color:var(--mu)"><?=ucfirst($chat_user['role'])?></div></div></div>
    <div class="cam" id="chat-msgs">
      <?php if(empty($chat_msgs)):?><div style="text-align:center;color:var(--mu);font-size:13px;padding:30px"><?=$lang==='ar'?'ابدأ المحادثة':'Commencez la conversation'?></div>
      <?php else:foreach($chat_msgs as $m):$im=$m['id_expediteur']==$user_id;?>
        <div class="mbw <?=$im?'me':''?>" data-msgid="<?=$m['id_message']?>">
          <div class="mv" style="width:30px;height:30px;font-size:11px;flex-shrink:0;background:<?=$im?'var(--pr);color:#fff':'var(--prl);color:var(--pr)'?>"><?=$im?ini($client['nom'],$client['prenom']):ini($chat_user['nom'],$chat_user['prenom'])?></div>
          <div class="bi"><div class="bu <?=$im?'me':'other'?>"><?=nl2br(htmlspecialchars($m['contenu']))?></div><div class="bt"><?=$m['data_envoi']?date('d/m H:i',strtotime($m['data_envoi'])):''?></div></div>
        </div>
      <?php endforeach;endif;?>
    </div>
    <div class="cai"><form id="chat-frm" method="POST"><input type="hidden" name="action" value="send_message"><input type="hidden" name="id_destinataire" value="<?=$sel_uid?>"><input type="hidden" name="tab" value="messages"><input type="hidden" name="with_redirect" value="<?=$sel_uid?>"><textarea id="chat-ta" name="contenu" placeholder="<?=$lang==='ar'?'اكتب رسالة...':'Écrire un message…'?>" autocomplete="off" rows="1"></textarea><button type="submit" class="sb2"><?=$lang==='ar'?'إرسال':'Envoyer'?></button></form></div>
    <?php else:?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:32px;text-align:center"><div style="font-size:48px">💬</div><div style="font-weight:700;font-size:15px"><?=$lang==='ar'?'ابدأ محادثة':'Démarrer une conversation'?></div><div style="font-size:13px;color:var(--mu);max-width:260px"><?=$lang==='ar'?'تواصل مع تقني من صفحة البحث':'Contactez un technicien depuis la recherche'?></div><a href="?tab=recherche" class="btn bp">🔍 <?=$lang==='ar'?'البحث عن تقني':'Trouver un technicien'?></a></div>
    <?php endif;?>
  </div>
</div>

<?php elseif($active_tab==='notifications'): ?>
<div class="card"><div class="ch"><div class="ct2">🔔 <?=$lang==='ar'?'الإشعارات':'Notifications'?></div><?php if(!empty($all_notifs)):?><form method="POST"><input type="hidden" name="action" value="mark_all_read"><input type="hidden" name="tab" value="notifications"><button class="btn bg bsm" type="submit"><?=$lang==='ar'?'تحديد الكل':'Tout marquer lu'?></button></form><?php endif;?></div>
<?php if(empty($all_notifs)):?><div style="padding:48px;text-align:center;color:var(--mu)"><div style="font-size:40px;margin-bottom:10px">🔔</div><div><?=$lang==='ar'?'لا إشعارات':'Aucune notification'?></div></div>
<?php else:?><div><?php foreach($all_notifs as $n):?>
<div class="nit" style="<?=$lang==='ar'?'flex-direction:row-reverse':''?>" <?=$n['lu']?'':'unread'?>"><div style="width:38px;height:38px;border-radius:10px;background:var(--grl);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">🔔</div><div style="flex:1"><div style="font-size:13px;font-weight:<?=$n['lu']?'600':'700'?>"><?=htmlspecialchars($n['titre'])?></div><div style="font-size:13px;color:var(--mu);margin-top:3px"><?=htmlspecialchars($n['contenu'])?></div><div style="font-size:11px;color:var(--mu);margin-top:4px"><?=$n['date_notif']?date('d/m/Y H:i',strtotime($n['date_notif'])):''?></div></div><?php if(!$n['lu']):?><div style="width:8px;height:8px;background:var(--pr);border-radius:50%;flex-shrink:0;margin-top:5px"></div><?php endif;?><?php if($n['lien']):?><a href="<?=htmlspecialchars($n['lien'])?>" class="btn bg bsm" style="flex-shrink:0"><?=$lang==='ar'?'عرض':'Voir →'?></a><?php endif;?></div>
<?php endforeach;endif;?></div>

<?php elseif($active_tab==='profil'): ?>
<div class="ph"><div class="pav"><?=ini($client['nom'],$client['prenom'])?></div><div class="ph-main"><div class="pnm"><?=htmlspecialchars($client['prenom'].' '.$client['nom'])?></div><div class="psp"><?=$lang==='ar'?'عميل':'Client'?> · <?=htmlspecialchars($client['ville']??($lang==='ar'?'الجزائر':'Algérie'))?> · <?=$client['date_inscription']?date('Y',strtotime($client['date_inscription'])):'—'?></div><div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--mu)"><div>📋 <strong style="color:var(--tx)"><?=$total_res?></strong> <?=$lang==='ar'?'حجز':'réservation'?></div><div>✅ <strong style="color:var(--tx)"><?=$done_res?></strong> <?=$lang==='ar'?'منتهية':'terminée'?></div></div></div></div>
<div id="profile-summary-wrapper" class="card" style="padding:22px;margin-bottom:14px">
  <div class="stt" style="margin-bottom:16px"><?=$lang==='ar'?'معلوماتي الشخصية':'Mes informations personnelles'?></div>
  <div class="fg" style="margin-bottom:18px">
    <div class="fgg">
      <div class="fl"><?=$lang==='ar'?'الاسم':'Prénom'?></div>
      <div class="fi" style="background:#fff;display:flex;align-items:center;min-height:42px;"><?=htmlspecialchars($client['prenom']??'')?></div>
    </div>
    <div class="fgg">
      <div class="fl"><?=$lang==='ar'?'اللقب':'Nom'?></div>
      <div class="fi" style="background:#fff;display:flex;align-items:center;min-height:42px;"><?=htmlspecialchars($client['nom']??'')?></div>
    </div>
    <div class="fgg">
      <div class="fl">Email</div>
      <div class="fi" style="background:#fff;display:flex;align-items:center;min-height:42px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($client['email']??'')?></div>
    </div>
    <div class="fgg">
      <div class="fl"><?=$lang==='ar'?'الهاتف':'Téléphone'?></div>
      <div class="fi" style="background:#fff;display:flex;align-items:center;min-height:42px;"><?=htmlspecialchars($client['telephone']??'')?></div>
    </div>
    <div class="fgg">
      <div class="fl"><?=$lang==='ar'?'الولاية':'Wilaya'?></div>
      <div class="fi" style="background:#fff;display:flex;align-items:center;min-height:42px;"><?=htmlspecialchars($client['ville']??($lang==='ar'?'الجزائر':'Algérie'))?></div>
    </div>
    <div class="fgg">
      <div class="fl"><?=$lang==='ar'?'اللغة':'Langue'?></div>
      <div class="fi" style="background:#fff;display:flex;align-items:center;min-height:42px;"><?=htmlspecialchars($lang==='ar'?'العربية':'Français')?></div>
    </div>
  </div>
  <div style="display:flex;justify-content:flex-start">
    <button class="btn bp" type="button" onclick="toggleProfileEdit(true)">✏️ <?=$lang==='ar'?'تعديل':'Modifier'?></button>
  </div>
</div>
<div id="profile-form-wrapper" class="card" style="padding:22px;display:none">
  <div class="stt" style="margin-bottom:16px"><?=$lang==='ar'?'تعديل معلوماتي':'Modifier mes informations'?></div>
  <form method="POST"><input type="hidden" name="action" value="update_profile"><input type="hidden" name="tab" value="profil">
    <div class="fg">
      <div class="fgg"><div class="fl"><?=$lang==='ar'?'الاسم':'Prénom'?></div><input class="fi" name="prenom" value="<?=htmlspecialchars($client['prenom']??'')?>"/></div>
      <div class="fgg"><div class="fl"><?=$lang==='ar'?'اللقب':'Nom'?></div><input class="fi" name="nom" value="<?=htmlspecialchars($client['nom']??'')?>"/></div>
      <div class="fgg"><div class="fl">Email</div><input class="fi" type="email" name="email" value="<?=htmlspecialchars($client['email']??'')?>"/></div>
      <div class="fgg"><div class="fl"><?=$lang==='ar'?'الهاتف':'Téléphone'?></div><input class="fi" name="telephone" value="<?=htmlspecialchars($client['telephone']??'')?>"/></div>
      <div class="fgg"><div class="fl"><?=$lang==='ar'?'الولاية':'Wilaya'?></div><select class="fi" name="ville"><?php $wfrl=wilayas_fr();$warl=wilayas_ar();$cvn=wilaya_norm((string)($client['ville']??''));foreach($wfrl as $num=>$fr):$ar=$warl[$num]??$fr;$sel=($cvn===wilaya_norm($fr))?'selected':'';$lbl=$lang==='ar'?$num.' — '.$ar:$num.' — '.$fr;?><option value="<?=htmlspecialchars($fr)?>" <?=$sel?>><?=htmlspecialchars($lbl)?></option><?php endforeach;?></select></div>
      <div class="fgg"><div class="fl"><?=$lang==='ar'?'اللغة':'Langue'?></div><select class="fi" name="langue"><option value="fr" <?=$lang==='fr'?'selected':''?>>Français</option><option value="ar" <?=$lang==='ar'?'selected':''?>>العربية</option></select></div>
      <div class="fgg full"><div class="fl"><?=$lang==='ar'?'كلمة مرور جديدة (فارغ = بدون تغيير)':'Nouveau mot de passe (vide = inchangé)'?></div><input class="fi" type="password" name="new_password" placeholder="••••••••"/></div>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-start;margin-top:18px"><button class="btn bp" type="submit">💾 <?=$lang==='ar'?'حفظ':'Enregistrer'?></button><button type="button" class="btn bg" onclick="toggleProfileEdit(false)"><?=$lang==='ar'?'إلغاء':'Annuler'?></button></div>
  </form>
</div>
<?php endif;?>

  </div>
</main>

<div class="mo" id="rat-modal">
  <div class="mox"><div class="moh" style="<?=$lang==='ar'?'flex-direction:row-reverse':''?>"><div class="mot" id="rat-title">⭐ <?=$lang==='ar'?'تقييم الخدمة':'Évaluer le service'?></div><div class="mcb" onclick="document.getElementById('rat-modal').classList.remove('open')">✕</div></div>
  <div class="mob"><form method="POST"><input type="hidden" name="action" value="rate"><input type="hidden" name="tab" value="reservations"><input type="hidden" name="id_service" id="r-svc"><input type="hidden" name="id_technicien" id="r-tech"><input type="hidden" name="note" id="r-note" value="5">
    <div style="text-align:center;margin-bottom:18px"><div style="font-size:13px;color:var(--mu);margin-bottom:14px"><?=$lang==='ar'?'كيف كانت تجربتك مع هذا التقني؟':'Comment s\'est passée votre intervention ?'?></div>
      <div style="display:flex;justify-content:center;gap:6px;margin-bottom:8px"><?php foreach([1,2,3,4,5] as $sv):?><button type="button" data-v="<?=$sv?>" onclick="setr(<?=$sv?>)" style="width:44px;height:44px;border-radius:12px;border:2px solid var(--bd);background:var(--sf2);font-size:22px;cursor:pointer;transition:all .15s;line-height:1" class="star-btn">★</button><?php endforeach;?></div>
      <div id="rlbl" style="font-size:13px;font-weight:700;color:var(--pr);min-height:18px"></div>
    </div>
    <div style="margin-bottom:14px"><div class="fl" style="margin-bottom:5px"><?=$lang==='ar'?'التعليق (اختياري)':'Commentaire (optionnel)'?></div><textarea name="commentaire" rows="3" class="fi" placeholder="<?=$lang==='ar'?'شارك تجربتك...':'Partagez votre expérience…'?>"></textarea></div>
    <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn bg" onclick="document.getElementById('rat-modal').classList.remove('open')"><?=$lang==='ar'?'إلغاء':'Annuler'?></button><button type="submit" class="btn bp">✓ <?=$lang==='ar'?'إرسال التقييم':'Envoyer l\'avis'?></button></div>
  </form></div></div>
</div>

<div class="tc"><?php if($toast):?><div class="toast <?=$toast['type']?>" id="php-toast"><?=$toast['type']==='ok'?'✅':'❌'?> <span><?=htmlspecialchars($toast['msg'])?></span></div><?php endif;?></div>

<script>
// Catalogue grid
let catSel=new Set();
function tc(label,id){
  const cb=label.querySelector('input[type="checkbox"]');const ck=label.querySelector('.cat-ck');if(!cb||!ck)return;
  if(cb.checked){cb.checked=false;catSel.delete(id);label.style.borderColor='var(--bd)';label.style.background='#fff';ck.style.background='#fff';ck.style.borderColor='var(--bd)';ck.textContent='';}
  else{cb.checked=true;catSel.add(id);label.style.borderColor='var(--pr)';label.style.background='var(--prl)';ck.style.background='var(--pr)';ck.style.borderColor='var(--pr)';ck.textContent='✓';}
  const n=catSel.size;const b=document.getElementById('cat-sub');const c=document.getElementById('cat-cnt');
  if(b){b.disabled=n===0;b.style.opacity=n===0?'.4':'1';}
  if(c){<?php if($lang==='ar'):?>c.textContent=n===0?'لم يتم اختيار أي خدمة':n+(n===1?' خدمة مختارة':' خدمات مختارة');<?php else:?>c.textContent=n===0?'Aucune prestation sélectionnée':n+' prestation'+(n>1?'s':'')+" sélectionnée"+(n>1?'s':'');<?php endif;?>}
}
// Profile prestation select
function pps(label){const cb=label.querySelector('input[type=checkbox]');const ck=label.querySelector('.ppc');const q=label.querySelector('.ppq');cb.checked=!cb.checked;if(cb.checked){if(ck){ck.style.background='var(--pr)';ck.style.borderColor='var(--pr)';ck.textContent='✓';}label.style.borderColor='var(--pr)';label.style.background='rgba(6,182,212,.06)';if(q)q.style.display='flex';}else{if(ck){ck.style.background='#fff';ck.style.borderColor='var(--bd)';ck.textContent='';}label.style.borderColor='var(--bd)';label.style.background='var(--sf2)';if(q)q.style.display='none';const qv=label.querySelector('.ppqv');const qi=label.querySelector('.ppqi');if(qv)qv.textContent='1';if(qi)qi.value='1';}pprc();}
function ppqc(btn,d){const label=btn.closest('label');const qv=label.querySelector('.ppqv');const qi=label.querySelector('.ppqi');if(!qv||!qi)return;let q=Math.max(1,parseInt(qi.value||1)+d);qi.value=q;qv.textContent=q;pprc();}
function pprc(){const ls=document.querySelectorAll('#pp-cat label,#pp-cus label');let tot=0;ls.forEach(l=>{const cb=l.querySelector('input[type=checkbox]');if(!cb||!cb.checked)return;const p=parseFloat(l.dataset.price||0);const q=parseInt(l.querySelector('.ppqi')?.value||1);tot+=p*q;});const te=document.getElementById('pp-tot');const tv=document.getElementById('pp-tv');const bf=document.getElementById('pp-bf');if(te)te.style.display=tot>0?'block':'none';if(tv)tv.textContent=tot.toLocaleString('fr-DZ')+' DA';if(bf)bf.style.display=tot>0?'block':'none';}
// Search results
function sp(label,pid,tid){const cb=label.querySelector('input[type=checkbox]');const ck=document.getElementById('pck-'+pid);const qw=document.getElementById('qw-'+pid);cb.checked=!cb.checked;if(cb.checked){if(ck){ck.style.background='var(--pr)';ck.style.borderColor='var(--pr)';ck.textContent='✓';}if(qw)qw.style.display='flex';label.style.borderColor='var(--pr)';label.style.background='rgba(6,182,212,.06)';}else{if(ck){ck.style.background='#fff';ck.style.borderColor='var(--bd)';ck.textContent='';}if(qw)qw.style.display='none';label.style.borderColor='var(--bd)';label.style.background='var(--sf2)';const qi=document.getElementById('qi-'+pid);const qv=document.getElementById('qv-'+pid);if(qi)qi.value=1;if(qv)qv.textContent='1';}rct(tid);}
function cq(pid,d,p,tid){const inp=document.getElementById('qi-'+pid);const val=document.getElementById('qv-'+pid);let q=Math.max(1,parseInt(inp.value||1)+d);inp.value=q;val.textContent=q;rct(tid);}
function rct(tid){let t=0;document.querySelectorAll('.pc-'+tid).forEach(cb=>{if(!cb.checked)return;const pid=cb.value;const q=parseInt(document.getElementById('qi-'+pid)?.value||1);const p=parseFloat(cb.closest('label')?.dataset.price||0);t+=q*p;});const te=document.getElementById('tot-'+tid);const tv=document.getElementById('tv-'+tid);const bf=document.getElementById('bf-'+tid);const any=[...document.querySelectorAll('.pc-'+tid)].some(c=>c.checked);if(te)te.style.display=any?'block':'none';if(tv)tv.textContent=t.toLocaleString('fr-DZ')+' DA';if(bf)bf.style.display=any?'block':'none';}
// Reservation edit
function ter(sid){const v=document.getElementById('rv-'+sid);const e=document.getElementById('re-'+sid);if(!v||!e)return;const ed=e.style.display!=='none';v.style.display=ed?'':'none';e.style.display=ed?'none':'';}
function trq(cb,sid,pid){const qw=document.getElementById('rqw-'+sid+'-'+pid);const lbl=document.getElementById('rpl-'+sid+'-'+pid);if(qw)qw.style.display=cb.checked?'flex':'none';if(lbl)lbl.style.background=cb.checked?'rgba(6,182,212,.06)':'var(--sf2)';}
function crq(sid,pid,d){const inp=document.getElementById('rqi-'+sid+'-'+pid);const val=document.getElementById('rqv-'+sid+'-'+pid);if(!inp||!val)return;let q=Math.max(1,parseInt(inp.value||1)+d);inp.value=q;val.textContent=q;}
// Profile edit toggle
function toggleProfileEdit(showEdit){const summary=document.getElementById('profile-summary-wrapper');const form=document.getElementById('profile-form-wrapper');if(!summary||!form)return;const open=typeof showEdit==='boolean'?showEdit:form.style.display==='none';summary.style.display=open?'none':'block';form.style.display=open?'block':'none';}
// Rating
const RL={1:'😞 <?=$lang==="ar"?"سيء":"Mauvais"?>',2:'😕 <?=$lang==="ar"?"مقبول":"Passable"?>',3:'😐 <?=$lang==="ar"?"جيد":"Correct"?>',4:'😊 <?=$lang==="ar"?"ممتاز":"Bien"?>',5:'🤩 <?=$lang==="ar"?"رائع !":"Excellent !"?>'};
function setr(v){document.getElementById('r-note').value=v;document.querySelectorAll('.star-btn').forEach((b,i)=>{const on=i<v;b.style.background=on?'#fef3c7':'var(--sf2)';b.style.borderColor=on?'#f59e0b':'var(--bd)';b.style.color=on?'#f59e0b':'#d1d5db';b.style.transform=on?'scale(1.1)':'scale(1)';});document.getElementById('rlbl').textContent=RL[v]||'';}
function orat(sid,tid,tn){document.getElementById('r-svc').value=sid;document.getElementById('r-tech').value=tid;document.getElementById('rat-title').textContent='⭐ <?=$lang==="ar"?"تقييم":"Évaluer"?> — '+tn;setr(5);document.getElementById('rat-modal').classList.add('open');}
const cmx=document.getElementById('chat-msgs');if(cmx)cmx.scrollTop=cmx.scrollHeight;
const pt=document.getElementById('php-toast');if(pt)setTimeout(()=>{pt.style.opacity='0';pt.style.transition='opacity .4s';setTimeout(()=>pt.remove(),400)},3500);
</script>
<script>window.KhidmaRT={role:'client',base:'',myId:<?=$user_id?>,me:{initials:'<?=ini($client["nom"],$client["prenom"])?>'},seed:{notif_id:<?=(int)$seed_notif_id?>,msg_id:<?=(int)$seed_msg_id?>}};</script>
<script src="js/khidma-realtime.js"></script>
<script src="js/khidma-pwa.js" defer></script>
<div class="sbo" id="sbo" onclick="closeSb()"></div>
<nav class="mn">
  <a href="?tab=accueil" class="mnt <?=$active_tab==='accueil'?'active':''?>"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg><?=$lang==='ar'?'الرئيسية':'Accueil'?></a>
  <a href="?tab=recherche" class="mnt <?=$active_tab==='recherche'?'active':''?>"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><?=$lang==='ar'?'بحث':'Recherche'?></a>
  <a href="?tab=reservations" class="mnt <?=$active_tab==='reservations'?'active':''?>"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><?=$lang==='ar'?'حجوزاتي':'Réservations'?></a>
  <a href="?tab=messages" class="mnt <?=$active_tab==='messages'?'active':''?>"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><?php if($unread_msg>0):?><span class="mnb"><?=$unread_msg?></span><?php endif;?><?=$lang==='ar'?'الرسائل':'Messages'?></a>
  <a href="?tab=notifications" class="mnt <?=$active_tab==='notifications'?'active':''?>"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><?php if($notif_count>0):?><span class="mnb"><?=$notif_count?></span><?php endif;?><?=$lang==='ar'?'إشعارات':'Notifs'?></a>
</nav>
<!-- ══ TECH MAP MODAL ══ -->
<div id="tech-map-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.45);backdrop-filter:blur(6px);align-items:center;justify-content:center;">
  <div style="background:var(--sf);border-radius:20px;width:min(720px,96vw);max-height:90vh;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.18);display:flex;flex-direction:column;">
    <!-- Header -->
    <div style="padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--bd);background:var(--sf2);flex-shrink:0;">
      <div>
        <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:16px;color:var(--tx)"><?=$lang==='ar'?'🗺 خريطة التقنيين المتاحين':'🗺 Techniciens disponibles en direct'?></div>
        <div style="font-size:12px;color:var(--mu);margin-top:3px" id="map-subtitle"><?=$lang==='ar'?'يتحديث تلقائياً كل 60 ثانية':'Se met à jour automatiquement toutes les 60s'?></div>
      </div>
      <button onclick="closeTechMap()" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--bd);background:var(--sf);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--mu)">✕</button>
    </div>
    <!-- Legend -->
    <div style="padding:10px 22px;display:flex;gap:16px;flex-wrap:wrap;border-bottom:1px solid var(--bd);background:var(--sf);flex-shrink:0;">
      <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--tx)"><span style="width:12px;height:12px;border-radius:50%;background:#16a34a;display:inline-block;box-shadow:0 0 0 3px rgba(22,163,74,.2)"></span><?=$lang==='ar'?'متاح الآن':'Disponible maintenant'?></div>
      <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--tx)"><span style="width:12px;height:12px;border-radius:50%;background:#f59e0b;display:inline-block"></span><?=$lang==='ar'?'في تدخل':'En intervention'?></div>
      <div id="map-count" style="margin-left:auto;font-size:12px;font-weight:700;color:var(--pr)"></div>
    </div>
    <!-- Map -->
    <div style="position:relative;flex:1;min-height:420px;">
      <div id="leaflet-map" style="width:100%;height:100%;min-height:420px;"></div>
      <!-- Empty state: shown when no techs have location active -->
      <div id="map-no-techs" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.88);z-index:800;flex-direction:column;align-items:center;justify-content:center;gap:10px;backdrop-filter:blur(4px);">
        <div style="font-size:40px">📍</div>
        <div style="font-weight:700;font-size:15px;color:var(--tx)"><?=$lang==='ar'?'لا يوجد تقنيون متاحون الآن':'Aucun technicien disponible en ce moment'?></div>
        <div style="font-size:13px;color:var(--mu);text-align:center;max-width:280px"><?=$lang==='ar'?'التقنيون الذين يفعّلون موقعهم سيظهرون هنا تلقائياً.':'Les techniciens qui activent leur localisation apparaîtront ici automatiquement.'?></div>
      </div>
    </div>
  </div>
</div>

<!-- Leaflet CSS + JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ── Tech location data from PHP ──
const techMapData = <?php
  // Get IDs of techs currently "en cours"
  $busy_q = $pdo->query("SELECT DISTINCT id_technicien FROM service WHERE statut_service='en cours'");
  $busy_ids = array_flip(array_column($busy_q->fetchAll(), 'id_technicien'));

  $map_techs = array_filter($all_techs, fn($t) =>
    !empty($t['latitude']) && !empty($t['longitude']) && !empty($t['localisation_active'])
  );
  echo json_encode(array_values(array_map(fn($t) => [
    'id'      => $t['id_technicien'],
    'tuid'    => $t['tuid'],
    'name'    => htmlspecialchars($t['prenom'].' '.$t['nom'], ENT_QUOTES),
    'ini'     => strtoupper(substr($t['prenom']??'',0,1).substr($t['nom']??'',0,1)),
    'spec'    => htmlspecialchars(svc_label($t['specialité']??'',$lang)?:($t['specialité']??''), ENT_QUOTES),
    'ville'   => htmlspecialchars($t['ville']??'', ENT_QUOTES),
    'note'    => round($t['avg_note'],1),
    'nb_avis' => intval($t['nb_avis']),
    'available_now' => !empty($t['available_now']),
    'lat'     => floatval($t['latitude']),
    'lng'     => floatval($t['longitude']),
    'busy'    => isset($busy_ids[$t['id_technicien']]),
  ], $map_techs)));
?>;

const IS_AR = <?=$lang==='ar'?'true':'false'?>;
const PURPLE_CSS = getComputedStyle(document.documentElement).getPropertyValue('--pr').trim() || '#7c3aed';

let mapInstance = null;
let mapMarkers  = {};
let mapRefreshTimer = null;

function buildIcon(ini, busy) {
  const color = busy === 'busy' ? '#f59e0b' : (busy === 'available' ? '#16a34a' : '#9ca3af');
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="44" height="52" viewBox="0 0 44 52">
    <filter id="sh"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-opacity=".25"/></filter>
    <ellipse cx="22" cy="49" rx="8" ry="3" fill="rgba(0,0,0,.15)"/>
    <path d="M22 2C13.16 2 6 9.16 6 18c0 11.5 16 32 16 32s16-20.5 16-32C38 9.16 30.84 2 22 2z"
          fill="${color}" filter="url(#sh)"/>
    <circle cx="22" cy="18" r="11" fill="white" opacity=".95"/>
    <text x="22" y="22" text-anchor="middle" font-family="system-ui,sans-serif"
          font-size="9" font-weight="800" fill="${color}">${ini}</text>
    ${busy === 'busy' ? '<circle cx="35" cy="6" r="5" fill="#ef4444" stroke="white" stroke-width="1.5"/>' : ''}
  </svg>`;
  return L.divIcon({
    html: svg,
    iconSize: [44, 52],
    iconAnchor: [22, 52],
    popupAnchor: [0, -54],
    className: ''
  });
}

function buildPopup(t) {
  const stars = '★'.repeat(Math.round(t.note)) + '☆'.repeat(5 - Math.round(t.note));
  const statusLabel = t.busy
    ? (IS_AR ? '🔴 في تدخل' : '🔴 En intervention')
    : (t.available_now ? (IS_AR ? '🟢 متاح الآن' : '🟢 Disponible maintenant') : (IS_AR ? '⛔ غير متاح' : '⛔ Indisponible'));
  return `<div style="font-family:system-ui,sans-serif;min-width:190px;padding:4px 2px">
    <div style="display:flex;align-items:center;gap:9px;margin-bottom:8px">
      <div style="width:36px;height:36px;border-radius:50%;background:${t.busy?'#f59e0b':(t.available_now?'#16a34a':'#9ca3af')};display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;color:#fff;flex-shrink:0">${t.ini}</div>
      <div>
        <div style="font-weight:700;font-size:14px;color:#111">${t.name}</div>
        <div style="font-size:11px;color:#7c3aed;font-weight:600">${t.spec}</div>
      </div>
    </div>
    <div style="font-size:12px;color:#6b7280;margin-bottom:4px">📍 ${t.ville}</div>
    <div style="font-size:12px;color:#f59e0b;margin-bottom:4px">${stars} <span style="color:#6b7280">(${t.nb_avis} ${IS_AR?'تقييم':'avis'})</span></div>
    <div style="font-size:11px;font-weight:600;margin-bottom:10px">${statusLabel}</div>
    <a href="?tab=recherche&tech=${t.id}"
       style="display:block;text-align:center;background:#7c3aed;color:#fff;border-radius:8px;padding:7px 12px;font-size:12px;font-weight:700;text-decoration:none;">
      ${IS_AR ? '← عرض الملف' : 'Voir le profil →'}
    </a>
  </div>`;
}

function initMap() {
  if (mapInstance) return;

  mapInstance = L.map('leaflet-map', {
    center: [28.0339, 1.6596], // Algeria center
    zoom: 5,
    zoomControl: true,
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 18
  }).addTo(mapInstance);

  renderMarkers(techMapData);
  updateMapCount();
}

function renderMarkers(techs) {
  // Remove old markers
  Object.values(mapMarkers).forEach(m => mapInstance.removeLayer(m));
  mapMarkers = {};

  const bounds = [];
  techs.forEach(t => {
    if (!t.lat || !t.lng) return;
    const marker = L.marker([t.lat, t.lng], { icon: buildIcon(t.ini, t.busy ? 'busy' : (t.available_now ? 'available' : 'off')) })
      .addTo(mapInstance)
      .bindPopup(buildPopup(t), { maxWidth: 240, className: 'tech-popup' });
    mapMarkers[t.id] = marker;
    bounds.push([t.lat, t.lng]);
  });

  if (bounds.length > 0) {
    mapInstance.fitBounds(bounds, { padding: [50, 50], maxZoom: 11 });
  } else {
    // No techs online — show Algeria centered
    mapInstance.setView([28.0339, 1.6596], 5);
    // Show empty state overlay
    const el = document.getElementById('map-no-techs');
    if (el) el.style.display = 'flex';
  }

  updateMapCount();
}

function updateMapCount() {
  const el = document.getElementById('map-count');
  if (!el) return;
  const total = Object.keys(mapMarkers).length;
  el.textContent = IS_AR
    ? `${total} تقني${total !== 1 ? '' : ''} على الخريطة`
    : `${total} technicien${total !== 1 ? 's' : ''} sur la carte`;
}

async function refreshMapLocations() {
  try {
    const res = await fetch('actions/get_tech_location.php');
    if (!res.ok) return;
    const data = await res.json();
    if (!Array.isArray(data)) return;

    // Normalise field names from the existing API response
    const normalised = data.map(t => ({
      id   : t.id_technicien,
      ini  : ((t.prenom||'').charAt(0) + (t.nom||'').charAt(0)).toUpperCase(),
      name : ((t.prenom||'') + ' ' + (t.nom||'')).trim(),
      spec : t.specialité || '',
      ville: t.ville || '',
      note : parseFloat(t.avg_note||0),
      nb_avis: parseInt(t.nb_avis||0),
      available_now: !!parseInt(t.available_now||0),
      lat  : parseFloat(t.latitude),
      lng  : parseFloat(t.longitude),
      busy : false,
    }));

    normalised.forEach(t => {
      if (!t.lat || !t.lng) return;
      const existing = mapMarkers[t.id];
      if (existing) {
        existing.setLatLng([t.lat, t.lng]);
        existing.setIcon(buildIcon(t.ini, t.busy ? 'busy' : (t.available_now ? 'available' : 'off')));
        existing.setPopupContent(buildPopup(t));
      } else {
        const marker = L.marker([t.lat, t.lng], { icon: buildIcon(t.ini, t.busy ? 'busy' : (t.available_now ? 'available' : 'off')) })
          .addTo(mapInstance)
          .bindPopup(buildPopup(t), { maxWidth: 240 });
        mapMarkers[t.id] = marker;
      }
    });

    // Remove techs that went offline
    const liveIds = new Set(normalised.map(t => t.id));
    Object.keys(mapMarkers).forEach(id => {
      if (!liveIds.has(parseInt(id))) {
        mapInstance.removeLayer(mapMarkers[id]);
        delete mapMarkers[id];
      }
    });

    updateMapCount();
  } catch(e) { console.warn('Map refresh error:', e); }
}

function openTechMap() {
  const modal = document.getElementById('tech-map-modal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  // Init map on first open
  setTimeout(() => {
    initMap();
    mapInstance.invalidateSize();
  }, 80);

  // Start auto-refresh every 60s
  if (mapRefreshTimer) clearInterval(mapRefreshTimer);
  mapRefreshTimer = setInterval(refreshMapLocations, 60000);
}

function closeTechMap() {
  document.getElementById('tech-map-modal').style.display = 'none';
  document.body.style.overflow = '';
  if (mapRefreshTimer) { clearInterval(mapRefreshTimer); mapRefreshTimer = null; }
}

// Close on backdrop click
document.getElementById('tech-map-modal').addEventListener('click', function(e) {
  if (e.target === this) closeTechMap();
});
</script>

<!-- Leaflet popup custom style -->
<style>
.tech-popup .leaflet-popup-content-wrapper {
  border-radius: 14px !important;
  box-shadow: 0 8px 30px rgba(0,0,0,.15) !important;
  border: 1px solid #e8e4f8 !important;
  padding: 0 !important;
}
.tech-popup .leaflet-popup-content {
  margin: 14px !important;
}
.tech-popup .leaflet-popup-tip-container { display: none; }
</style>

<script>function openSb(){document.querySelector('.sb').classList.add('open');document.getElementById('sbo').classList.add('open');document.body.style.overflow='hidden';}function closeSb(){document.querySelector('.sb').classList.remove('open');document.getElementById('sbo').classList.remove('open');document.body.style.overflow='';}</script>
</body>
</html>