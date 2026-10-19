<?php

session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/lang.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$lang = 'fr';
$dir  = 'ltr';

$admin_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'approve_tech' && isset($_POST['id_technicien'])) {
    $pdo->prepare("UPDATE technicien SET verifie = 1, date_validation = CURDATE() WHERE id_technicien = ?")->execute([$_POST['id_technicien']]);
    $pdo->prepare("UPDATE utilisateur SET statut_compte = 'actif' WHERE id_user = (SELECT id_user FROM technicien WHERE id_technicien = ?)")->execute([$_POST['id_technicien']]);
    // Notify tech
    $tu = $pdo->prepare("SELECT u.id_user, u.nom, u.prenom, u.email FROM technicien t JOIN utilisateur u ON u.id_user=t.id_user WHERE t.id_technicien=?");
    $tu->execute([$_POST['id_technicien']]); $tr=$tu->fetch();
    if ($tr) {
      notifier($pdo,$tr['id_user'],'Profil approuvé ✓','Votre profil a été validé. Vous pouvez maintenant recevoir des réservations.','dashboard-tech.php');
      require_once 'includes/mailer.php';
      send_tech_approved($tr['email'], $tr['nom'], $tr['prenom']);
    }
    log_action($pdo,$admin_id,'approve_tech','Tech #'.$_POST['id_technicien']);
    $_SESSION['toast'] = ['msg' => 'Technicien approuvé ✓', 'type' => 'ok'];
  }
  if ($action === 'reject_tech' && isset($_POST['id_technicien'])) {
    $row = $pdo->prepare("SELECT id_user FROM technicien WHERE id_technicien = ?");
    $row->execute([$_POST['id_technicien']]);
    $uid = $row->fetchColumn();
    if ($uid) {
      $pdo->prepare("DELETE FROM signalement WHERE id_user=? OR id_signaleur=?")->execute([$uid,$uid]);
      $pdo->prepare("DELETE FROM notification WHERE id_user=?")->execute([$uid]);
      $pdo->prepare("DELETE FROM message WHERE id_expediteur=? OR id_destinataire=?")->execute([$uid,$uid]);
      $pdo->prepare("DELETE FROM log_action WHERE id_user=?")->execute([$uid]);
      $pdo->prepare("DELETE FROM technicien WHERE id_technicien=?")->execute([$_POST['id_technicien']]);
      $pdo->prepare("DELETE FROM utilisateur WHERE id_user=?")->execute([$uid]);
    }
    log_action($pdo,$admin_id,'reject_tech','Tech #'.$_POST['id_technicien'].' supprimé');
    $tu = $pdo->prepare("SELECT id_user FROM technicien WHERE id_technicien=?"); $tu->execute([$_POST['id_technicien']]); $tr=$tu->fetch();
    if ($tr) notifier($pdo,$tr['id_user'],'Profil refusé','Votre profil n\'a pas été validé. Contactez l\'administration.','dashboard-tech.php');
    log_action($pdo,$admin_id,'reject_tech','Tech #'.$_POST['id_technicien']);
    $_SESSION['toast'] = ['msg' => 'Technicien rejeté', 'type' => 'err'];
  }
  if ($action === 'suspend_user' && isset($_POST['id_user'])) {
    $raison = trim($_POST['raison']??'');
    $pdo->prepare("UPDATE utilisateur SET statut_compte='suspendu', raison_suspension=? WHERE id_user=?")->execute([$raison,$_POST['id_user']]);
    notifier($pdo,$_POST['id_user'],'Compte suspendu','Votre compte a été suspendu'.($raison?' : '.$raison:'.'));
    log_action($pdo,$admin_id,'suspend_user','User #'.$_POST['id_user'].' raison: '.$raison);
    $_SESSION['toast'] = ['msg' => 'Utilisateur suspendu', 'type' => 'err'];
  }
  if ($action === 'activate_user' && isset($_POST['id_user'])) {
    $pdo->prepare("UPDATE utilisateur SET statut_compte='actif', raison_suspension=NULL WHERE id_user=?")->execute([$_POST['id_user']]);
    notifier($pdo,$_POST['id_user'],'Compte réactivé ✓','Votre compte est de nouveau actif.');
    log_action($pdo,$admin_id,'activate_user','User #'.$_POST['id_user']);
    $_SESSION['toast'] = ['msg' => 'Utilisateur réactivé', 'type' => 'ok'];
  }
  if ($action === 'delete_user' && isset($_POST['id_user'])) {
    $uid = intval($_POST['id_user']);
    $pdo->prepare("DELETE FROM notification WHERE id_user=?")->execute([$uid]);
    $pdo->prepare("DELETE FROM message WHERE id_expediteur=? OR id_destinataire=?")->execute([$uid,$uid]);
    $pdo->prepare("DELETE FROM signalement WHERE id_user=? OR id_signaleur=?")->execute([$uid,$uid]);
    $pdo->prepare("DELETE FROM log_action WHERE id_user=?")->execute([$uid]);
    $cid=$pdo->prepare("SELECT id_client FROM client WHERE id_user=?");$cid->execute([$uid]);$crow=$cid->fetch();
    $tid=$pdo->prepare("SELECT id_technicien FROM technicien WHERE id_user=?");$tid->execute([$uid]);$trow=$tid->fetch();
    if($crow){ $pdo->prepare("DELETE FROM avis WHERE id_client=?")->execute([$crow['id_client']]); $pdo->prepare("DELETE FROM litige WHERE id_client=?")->execute([$crow['id_client']]); $pdo->prepare("DELETE FROM service WHERE id_client=?")->execute([$crow['id_client']]); $pdo->prepare("DELETE FROM client WHERE id_user=?")->execute([$uid]); }
    if($trow){ $pdo->prepare("DELETE FROM avis WHERE id_technicien=?")->execute([$trow['id_technicien']]); $pdo->prepare("DELETE FROM litige WHERE id_technicien=?")->execute([$trow['id_technicien']]); $pdo->prepare("DELETE FROM prestation WHERE id_technicien=?")->execute([$trow['id_technicien']]); $pdo->prepare("DELETE FROM disponibilite WHERE id_technicien=?")->execute([$trow['id_technicien']]); $pdo->prepare("DELETE FROM service WHERE id_technicien=?")->execute([$trow['id_technicien']]); $pdo->prepare("DELETE FROM technicien WHERE id_user=?")->execute([$uid]); }
    $pdo->prepare("DELETE FROM utilisateur WHERE id_user=?")->execute([$uid]);
    log_action($pdo,$admin_id,'delete_user','User #'.$uid);
    $_SESSION['toast'] = ['msg' => 'Utilisateur supprimé', 'type' => 'err'];
  }
  if ($action === 'resolve_litige' && isset($_POST['id_litige'])) {
    $statut     = $_POST['statut_litige'] ?? 'resolu';
    $resolution = trim($_POST['resolution'] ?? '');
    $pdo->prepare("UPDATE litige SET statut_litige=?, resolution=?, date_resolution=CURDATE() WHERE id_litige=?")->execute([$statut,$resolution,$_POST['id_litige']]);
    $l=$pdo->prepare("SELECT l.*,uc.id_user as cuid,ut.id_user as tuid FROM litige l JOIN client c ON c.id_client=l.id_client JOIN utilisateur uc ON uc.id_user=c.id_user JOIN technicien t ON t.id_technicien=l.id_technicien JOIN utilisateur ut ON ut.id_user=t.id_user WHERE l.id_litige=?");
    $l->execute([$_POST['id_litige']]); $lit=$l->fetch();
    if ($lit) {
      notifier($pdo,$lit['cuid'],'Litige #'.$_POST['id_litige'].' traité','Résolution : '.($resolution?:'Voir litiges'),'dashboard-client.php?tab=litiges');
      notifier($pdo,$lit['tuid'],'Litige #'.$_POST['id_litige'].' traité','L\'administrateur a traité ce litige.','dashboard-tech.php');
    }
    log_action($pdo,$admin_id,'resolve_litige','Litige #'.$_POST['id_litige'].' → '.$statut);
    $_SESSION['toast'] = ['msg' => 'Litige mis à jour', 'type' => 'ok'];
  }
  if ($action === 'hide_avis' && isset($_POST['id_avis'])) {
    $pdo->prepare("UPDATE avis SET visible=0 WHERE id_avis=?")->execute([$_POST['id_avis']]);
    log_action($pdo,$admin_id,'hide_avis','Avis #'.$_POST['id_avis']);
    $_SESSION['toast'] = ['msg' => 'Avis masqué', 'type' => 'ok'];
  }
  if ($action === 'delete_avis' && isset($_POST['id_avis'])) {
    $pdo->prepare("DELETE FROM avis WHERE id_avis=?")->execute([$_POST['id_avis']]);
    log_action($pdo,$admin_id,'delete_avis','Avis #'.$_POST['id_avis']);
    $_SESSION['toast'] = ['msg' => 'Avis supprimé', 'type' => 'ok'];
  }
  if ($action === 'restore_avis' && isset($_POST['id_avis'])) {
    $pdo->prepare("UPDATE avis SET visible=1 WHERE id_avis=?")->execute([$_POST['id_avis']]);
    log_action($pdo,$admin_id,'restore_avis','Avis #'.$_POST['id_avis']);
    $_SESSION['toast'] = ['msg' => 'Avis restauré', 'type' => 'ok'];
  }
  if ($action === 'handle_signalement' && isset($_POST['id_signalement'])) {
    $statut = $_POST['statut_signal'] ?? 'traité';
    $pdo->prepare("UPDATE signalement SET statut_signal=? WHERE id_signalement=?")->execute([$statut,$_POST['id_signalement']]);
    log_action($pdo,$admin_id,'handle_signal','Signalement #'.$_POST['id_signalement'].' → '.$statut);
    $_SESSION['toast'] = ['msg' => 'Signalement traité', 'type' => 'ok'];
  }
  if ($action === 'gen_facture' && isset($_POST['id_service'])) {
    $id_svc = intval($_POST['id_service']);
    $calc = $pdo->prepare("SELECT COALESCE(SUM(p.prix_unitaire * sp.quantite),0) FROM service_prestation sp JOIN prestation p ON p.id_prestation=sp.id_prestation WHERE sp.id_service=?");
    $calc->execute([$id_svc]); $montant = floatval($calc->fetchColumn());
    if ($montant <= 0) $montant = floatval($_POST['montant']??0);
    if ($montant < facture_tva_threshold()) {
    $_SESSION['toast'] = ['msg' => 'Montant inférieur à 5000 DA, aucune facture n’est nécessaire.', 'type' => 'ok'];
    header("Location: dashboard-admin.php?tab=factures");
    exit();
    }
    $chk=$pdo->prepare("SELECT id_facture FROM facture WHERE id_service=?"); $chk->execute([$id_svc]);
    if (!$chk->fetch()) {
      $pdo->prepare("INSERT INTO facture (id_service,date_facture,montant_total,statut_facture) VALUES (?,CURDATE(),?,'émise')")->execute([$id_svc,$montant]);
      $pdo->prepare("UPDATE service SET prix_final=? WHERE id_service=?")->execute([$montant,$id_svc]);
      log_action($pdo,$admin_id,'gen_facture','Service #'.$id_svc.' montant '.$montant.' DA (auto)');
      $_SESSION['toast'] = ['msg' => 'Facture générée ✓ ('.number_format($montant,0).' DA)', 'type' => 'ok'];
    } else { $_SESSION['toast'] = ['msg' => 'Facture déjà existante', 'type' => 'err']; }
  }
  if ($action === 'send_message' && !empty($_POST['id_destinataire']) && !empty($_POST['contenu'])) {
    $pdo->prepare("INSERT INTO message (id_message, id_expediteur, id_destinataire, contenu, data_envoi, lu) SELECT COALESCE(MAX(id_message),0)+1, ?, ?, ?, NOW(), 0 FROM message")->execute([$admin_id, $_POST['id_destinataire'], $_POST['contenu']]);
    log_action($pdo,$admin_id,'send_message','To user #'.$_POST['id_destinataire']);
  }
}

$tech_all = $pdo->query("\n    SELECT t.*, u.nom, u.prenom, u.email, u.telephone, u.ville, u.statut_compte, u.date_inscription,\n           AVG(a.note) as avg_note\n    FROM technicien t\n    JOIN utilisateur u ON u.id_user = t.id_user\n    LEFT JOIN avis a ON a.id_technicien = t.id_technicien\n    GROUP BY t.id_technicien, u.id_user, u.nom, u.prenom, u.email, u.telephone, u.ville, u.statut_compte, u.date_inscription\n    ORDER BY u.date_inscription DESC\n")->fetchAll();

// Technicians pending validation
$tech_pending = $pdo->query("\n    SELECT t.*, u.nom, u.prenom, u.email, u.telephone, u.ville, u.date_inscription\n    FROM technicien t\n    JOIN utilisateur u ON u.id_user = t.id_user\n    WHERE t.verifie = 0\n    ORDER BY u.date_inscription ASC\n")->fetchAll();

$clients_all = $pdo->query("\n    SELECT u.id_user, u.nom, u.prenom, u.email, u.telephone, u.ville, u.statut_compte, u.date_inscription, COUNT(s.id_service) as nb_reservations\n    FROM utilisateur u\n    LEFT JOIN client c ON c.id_user = u.id_user\n    LEFT JOIN service s ON s.id_client = c.id_client\n    WHERE u.role = 'client'\n    GROUP BY u.id_user ORDER BY u.date_inscription DESC\n")->fetchAll();

$services_recent = $pdo->query("
    SELECT s.id_service, s.date_service, s.statut_service, s.prix_estime, s.prix_final, s.description,
           uc.nom as client_nom, uc.prenom as client_prenom,
           ut.nom as tech_nom, ut.prenom as tech_prenom, t.specialité
    FROM service s
    JOIN client c ON s.id_client = c.id_client
    JOIN utilisateur uc ON c.id_user = uc.id_user
    JOIN technicien t ON s.id_technicien = t.id_technicien
    JOIN utilisateur ut ON t.id_user = ut.id_user
    ORDER BY s.date_service DESC LIMIT 50
")->fetchAll();

// Fetch prestations for all services shown
$admin_svc_ids = array_values(array_column($services_recent,'id_service'));
$admin_svc_prests = [];
if (!empty($admin_svc_ids)) {
    $in = implode(',', array_fill(0, count($admin_svc_ids), '?'));
    $sp = $pdo->prepare("SELECT sp.id_service, pr.nom, pr.prix_unitaire FROM service_prestation sp JOIN prestation pr ON pr.id_prestation=sp.id_prestation WHERE sp.id_service IN ($in)");
    $sp->execute($admin_svc_ids);
    foreach ($sp->fetchAll() as $row) { $admin_svc_prests[(int)$row['id_service']][] = $row; }
}
$litiges_open = $pdo->query("
    SELECT l.id_litige, l.description_litige, l.statut_litige, l.date_overture,
           s.id_service, s.prix_estime, s.prix_final,
           uc.nom as client_nom, uc.prenom as client_prenom, uc.id_user as client_uid,
           ut.nom as tech_nom, ut.prenom as tech_prenom
    FROM litige l
    JOIN service s ON l.id_service = s.id_service
    JOIN client c ON s.id_client = c.id_client
    JOIN utilisateur uc ON c.id_user = uc.id_user
    JOIN technicien t ON s.id_technicien = t.id_technicien
    JOIN utilisateur ut ON t.id_user = ut.id_user
    WHERE l.statut_litige != 'résolu' ORDER BY l.date_overture DESC
")->fetchAll();

$admin_id = $_SESSION['user_id'];

// --- Fetch real data ---
$conv_stmt = $pdo->prepare("
    SELECT DISTINCT
        CASE WHEN m.id_expediteur = ? THEN m.id_destinataire ELSE m.id_expediteur END as other_id,
        u.nom, u.prenom, u.role, MAX(m.data_envoi) as last_date,
        SUM(CASE WHEN m.lu=0 AND m.id_destinataire=? THEN 1 ELSE 0 END) as unread
    FROM message m
    JOIN utilisateur u ON u.id_user = CASE WHEN m.id_expediteur=? THEN m.id_destinataire ELSE m.id_expediteur END
    WHERE m.id_expediteur=? OR m.id_destinataire=?
    GROUP BY other_id ORDER BY last_date DESC
");
$conv_stmt->execute([$admin_id,$admin_id,$admin_id,$admin_id,$admin_id]);
$conversations = $conv_stmt->fetchAll();
$all_users = $pdo->query("SELECT id_user, nom, prenom, role FROM utilisateur WHERE role != 'admin' ORDER BY nom")->fetchAll();

$monthly = $pdo->query("
    SELECT DATE_FORMAT(date_service,'%b') as month, COUNT(*) as total
    FROM service WHERE date_service >= DATE_SUB(CURDATE(), INTERVAL 8 MONTH)
    GROUP BY YEAR(date_service), MONTH(date_service) ORDER BY date_service ASC LIMIT 8
")->fetchAll();

$service_types = $pdo->query("
    SELECT t.specialité, COUNT(s.id_service) as total
    FROM service s JOIN technicien t ON s.id_technicien = t.id_technicien
    GROUP BY t.specialité ORDER BY total DESC LIMIT 5
")->fetchAll();
$total_svc_pct = array_sum(array_column($service_types,'total')) ?: 1;

$admin_info_stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_user = ?");
$admin_info_stmt->execute([$admin_id]);
$admin_info = $admin_info_stmt->fetch();

// Avis moderation
$avis_all = $pdo->query("
    SELECT a.*, uc.nom as c_nom, uc.prenom as c_prenom,
           ut.nom as t_nom, ut.prenom as t_prenom, t.specialité
    FROM avis a
    JOIN utilisateur uc ON uc.id_user=(SELECT id_user FROM client WHERE id_client=a.id_client LIMIT 1)
    JOIN technicien t ON t.id_technicien=a.id_technicien
    JOIN utilisateur ut ON ut.id_user=t.id_user
    ORDER BY a.date_avis DESC LIMIT 100
")->fetchAll();

// Signalements
$signalements = $pdo->query("
    SELECT s.*, u.nom as rep_nom, u.prenom as rep_prenom, u.role as rep_role,
           us.nom as sig_nom, us.prenom as sig_prenom
    FROM signalement s
    JOIN utilisateur u ON u.id_user=s.id_user
    LEFT JOIN utilisateur us ON us.id_user=s.id_signaleur
    ORDER BY s.date_signalement DESC LIMIT 100
")->fetchAll();

// Logs
$logs = $pdo->query("
    SELECT l.*, u.nom, u.prenom, u.role
    FROM log_action l
    LEFT JOIN utilisateur u ON u.id_user=l.id_user
    ORDER BY l.date_action DESC LIMIT 200
")->fetchAll();

// Services needing facture
$services_no_facture = $pdo->query("
    SELECT s.id_service, s.prix_final, s.prix_estime, s.date_service,
           uc.nom as c_nom, uc.prenom as c_prenom,
           ut.nom as t_nom, ut.prenom as t_prenom, t.specialité
    FROM service s
    JOIN client c ON c.id_client=s.id_client
    JOIN utilisateur uc ON uc.id_user=c.id_user
    JOIN technicien t ON t.id_technicien=s.id_technicien
    JOIN utilisateur ut ON ut.id_user=t.id_user
    LEFT JOIN facture f ON f.id_facture=(SELECT MAX(f2.id_facture) FROM facture f2 WHERE f2.id_service=s.id_service)
  WHERE s.statut_service='terminé' AND f.id_facture IS NULL AND COALESCE(s.prix_final, s.prix_estime, 0) >= 5000
    ORDER BY s.date_service DESC
")->fetchAll();

// Factures already generated
$factures = $pdo->query("
    SELECT f.*, s.date_service, s.description,
           uc.nom as c_nom, uc.prenom as c_prenom,
           ut.nom as t_nom, ut.prenom as t_prenom
    FROM facture f
    JOIN service s ON s.id_service=f.id_service
    JOIN client c ON c.id_client=s.id_client
    JOIN utilisateur uc ON uc.id_user=c.id_user
    JOIN technicien t ON t.id_technicien=s.id_technicien
    JOIN utilisateur ut ON ut.id_user=t.id_user
    ORDER BY f.date_facture DESC LIMIT 100
")->fetchAll();

// Prestations for factures
$fac_ids = array_values(array_column($factures,'id_service'));
$admin_fac_prests = [];
if (!empty($fac_ids)) {
    $in = implode(',', array_fill(0, count($fac_ids), '?'));
    $sp = $pdo->prepare("SELECT sp.id_service, pr.nom FROM service_prestation sp JOIN prestation pr ON pr.id_prestation=sp.id_prestation WHERE sp.id_service IN ($in)");
    $sp->execute($fac_ids);
    foreach ($sp->fetchAll() as $row) { $admin_fac_prests[(int)$row['id_service']][] = $row['nom']; }
}
$stats['signalements'] = $pdo->query("SELECT COUNT(*) FROM signalement WHERE statut_signal='en attente'")->fetchColumn();
$stats['avis_pending'] = $pdo->query("SELECT COUNT(*) FROM avis WHERE visible=1")->fetchColumn();
$stats['en_attente'] = $pdo->query("SELECT COUNT(*) FROM technicien WHERE verifie=0")->fetchColumn();
$stats['litiges'] = $pdo->query("SELECT COUNT(*) FROM litige WHERE statut_litige != 'résolu'")->fetchColumn();
$stats['techniciens'] = $pdo->query("SELECT COUNT(*) FROM technicien")->fetchColumn();
$stats['clients'] = $pdo->query("SELECT COUNT(*) FROM client")->fetchColumn();
$stats['services'] = $pdo->query("SELECT COUNT(*) FROM service")->fetchColumn();
$stats['new_this_month_tech'] = $pdo->query("SELECT COUNT(*) FROM utilisateur u JOIN technicien t ON t.id_user=u.id_user WHERE u.role='technicien' AND DATE_FORMAT(u.date_inscription,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();
$stats['new_this_month_client'] = $pdo->query("SELECT COUNT(*) FROM utilisateur u JOIN client c ON c.id_user=u.id_user WHERE u.role='client' AND DATE_FORMAT(u.date_inscription,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();

$active_tab = $_GET['tab'] ?? 'dashboard';

// ── Commission data ────────────────────────────────────────────────
// Handle mark-as-payée action (tech paid the platform)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'marquer_versee') {
    $id_comm = intval($_POST['id_commission'] ?? 0);
    if ($id_comm) {
        $pdo->prepare("UPDATE commission SET statut='payée', date_paiement=NOW() WHERE id_commission=?")->execute([$id_comm]);
        log_action($pdo, $_SESSION['user_id'], 'commission_payee', "Commission #$id_comm marquée payée par le tech");
    }
    header("Location: dashboard-admin.php?tab=commissions"); exit;
}

$commissions_list = $pdo->query("
    SELECT c.*,
           ut.nom AS tn, ut.prenom AS tp, ut.email AS temail,
           t.specialité,
           s.date_service, s.nom AS snom
    FROM commission c
    JOIN technicien t  ON t.id_technicien = c.id_technicien
    JOIN utilisateur ut ON ut.id_user = t.id_user
    JOIN service s     ON s.id_service = c.id_service
    ORDER BY c.date_commission DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$comm_stats = $pdo->query("
    SELECT
        COUNT(*)                                                          AS total_commissions,
        COALESCE(SUM(montant_commission),0)                               AS total_montant,
        COALESCE(SUM(CASE WHEN statut='due'   THEN montant_commission ELSE 0 END),0) AS montant_du,
        COALESCE(SUM(CASE WHEN statut='payée' THEN montant_commission ELSE 0 END),0) AS montant_recu,
        COALESCE(SUM(montant_service),0)                                  AS volume_affaires
    FROM commission
")->fetch(PDO::FETCH_ASSOC);

$commissions_pending_count = (int)($comm_stats['total_commissions'] ?? 0);

// Per-technician debt summary (for admin overview)
$tech_debts = $pdo->query("
    SELECT
        c.id_technicien,
        ut.prenom, ut.nom, ut.email, ut.telephone,
        t.specialité,
        COALESCE(SUM(CASE WHEN c.statut='due' THEN c.montant_commission ELSE 0 END),0) AS montant_du,
        COUNT(CASE WHEN c.statut='due' THEN 1 END) AS nb_du,
        MAX(c.date_commission) AS derniere_intervention
    FROM commission c
    JOIN technicien t   ON t.id_technicien = c.id_technicien
    JOIN utilisateur ut ON ut.id_user = t.id_user
    GROUP BY c.id_technicien, ut.prenom, ut.nom, ut.email, ut.telephone, t.specialité
    HAVING montant_du > 0
    ORDER BY montant_du DESC
")->fetchAll(PDO::FETCH_ASSOC);

$commission_months = $pdo->query("
  SELECT
    c.id_technicien,
    ut.prenom, ut.nom, ut.email, ut.telephone,
    t.specialité,
    YEAR(c.date_commission)  AS year_num,
    MONTH(c.date_commission) AS month_num,
    DATE_FORMAT(c.date_commission, '%Y-%m') AS ym,
    DATE_FORMAT(c.date_commission, '%m/%Y') AS month_label,
    MIN(c.date_commission) AS first_date,
    MAX(c.date_commission) AS last_date,
    COUNT(*) AS nb_interventions,
    COALESCE(SUM(c.montant_service),0) AS montant_service_total,
    COALESCE(SUM(c.montant_commission),0) AS montant_total,
    COALESCE(SUM(CASE WHEN c.statut='due' THEN c.montant_commission ELSE 0 END),0) AS montant_du,
    COALESCE(SUM(CASE WHEN c.statut='payée' THEN c.montant_commission ELSE 0 END),0) AS montant_paye,
    COALESCE((
        SELECT SUM(t.montant_tva) FROM tva t
        WHERE t.id_technicien = c.id_technicien
          AND YEAR(t.date_tva) = YEAR(c.date_commission)
          AND MONTH(t.date_tva) = MONTH(c.date_commission)
    ),0) AS tva_total,
    COALESCE((
        SELECT SUM(CASE WHEN t.statut='due' THEN t.montant_tva ELSE 0 END) FROM tva t
        WHERE t.id_technicien = c.id_technicien
          AND YEAR(t.date_tva) = YEAR(c.date_commission)
          AND MONTH(t.date_tva) = MONTH(c.date_commission)
    ),0) AS tva_du,
    COALESCE(SUM(CASE WHEN c.statut='due' THEN 1 ELSE 0 END),0) AS nb_due,
    COALESCE(SUM(CASE WHEN c.statut='payée' THEN 1 ELSE 0 END),0) AS nb_payee,
    MAX(c.date_paiement) AS last_payment
  FROM commission c
  JOIN technicien t   ON t.id_technicien = c.id_technicien
  JOIN utilisateur ut ON ut.id_user = t.id_user
  GROUP BY c.id_technicien, year_num, month_num, ut.prenom, ut.nom, ut.email, ut.telephone, t.specialité
  ORDER BY year_num DESC, month_num DESC, montant_du DESC, ut.nom ASC
")->fetchAll(PDO::FETCH_ASSOC);

$commission_month_details = [];
foreach ($commissions_list as $cm) {
  $ym = !empty($cm['date_commission']) ? date('Y-m', strtotime($cm['date_commission'])) : '0000-00';
  $key = (int)$cm['id_technicien'] . '|' . $ym;
  if (!isset($commission_month_details[$key])) {
    $commission_month_details[$key] = [];
  }
  $commission_month_details[$key][] = $cm;
}

$current_month_key = date('Y-m');
$current_month_due = 0.0;
$current_month_techs_due = 0;
foreach ($commission_months as $month_row) {
  if (($month_row['ym'] ?? '') === $current_month_key) {
    $current_month_due += floatval($month_row['montant_du'] ?? 0);
    if ((float)($month_row['montant_du'] ?? 0) > 0) {
      $current_month_techs_due++;
    }
  }
}
// ──────────────────────────────────────────────────────────────────
$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
if (isset($_GET['reset']) && $_GET['reset'] == '1') {
  $toast = ['msg' => '🔒 Mot de passe mis à jour avec succès !', 'type' => 'ok'];
}

// Realtime seed IDs
$_q = $pdo->prepare("SELECT COALESCE(MAX(id_notif),0) FROM notification WHERE id_user=?");
$_q->execute([$admin_id]); $seed_notif_id = $_q->fetchColumn();
$_q = $pdo->prepare("SELECT COALESCE(MAX(id_message),0) FROM message WHERE id_destinataire=?");
$_q->execute([$admin_id]); $seed_msg_id = $_q->fetchColumn();
$seed_user_id = $pdo->query("SELECT COALESCE(MAX(id_user),0) FROM utilisateur WHERE role != 'admin'")->fetchColumn();


function initials($nom,$prenom){return strtoupper(substr($prenom??'',0,1).substr($nom??'',0,1));}
function stars($n){$n=round($n);$s='';for($i=1;$i<=5;$i++)$s.=$i<=$n?'★':'☆';return $s;}
function chip($status){
    $map=['actif'=>['chip-green','Actif'],'suspendu'=>['chip-red','Suspendu'],'inactif'=>['chip-amber','Inactif'],
          'terminé'=>['chip-green','Terminé'],'en cours'=>['chip-amber','En cours'],'planifié'=>['chip-blue','Planifié'],
          'annulé'=>['chip-red','Annulé'],'ouvert'=>['chip-red','Ouvert'],'résolu'=>['chip-green','Résolu']];
    $s=strtolower($status??'');[$cls,$lbl]=$map[$s]??['chip-amber',ucfirst($status)];
    return "<span class=\"chip $cls\">$lbl</span>";
}
$titles=['dashboard'=>'Tableau de bord','validation'=>'Validation techniciens','techniciens'=>'Techniciens','clients'=>'Clients',
         'reservations'=>'Réservations','messages'=>'Messages','litiges'=>'Litiges',
         'statistiques'=>'Statistiques','profil'=>'Mon Profil',
         'avis'=>'Modération des avis','signalements'=>'Signalements','logs'=>'Journal des actions','factures'=>'Factures'];
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
<title>Admin — <?=t('site_name',$lang)?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
<style>
:root{
  --primary:#7c3aed;--primary-dark:#5b21b6;--primary-light:rgba(124,58,237,.08);
  --secondary:#1a73e8;--secondary-light:rgba(26,115,232,.1);
  --bg:#faf9fe;--surface:#fff;--surface2:#f3f0ff;--border:#e8e4f8;
  --text:#1f2933;--muted:#6b7280;
  --green:#16a34a;--green-l:rgba(22,163,74,.1);
  --red:#ef4444;--red-l:rgba(239,68,68,.1);
  --amber:#f59e0b;--amber-l:rgba(245,158,11,.1);
  --blue:#1a73e8;--blue-l:rgba(26,115,232,.1);
  --sidebar-w:248px;--radius:12px;
  --shadow:0 4px 16px rgba(15,23,42,.07);--shadow-lg:0 10px 30px rgba(15,23,42,.12);
}
*{margin:0;padding:0;box-sizing:border-box;}
html,body{height:100%;overflow:hidden;}body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;width:100%;}
/* SIDEBAR */
.sidebar{width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;z-index:10;box-shadow:2px 0 12px rgba(15,23,42,.04);position:sticky;top:0;height:100vh;overflow-y:auto;}
.logo{padding:22px 20px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);}
.logo-icon{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:13px;color:#fff;flex-shrink:0;}
.logo-text{font-family:'Syne',sans-serif;font-weight:700;font-size:16px;}.logo-text span{color:var(--primary);}
.nav{flex:1;padding:14px 10px;display:flex;flex-direction:column;gap:2px;overflow-y:auto;}
.nav-section{font-size:10px;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;padding:10px 10px 4px;}
.nav-item{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:10px;cursor:pointer;transition:all .18s;color:var(--muted);font-size:13.5px;font-weight:500;text-decoration:none;position:relative;}
.nav-item:hover{background:var(--surface2);color:var(--text);}
.nav-item.active{background:var(--primary-light);color:var(--primary);font-weight:600;}
.nav-item.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:18px;background:var(--primary);border-radius:0 3px 3px 0;}
.nav-item svg{width:17px;height:17px;flex-shrink:0;}
.badge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;line-height:1.4;}
.badge.amber{background:var(--amber);}
.sidebar-footer{padding:14px 10px;border-top:1px solid var(--border);}
.admin-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;cursor:pointer;transition:background .18s;text-decoration:none;color:inherit;}
.admin-card:hover{background:var(--surface2);}
.av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;flex-shrink:0;}
.admin-nm{font-size:13px;font-weight:600;}
.admin-rl{font-size:11px;color:var(--primary);font-weight:500;}
/* MAIN */
.main{flex:1;display:flex;flex-direction:column;min-width:0;height:100vh;overflow:hidden;}
.topbar{padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);background:var(--surface);position:sticky;top:0;z-index:40;}
.page-title{font-family:'Syne',sans-serif;font-weight:700;font-size:19px;}
.topbar-right{display:flex;align-items:center;gap:10px;}
.notif-btn{width:36px;height:36px;border-radius:10px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;}
.notif-dot{position:absolute;top:7px;right:7px;width:7px;height:7px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface);}
.content{flex:1;padding:24px 28px;overflow-y:auto;min-height:0;}.content.msgs{padding:0;overflow:hidden;display:flex;flex-direction:column;}
/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;position:relative;overflow:hidden;box-shadow:var(--shadow);}
.stat-label{font-size:11px;color:var(--muted);font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.8px;}
.stat-val{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;line-height:1;}
.stat-sub{font-size:11px;color:var(--muted);margin-top:5px;}
.stat-sub span{color:var(--green);font-weight:600;}
.stat-icon{position:absolute;right:16px;top:16px;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;}
.si-g{background:var(--green-l);color:var(--green);}
.si-b{background:var(--blue-l);color:var(--blue);}
.si-a{background:var(--amber-l);color:var(--amber);}
.si-r{background:var(--red-l);color:var(--red);}
/* LAYOUT */
.sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.sec-title{font-family:'Syne',sans-serif;font-weight:700;font-size:15px;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;}
/* BTN */
.btn{padding:7px 14px;border-radius:999px;font-size:12.5px;font-weight:600;cursor:pointer;border:none;transition:all .18s;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:5px;text-decoration:none;}
.btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(124,58,237,.22);}
.btn-primary:hover{background:var(--primary-dark);transform:translateY(-1px);}
.btn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border);}
.btn-ghost:hover{background:var(--border);}
.btn-g{background:var(--green-l);color:var(--green);border:1px solid rgba(22,163,74,.25);}
.btn-g:hover{background:rgba(22,163,74,.2);}
.btn-r{background:var(--red-l);color:var(--red);border:1px solid rgba(239,68,68,.25);}
.btn-r:hover{background:rgba(239,68,68,.2);}
.btn-sm{padding:5px 11px;font-size:12px;}
/* CARD/TABLE */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
table{width:100%;border-collapse:collapse;}
thead tr{border-bottom:1px solid var(--border);background:var(--surface2);}
thead th{padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;}
tbody tr{border-bottom:1px solid var(--border);transition:background .12s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:var(--surface2);}
td{padding:11px 14px;font-size:13.5px;}
.fa{display:flex;gap:5px;flex-wrap:wrap;}
.uc{display:flex;align-items:center;gap:9px;}
.mav{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;border:1px solid var(--border);}
.stars{color:var(--amber);font-size:12px;letter-spacing:1px;}
/* CHIPS */
.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;}
.chip::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
.chip-green{background:var(--green-l);color:var(--green);}
.chip-amber{background:var(--amber-l);color:var(--amber);}
.chip-red{background:var(--red-l);color:var(--red);}
.chip-blue{background:var(--blue-l);color:var(--blue);}
/* MESSAGES */
.msg-layout{display:grid;grid-template-columns:270px 1fr;flex:1;min-height:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
.msg-layout > *{min-height:0;min-width:0;}
.msg-list{border-right:1px solid var(--border);overflow-y:auto;display:flex;flex-direction:column;}
.msg-search{padding:12px 14px;border-bottom:1px solid var(--border);}
.msg-search input{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:7px 11px;color:var(--text);font-size:13px;font-family:'DM Sans',sans-serif;outline:none;}
.msg-search input:focus{border-color:var(--primary);}
.msg-item{padding:12px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .12s;display:flex;gap:9px;align-items:flex-start;text-decoration:none;color:inherit;}
.msg-item:hover{background:var(--surface2);}
.msg-item.active{background:var(--primary-light);border-left:3px solid var(--primary);}
.msg-item-info{flex:1;min-width:0;}
.msg-sender{font-size:13px;font-weight:600;}
.msg-time{font-size:11px;color:var(--muted);}
.msg-preview{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.msg-unread{width:8px;height:8px;background:var(--primary);border-radius:50%;margin-top:4px;flex-shrink:0;}
.chat-area{display:flex;flex-direction:column;background:var(--bg);overflow:hidden;min-height:0;}
.chat-hdr{padding:14px 18px;border-bottom:1px solid var(--border);background:var(--surface);display:flex;align-items:center;gap:10px;}
.chat-msgs{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;gap:14px;min-height:0;}
.mbw{display:flex;gap:10px;align-items:flex-end;}
.mbw.right{flex-direction:row-reverse;}
.bubble-inner{display:flex;flex-direction:column;min-width:0;max-width:68%;}
.mbw.right .bubble-inner{align-items:flex-end;}
.bubble{padding:11px 15px;font-size:13.5px;line-height:1.55;word-break:break-word;overflow-wrap:break-word;white-space:pre-wrap;}
.bubble.other{background:#fff;border:1px solid var(--border);border-radius:18px 18px 18px 4px;color:var(--text);box-shadow:0 1px 4px rgba(0,0,0,.06);}
.bubble.adm{background:linear-gradient(135deg,var(--primary),#5b21b6);color:#fff;border-radius:18px 18px 4px 18px;box-shadow:0 3px 12px rgba(124,58,237,.28);}
.bt{font-size:10.5px;color:var(--muted);margin-top:4px;padding:0 4px;}
.mbw.right .bt{text-align:right;color:rgba(124,58,237,.6);}
.bt{font-size:10px;color:var(--muted);margin-top:2px;}
.chat-inp{padding:12px 16px;border-top:1px solid var(--border);background:var(--surface);display:flex;align-items:flex-end;gap:8px;flex-shrink:0;}
.chat-inp form{display:contents;}
.chat-inp textarea{flex:1;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:9px 14px;color:var(--text);font-size:13.5px;font-family:'DM Sans',sans-serif;outline:none;resize:none;min-height:44px;max-height:120px;overflow-y:auto;line-height:1.5;}
.chat-inp textarea:focus{border-color:var(--primary);}
.send-btn{padding:9px 16px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:13px;align-self:flex-end;flex-shrink:0;}
.send-btn:hover{background:var(--primary-dark);}
.new-msg{padding:10px 14px;border-top:1px solid var(--border);background:var(--surface2);}
.new-msg select{width:100%;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:7px 10px;color:var(--text);font-size:12px;font-family:'DM Sans',sans-serif;outline:none;}
/* LITIGES */
.litige-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;margin-bottom:10px;display:grid;grid-template-columns:1fr auto;gap:14px;align-items:start;box-shadow:var(--shadow);}
.lit-id{font-size:11px;color:var(--muted);margin-bottom:3px;font-weight:700;}
.lit-title{font-size:14px;font-weight:700;margin-bottom:5px;}
.lit-desc{font-size:13px;color:var(--muted);line-height:1.5;}
.lit-meta{display:flex;gap:12px;margin-top:8px;font-size:12px;color:var(--muted);flex-wrap:wrap;}
.lit-act{display:flex;flex-direction:column;gap:6px;align-items:flex-end;}
/* CHARTS */
.chart-bars{background:var(--surface2);border-radius:10px;height:150px;display:flex;align-items:flex-end;padding:14px 14px 0;gap:7px;}
.bar{border-radius:6px 6px 0 0;background:var(--primary);opacity:.65;flex:1;transition:opacity .2s;cursor:pointer;}
.bar:hover{opacity:1;}
.pr-row{margin-bottom:10px;}
.pr-lbl{display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px;}
.pr-track{height:6px;background:var(--surface2);border-radius:3px;}
.pr-fill{height:100%;border-radius:3px;transition:width .6s ease;}
.profile-hero{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:28px;display:flex;align-items:flex-start;gap:24px;margin-bottom:18px;box-shadow:var(--shadow);direction:ltr;text-align:left;}
.profile-main{display:flex;flex-direction:column;align-items:flex-start;min-width:0;}
.profile-av{width:80px;height:80px;border-radius:50%;border:3px solid var(--primary);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;font-family:'Syne',sans-serif;background:var(--primary-light);color:var(--primary);flex-shrink:0;}
.profile-name{font-family:'Syne',sans-serif;font-weight:800;font-size:22px;margin-bottom:2px;}
.profile-role{color:var(--primary);font-size:13px;font-weight:600;margin-bottom:10px;}
.profile-meta{display:flex;gap:16px;flex-wrap:wrap;}
.meta-item{font-size:13px;color:var(--muted);}
.meta-item strong{color:var(--text);}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fgrp{display:flex;flex-direction:column;gap:5px;}
.fgrp.full{grid-column:1/-1;}
.flbl{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;}
.finput{background:var(--surface2);border:1px solid var(--border);border-radius:9px;padding:9px 12px;color:var(--text);font-size:13.5px;font-family:'DM Sans',sans-serif;outline:none;transition:border .18s;width:100%;}
.finput:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(124,58,237,.10);}
textarea.finput{resize:vertical;min-height:75px;}
/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(31,41,51,.55);backdrop-filter:blur(4px);z-index:100;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:18px;width:600px;max-width:95vw;max-height:88vh;overflow-y:auto;box-shadow:var(--shadow-lg);animation:mIn .25s ease;}
@keyframes mIn{from{transform:scale(.96) translateY(14px)}to{transform:scale(1) translateY(0)}}
.modal-hdr{padding:22px 26px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--surface);z-index:1;}
.modal-title{font-family:'Syne',sans-serif;font-weight:700;font-size:17px;}
.close-btn{width:30px;height:30px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;color:var(--muted);}
.close-btn:hover{background:var(--border);}
.modal-body{padding:22px 26px;}
.cv-sec{margin-bottom:18px;}
.cv-sec-title{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1.2px;margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid var(--border);}
.cv-row{display:flex;justify-content:space-between;padding:5px 0;font-size:13.5px;}
.cv-row .lbl{color:var(--muted);}
.cv-tags{display:flex;flex-wrap:wrap;gap:5px;margin-top:3px;}
.cv-tag{background:var(--surface2);border:1px solid var(--border);border-radius:7px;padding:3px 10px;font-size:12px;}
.modal-footer{padding:14px 26px 22px;display:flex;gap:8px;justify-content:flex-end;border-top:1px solid var(--border);}
/* EMPTY */
.empty{text-align:center;padding:50px 0;color:var(--muted);}
.empty .ico{font-size:38px;margin-bottom:10px;}
.empty p{font-size:15px;font-weight:600;}
/* TOAST */
.toast-cont{position:fixed;bottom:20px;right:20px;display:flex;flex-direction:column;gap:8px;z-index:200;}
.toast{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:11px 16px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:9px;box-shadow:var(--shadow-lg);animation:tIn .25s ease;min-width:220px;}
@keyframes tIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
.toast.success{border-left:3px solid var(--green);}
.toast.error{border-left:3px solid var(--red);}
.toast.info{border-left:3px solid var(--blue);}
::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-track{background:transparent;}::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}

  .mob-hamburger { display: none !important; }
  .sidebar-overlay { display: none !important; }

  body{background:var(--bg);}
  .main{min-height:100vh;background:var(--bg);}
  .content{padding-bottom:96px !important;}
  .card,.profile-hero,.litige-card,.msg-layout{box-shadow:none;}
  .card{border-radius:14px;}
  .card,.msg-layout,.profile-hero,.litige-card{border-color:rgba(232,228,248,1);}
  .topbar{background:rgba(255,255,255,.92);backdrop-filter:blur(14px);}
  .mob-nav{box-shadow:0 -8px 24px rgba(15,23,42,.08);}


/* ════════════════════════════════
   MOBILE ≤ 768px — ADMIN
════════════════════════════════ */
@media (max-width: 768px) {

  /* ── Reset desktop height lock ── */
  html, body {
    height: auto !important;
    overflow: auto !important;
    width: 100% !important;
  }
  body { display: block !important; }

  /* ── Sidebar: slide off-screen ── */
  .sidebar {
    position: fixed !important;
    top: 0; left: 0; bottom: 0;
    width: 260px !important;
    height: 100vh !important;
    transform: translateX(-110%);
    transition: transform .3s cubic-bezier(.4,0,.2,1);
    z-index: 300;
    overflow-y: auto;
  }
  .sidebar.open { transform: translateX(0); }

  /* ── Overlay ── */
  .sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 299;
  }
  .sidebar-overlay.open { display: block; }

  /* ── Main: full width ── */
  .main {
    width: 100% !important;
    min-width: 0 !important;
    height: auto !important;
    overflow: visible !important;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }

  /* ── Topbar ── */
  .topbar {
    padding: 0 12px !important;
    height: 52px !important;
    flex-shrink: 0;
    overflow: hidden;
  }
  .page-title {
    font-size: 14px !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
  }
  .topbar-right { gap: 6px !important; }
  .notif-btn { width: 32px !important; height: 32px !important; }
  .av { width: 30px !important; height: 30px !important; font-size: 11px !important; }
  .mob-hamburger { display: flex !important; }

  /* ── Content ── */
  .content {
    padding: 14px 12px 90px !important;
    overflow-y: auto !important;
    flex: 1;
    width: 100%;
    min-width: 0;
  }
  .content.msgs {
    padding: 0 !important;
    overflow: hidden !important;
    height: calc(100vh - 52px);
  }

  /* ── Stats grid: 2 columns ── */
  .stats-grid {
    grid-template-columns: 1fr 1fr !important;
    gap: 10px !important;
    margin-bottom: 14px !important;
  }
  .stat-card { padding: 14px !important; }
  .stat-val { font-size: 22px !important; }
  .stat-label { font-size: 10px !important; }
  .stat-icon { width: 30px !important; height: 30px !important; right: 10px !important; top: 10px !important; }

  /* ── Two col → single ── */
  .two-col {
    grid-template-columns: 1fr !important;
    gap: 12px !important;
  }

  /* ── Cards ── */
  .card { border-radius: 12px; overflow-x: hidden; }
  .sec-hdr { flex-wrap: wrap; gap: 8px; }
  .sec-title { font-size: 14px; }

  /* ── Tables: scroll horizontally inside card ── */
  .card { overflow-x: auto !important; }
  table { min-width: 480px; }
  thead th { padding: 8px 10px !important; font-size: 10px !important; }
  td { padding: 9px 10px !important; font-size: 12.5px !important; }
  .fa { flex-wrap: nowrap; }

  /* ── Messages ── */
  .msg-layout {
    grid-template-columns: 1fr !important;
    height: calc(100vh - 52px);
  }
  .msg-list { display: none !important; }
  .chat-msgs { height: calc(100vh - 170px) !important; }

  /* ── Bottom tab bar ── */
  .mob-nav {
    display: flex !important;
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--surface);
    border-top: 1px solid var(--border);
    z-index: 200;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    box-shadow: 0 -4px 20px rgba(0,0,0,.06);
  }
  .mob-tab {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 7px 2px 5px;
    text-decoration: none;
    color: var(--muted);
    font-size: 9px; font-weight: 600;
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

  /* ── Prevent iOS input zoom ── */
  input, select, textarea { font-size: 16px !important; }

  /* ── Misc ── */
  .filter-bar { flex-wrap: wrap; gap: 6px; }
  .filter-bar select,
  .filter-bar input { flex: 1; min-width: 120px; }
  .modal { width: 95vw !important; max-height: 90vh; }
  .chip { font-size: 10px; padding: 2px 7px; }
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

/* ── Desktop reset ── */
@media (min-width: 769px) {
  .mob-nav { display: none !important; }
  .mob-hamburger { display: none !important; }
  .sidebar-overlay { display: none !important; }
}

</style>
</head>
<body>

<aside class="sidebar">
  <div class="logo">
    <div class="logo-icon">KDZ</div>
    <div class="logo-text">Khidma <span>DZ</span></div>
  </div>
  <nav class="nav">
    <div class="nav-section">Principal</div>
    <a class="nav-item <?= $active_tab==='dashboard'?'active':'' ?>" href="?tab=dashboard">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Tableau de bord
    </a>
    <div class="nav-section">Gestion</div>
    <a class="nav-item <?= $active_tab==='validation'?'active':'' ?>" href="?tab=validation">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      Validation
      <?php if($stats['en_attente']>0):?><span class="badge"><?=$stats['en_attente']?></span><?php endif;?>
    </a>
    <a class="nav-item <?= $active_tab==='techniciens'?'active':'' ?>" href="?tab=techniciens">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Techniciens
      <?php if($stats['en_attente']>0):?><span class="badge"><?=$stats['en_attente']?></span><?php endif;?>
    </a>
    <a class="nav-item <?= $active_tab==='clients'?'active':'' ?>" href="?tab=clients">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
      Clients
    </a>
    <a class="nav-item <?= $active_tab==='reservations'?'active':'' ?>" href="?tab=reservations">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      Réservations
    </a>
    <a class="nav-item <?= $active_tab==='messages'?'active':'' ?>" href="?tab=messages">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Messages
      <?php $unread=array_sum(array_column($conversations,'unread'));if($unread>0):?><span class="badge nav-badge-msg"><?=$unread?></span><?php endif;?>
    </a>
    <a class="nav-item <?= $active_tab==='litiges'?'active':'' ?>" href="?tab=litiges">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Litiges
      <?php if($stats['litiges']>0):?><span class="badge amber"><?=$stats['litiges']?></span><?php endif;?>
    </a>
    <a class="nav-item <?= $active_tab==='signalements'?'active':'' ?>" href="?tab=signalements">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
      Signalements
      <?php if($stats['signalements']>0):?><span class="badge amber"><?=$stats['signalements']?></span><?php endif;?>
    </a>
    <div class="nav-section">Modération</div>
    <a class="nav-item <?= $active_tab==='avis'?'active':'' ?>" href="?tab=avis">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      Avis
    </a>
    <a class="nav-item <?= $active_tab==='commissions'?'active':'' ?>" href="?tab=commissions">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><line x1="12" y1="6" x2="12" y2="18"/></svg>
      Commissions
      <?php if(!empty($commissions_pending_count) && $commissions_pending_count>0):?><span class="badge amber"><?=$commissions_pending_count?></span><?php endif;?>
    </a>
    <a class="nav-item <?= $active_tab==='factures'?'active':'' ?>" href="?tab=factures">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      Factures
      <?php if(count($services_no_facture)>0):?><span class="badge amber"><?=count($services_no_facture)?></span><?php endif;?>
    </a>
    <a class="nav-item <?= $active_tab==='statistiques'?'active':'' ?>" href="?tab=statistiques">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Statistiques
    </a>
    <a class="nav-item <?= $active_tab==='logs'?'active':'' ?>" href="?tab=logs">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Journal (logs)
    </a>
    <a class="nav-item <?= $active_tab==='profil'?'active':'' ?>" href="?tab=profil">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Mon Profil
    </a>
    <a class="nav-item" href="index.php">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Voir le site
    </a>
  </nav>
  <div class="sidebar-footer">
    <a class="admin-card" href="?tab=profil">
      <div class="av"><?=initials($admin_info['nom']??'',$admin_info['prenom']??'')?></div>
      <div style="flex:1;min-width:0">
        <div class="admin-nm"><?=htmlspecialchars(($admin_info['prenom']??'').' '.($admin_info['nom']??''))?></div>
        <div class="admin-rl">Super Admin</div>
      </div>
    </a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <button class="mob-hamburger" onclick="openSidebar()" style="display:none;background:none;border:none;cursor:pointer;padding:4px;color:var(--text);margin-right:8px;flex-shrink:0"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="page-title"><?=$titles[$active_tab]??'Admin'?></div>
    <div class="topbar-right">
      <a href="actions/handle_logout.php" class="btn btn-ghost btn-sm">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Déconnexion
      </a>
    </div>
  </div>

  <div class="content <?php if($active_tab==='messages') echo 'msgs'; ?>">

  <?php if($active_tab==='dashboard'): ?>
  <!-- ══ DASHBOARD ══ -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon si-a"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      <div class="stat-label">Techniciens</div><div class="stat-val"><?=$stats['techniciens']?></div>
      <div class="stat-sub"><span>+<?=$stats['new_this_month_tech']?></span> ce mois</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-b"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
      <div class="stat-label">Clients</div><div class="stat-val"><?=$stats['clients']?></div>
      <div class="stat-sub"><span>+<?=$stats['new_this_month_client']?></span> ce mois</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-g"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
      <div class="stat-label">Réservations</div><div class="stat-val"><?=$stats['services']?></div>
      <div class="stat-sub" style="color:var(--muted)">Total cumulé</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-r"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
      <div class="stat-label">Litiges actifs</div><div class="stat-val"><?=$stats['litiges']?></div>
      <div class="stat-sub" style="color:<?=$stats['litiges']>0?'var(--red)':'var(--green)'?>"><?=$stats['litiges']>0?'À traiter':'Aucun litige'?></div>
    </div>
  </div>

  <div class="two-col">
    <div>
      <div class="sec-hdr">
        <div class="sec-title">En attente de validation (<?=count($tech_pending)?>)</div>
        <a href="?tab=techniciens" class="btn btn-ghost btn-sm">Voir tout</a>
      </div>
      <div class="card">
        <?php if(empty($tech_pending)):?>
        <div class="empty"><div class="ico">✅</div><p>Aucun technicien en attente</p></div>
        <?php else:?>
        <table><thead><tr><th>Technicien</th><th>Spécialité</th><th>Wilaya</th><th>Action</th></tr></thead><tbody>
        <?php foreach(array_slice($tech_pending,0,4) as $t):?>
          <tr style="cursor:pointer" onclick="openCV(<?=htmlspecialchars(json_encode($t))?>)">
            <td><div class="uc"><div class="mav" style="background:var(--amber-l);color:var(--amber)"><?=initials($t['nom'],$t['prenom'])?></div><?=htmlspecialchars($t['prenom'].' '.$t['nom'])?></div></td>
            <td><?=htmlspecialchars($t['specialité']??'—')?></td>
            <td><?=htmlspecialchars(wilaya_display($t['ville']??'—', $lang ?? 'fr'))?></td>
            <td onclick="event.stopPropagation()"><div class="fa">
              <form method="POST" style="display:inline"><input type="hidden" name="action" value="approve_tech"><input type="hidden" name="id_technicien" value="<?=$t['id_technicien']?>"><input type="hidden" name="tab" value="dashboard"><button class="btn btn-g btn-sm">✓ Approuver</button></form>
              <form method="POST" style="display:inline"><input type="hidden" name="action" value="reject_tech"><input type="hidden" name="id_technicien" value="<?=$t['id_technicien']?>"><input type="hidden" name="tab" value="dashboard"><button class="btn btn-r btn-sm">✗ Rejeter</button></form>
            </div></td>
          </tr>
        <?php endforeach;?>
        </tbody></table>
        <?php endif;?>
      </div>
    </div>
    <div>
      <div class="sec-hdr">
        <div class="sec-title">Litiges récents</div>
        <a href="?tab=litiges" class="btn btn-ghost btn-sm">Voir tout</a>
      </div>
      <div class="card">
        <?php if(empty($litiges_open)):?>
        <div class="empty"><div class="ico">✅</div><p>Aucun litige actif</p></div>
        <?php else:?>
        <table><thead><tr><th>Description</th><th>Client</th><th>Statut</th></tr></thead><tbody>
        <?php foreach(array_slice($litiges_open,0,4) as $l):?>
          <tr><td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars(substr($l['description_litige']??'—',0,40))?>...</td>
          <td><?=htmlspecialchars($l['client_prenom'].' '.$l['client_nom'])?></td>
          <td><?=chip($l['statut_litige'])?></td></tr>
        <?php endforeach;?>
        </tbody></table>
        <?php endif;?>
      </div>
    </div>
  </div>

  <div class="sec-hdr"><div class="sec-title">Réservations récentes</div></div>
  <div class="card">
    <?php if(empty($services_recent)):?>
    <div class="empty"><div class="ico">📅</div><p>Aucune réservation</p></div>
    <?php else:?>
    <table><thead><tr><th>#</th><th>Client</th><th>Technicien</th><th>Service</th><th>Prix</th><th>Date</th><th>Statut</th></tr></thead><tbody>
    <?php foreach(array_slice($services_recent,0,6) as $s):?>
      <tr>
        <td style="color:var(--muted);font-size:12px">#<?=$s['id_service']?></td>
        <td><?=htmlspecialchars($s['client_prenom'].' '.$s['client_nom'])?></td>
        <td><?=htmlspecialchars($s['tech_prenom'].' '.$s['tech_nom'])?></td>
        <td><?=htmlspecialchars($s['specialité']??'—')?></td>
        <td style="color:var(--green);font-weight:600"><?=$s['prix_final']?number_format($s['prix_final'],0).' DA':($s['prix_estime']?'~'.number_format($s['prix_estime'],0).' DA':'—')?></td>
        <td style="color:var(--muted);font-size:12px"><?=$s['date_service']?date('d/m/y',strtotime($s['date_service'])):'—'?></td>
        <td><?=chip($s['statut_service']??'planifié')?></td>
      </tr>
    <?php endforeach;?>
    </tbody></table>
    <?php endif;?>
  </div>

  <?php elseif($active_tab==='validation'): ?>
  <!-- ══ VALIDATION ══ -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
    <div class="sec-title" style="margin:0">Validation techniciens (<?=count($tech_pending)?>)</div>
    <input id="validation-search" type="text" placeholder="🔍 Rechercher nom, email, spécialité…" oninput="filterTechs()" style="padding:7px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;outline:none;min-width:180px;flex:1;background:#fff">
  </div>
  <?php if(empty($tech_pending)): ?>
  <div style="text-align:center;padding:80px 24px">
    <div style="font-size:64px;margin-bottom:16px">✅</div>
    <div style="font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--text);margin-bottom:8px">Aucun technicien en attente</div>
    <div style="font-size:14px;color:var(--muted)">Tous les profils ont été traités.</div>
    <a href="?tab=techniciens" class="btn btn-primary" style="margin-top:20px;display:inline-flex">Voir tous les techniciens</a>
  </div>
  <?php else: ?>
  <div style="display:grid;gap:20px">
  <?php foreach($tech_pending as $t): ?>
  <?php $validation_search = htmlspecialchars(strtolower(trim(($t['prenom']??'').' '.($t['nom']??'').' '.($t['email']??'').' '.($t['specialité']??'').' '.($t['ville']??'').' '.($t['experience']??'')))); ?>
  <div class="card validation-tech-card" data-search="<?=$validation_search?>" style="padding:0;overflow:hidden">
    <!-- Header -->
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:16px;background:linear-gradient(135deg,var(--primary-light),rgba(6,182,212,.06))">
      <div style="width:56px;height:56px;border-radius:50%;background:var(--primary-light);border:2px solid var(--primary);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--primary);flex-shrink:0"><?=initials($t['nom'],$t['prenom'])?></div>
      <div style="flex:1">
        <div style="font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--text)"><?=htmlspecialchars($t['prenom'].' '.$t['nom'])?></div>
        <div style="font-size:13px;color:var(--muted);margin-top:2px"><?=htmlspecialchars($t['email'])?> · <?=htmlspecialchars($t['telephone']??'—')?></div>
        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
          <span class="chip chip-amber">⏳ En attente de validation</span>
          <?php if($t['specialité']):?><span class="chip" style="background:var(--blue-l);color:var(--blue)"><?=htmlspecialchars($t['specialité'])?></span><?php endif;?>
          <?php if($t['ville']):?><span class="chip" style="background:var(--surface2);color:var(--muted)">📍 <?=htmlspecialchars(wilaya_display($t['ville'],$lang ?? 'fr'))?></span><?php endif;?>
        </div>
      </div>
      <div style="font-size:12px;color:var(--muted);text-align:right">
        Inscrit le<br><strong style="color:var(--text)"><?=$t['date_inscription']?date('d/m/Y',strtotime($t['date_inscription'])):'—'?></strong>
      </div>
    </div>
    <!-- Details grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border-bottom:1px solid var(--border)">
      <div style="padding:16px 20px;border-right:1px solid var(--border)">
        <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px">Expérience</div>
        <div style="font-size:16px;font-weight:700;color:var(--text)"><?=$t['experience']?$t['experience'].' ans':'—'?></div>
      </div>
      <div style="padding:16px 20px">
        <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px">Documents</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <?php if($t['cv']):?><span style="background:#dcfce7;color:#15803d;border:1px solid #86efac;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:700">✓ CV</span><?php else:?><span style="background:var(--surface2);color:var(--muted);border:1px solid var(--border);border-radius:6px;padding:2px 8px;font-size:11px">Pas de CV</span><?php endif;?>
          <?php if($t['diplôme']):?><span style="background:#dcfce7;color:#15803d;border:1px solid #86efac;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:700">✓ Diplôme</span><?php else:?><span style="background:var(--surface2);color:var(--muted);border:1px solid var(--border);border-radius:6px;padding:2px 8px;font-size:11px">Pas de diplôme</span><?php endif;?>
        </div>
      </div>
    </div>
    <?php if($t['description_profil']):?>
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-size:13px;color:var(--muted);line-height:1.6">
      <strong style="color:var(--text)">Bio :</strong> <?=htmlspecialchars($t['description_profil'])?>
    </div>
    <?php endif;?>
    <!-- Actions -->
    <div style="padding:16px 20px;display:flex;gap:10px;align-items:center;background:var(--surface2)">
      <button onclick="openCV(<?=htmlspecialchars(json_encode($t))?>) " class="btn btn-ghost btn-sm">📄 Voir le dossier complet</button>
      <div style="flex:1"></div>
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="reject_tech">
        <input type="hidden" name="id_technicien" value="<?=$t['id_technicien']?>">
        <input type="hidden" name="tab" value="validation">
        <button class="btn btn-r" onclick="return confirm('Rejeter ce technicien ?')">✕ Rejeter</button>
      </form>
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="approve_tech">
        <input type="hidden" name="id_technicien" value="<?=$t['id_technicien']?>">
        <input type="hidden" name="tab" value="validation">
        <button class="btn btn-primary">✓ Approuver</button>
      </form>
    </div>
  </div>
  <?php endforeach;?>
  </div>
  <?php endif;?>

  <?php elseif($active_tab==='techniciens'): ?>
  <!-- ══ TECHNICIENS ══ -->
  <div class="sec-hdr">
    <div style="display:flex;gap:10px;align-items:center;width:100%">
      <div style="min-width:220px;flex:0">
        <div class="sec-title">Gestion des techniciens (<?=count($tech_all)?>)</div>
      </div>
      <input id="tech-search" type="text" placeholder="🔍 Rechercher technicien, email, spécialité…" oninput="filterTechs()" style="padding:8px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;outline:none;min-width:180px;flex:3;background:#fff">
      <select id="tech-filter" class="finput btn-sm" style="width:auto;padding:6px 14px;border-radius:999px;cursor:pointer;flex:0" onchange="filterTechs(this.value)">
        <option value="all">Tous</option><option value="pending">En attente</option><option value="approved">Approuvés</option><option value="rejected">Rejetés</option>
      </select>
    </div>
  </div>
  <div class="card">
    <?php if(empty($tech_all)):?>
    <div class="empty"><div class="ico">👷</div><p>Aucun technicien inscrit</p></div>
    <?php else:?>
    <table id="techs-table"><thead><tr><th>Technicien</th><th>Spécialité</th><th>Wilaya</th><th>Exp.</th><th>Note</th><th>Statut</th><th>CV</th><th>Action</th></tr></thead><tbody>
    <?php foreach($tech_all as $t):
      $status=$t['verifie']?'approved':($t['statut_compte']==='suspendu'?'rejected':'pending');?>
      <?php $tech_search = htmlspecialchars(strtolower(trim(($t['prenom']??'').' '.($t['nom']??'').' '.($t['email']??'').' '.($t['specialité']??'').' '.($t['ville']??'')))); ?>
      <tr data-status="<?=$status?>" data-search="<?=$tech_search?>">
        <td><div class="uc"><div class="mav" style="background:<?=$t['verifie']?'var(--green-l);color:var(--green)':'var(--amber-l);color:var(--amber)'?>"><?=initials($t['nom'],$t['prenom'])?></div>
          <div><div><?=htmlspecialchars($t['prenom'].' '.$t['nom'])?></div><div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($t['email'])?></div></div>
        </div></td>
        <td><?=htmlspecialchars($t['specialité']??'—')?></td>
        <td><?=htmlspecialchars(wilaya_display($t['ville']??'—', $lang ?? 'fr'))?></td>
        <td><?=$t['experience']?$t['experience'].' ans':'—'?></td>
        <td><span class="stars"><?=stars($t['avg_note'])?></span></td>
        <td><?php if($t['statut_compte']==='suspendu'):?><span class="chip chip-red">Suspendu</span><?php else:?><span class="chip chip-green">Approuvé</span><?php endif;?></td>
        <td><button class="btn btn-ghost btn-sm" onclick="openCV(<?=htmlspecialchars(json_encode($t))?>)">📄 CV</button></td>
        <td><div class="fa">
          <?php if($t['verifie'] && $t['statut_compte']==='suspendu'):?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="activate_user"><input type="hidden" name="id_user" value="<?=$t['id_user']?>"><input type="hidden" name="tab" value="techniciens"><button class="btn btn-g btn-sm">✓ Réactiver</button></form>
          <?php elseif($t['verifie']):?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="suspend_user"><input type="hidden" name="id_user" value="<?=$t['id_user']?>"><input type="hidden" name="tab" value="techniciens"><button class="btn btn-r btn-sm">Suspendre</button></form>
          <?php endif;?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer définitivement ce technicien ?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id_user" value="<?=$t['id_user']?>"><input type="hidden" name="tab" value="techniciens"><button class="btn btn-r btn-sm">🗑</button></form>
        </div></td>
      </tr>
    <?php endforeach;?>
    </tbody></table>
    <?php endif;?>
  </div>

  <?php elseif($active_tab==='clients'): ?>
  <!-- ══ CLIENTS ══ -->
    <div class="sec-hdr" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <div class="sec-title" style="margin:0">Gestion des clients (<?=count($clients_all)?>)</div>
    <input id="client-search" type="text" placeholder="🔍 Rechercher client, email, téléphone…" oninput="filterClients()" style="padding:7px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;outline:none;min-width:180px;flex:1;background:#fff">
  </div>
  <div class="card">
    <?php if(empty($clients_all)):?>
    <div class="empty"><div class="ico">👤</div><p>Aucun client inscrit</p></div>
    <?php else:?>
    <table id="clients-table"><thead><tr><th>Client</th><th>Email</th><th>Téléphone</th><th>Wilaya</th><th>Réservations</th><th>Membre depuis</th><th>Statut</th><th>Action</th></tr></thead><tbody>
    <?php foreach($clients_all as $c):?>
      <?php $client_search = htmlspecialchars(strtolower(trim(($c['prenom']??'').' '.($c['nom']??'').' '.($c['email']??'').' '.($c['telephone']??'').' '.($c['ville']??'')))); ?>
      <tr data-search="<?=$client_search?>">
        <td><div class="uc"><div class="mav" style="background:var(--blue-l);color:var(--blue)"><?=initials($c['nom'],$c['prenom'])?></div><?=htmlspecialchars($c['prenom'].' '.$c['nom'])?></div></td>
        <td style="color:var(--muted);font-size:12px"><?=htmlspecialchars($c['email'])?></td>
        <td style="color:var(--muted);font-size:12px"><?=htmlspecialchars($c['telephone']??'—')?></td>
        <td><?=htmlspecialchars(wilaya_display($c['ville']??'—', $lang ?? 'fr'))?></td>
        <td style="font-weight:600"><?=$c['nb_reservations']?></td>
        <td style="color:var(--muted);font-size:12px"><?=$c['date_inscription']?date('M Y',strtotime($c['date_inscription'])):'—'?></td>
        <td><?=chip($c['statut_compte']??'actif')?></td>
        <td><div class="fa">
          <?php if(($c['statut_compte']??'actif')==='suspendu'):?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="activate_user"><input type="hidden" name="id_user" value="<?=$c['id_user']?>"><input type="hidden" name="tab" value="clients"><button class="btn btn-g btn-sm">Réactiver</button></form>
          <?php else:?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="suspend_user"><input type="hidden" name="id_user" value="<?=$c['id_user']?>"><input type="hidden" name="tab" value="clients"><button class="btn btn-r btn-sm">Suspendre</button></form>
          <?php endif;?>
          <a href="?tab=messages&with=<?=$c['id_user']?>" class="btn btn-ghost btn-sm">✉</a>
          <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer définitivement ce client ?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id_user" value="<?=$c['id_user']?>"><input type="hidden" name="tab" value="clients"><button class="btn btn-r btn-sm">🗑</button></form>
        </div></td>
      </tr>
    <?php endforeach;?>
    </tbody></table>
    <?php endif;?>
  </div>

  <?php elseif($active_tab==='reservations'): ?>
  <!-- ══ RESERVATIONS ══ -->
  <!-- reservations filter + table -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
    <div class="sec-title" style="margin:0">Réservations (<?=count($services_recent)?>)</div>
    <input type="text" id="res-search" placeholder="🔍 Client, technicien…" oninput="filterRes()" style="padding:7px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;outline:none;min-width:180px;flex:1;background:#fff">
    <select id="res-status" onchange="filterRes()" style="padding:7px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;outline:none;background:#fff">
      <option value="">Tous les statuts</option>
      <option value="planifié">Planifié</option>
      <option value="en cours">En cours</option>
      <option value="terminé">Terminé</option>
      <option value="annulé">Annulé</option>
    </select>
  </div>
  <?php if(empty($services_recent)):?>
  <div class="card"><div class="empty"><div class="ico">📅</div><p>Aucune réservation</p></div></div>
  <?php else:?>
  <div class="card" style="padding:0;overflow:hidden">
    <table id="res-table">
      <thead><tr>
        <th style="width:44px">#</th>
        <th>Client</th>
        <th>Technicien</th>
        <th>Prestations</th>
        <th>Description</th>
        <th>Date</th>
        <th>Prix</th>
        <th>Statut</th>
      </tr></thead>
      <tbody>
      <?php foreach($services_recent as $s):
        $prests = $admin_svc_prests[(int)$s['id_service']] ?? [];?>
      <tr data-status="<?=htmlspecialchars($s['statut_service']??'')?>" data-search="<?=htmlspecialchars(strtolower($s['client_prenom'].' '.$s['client_nom'].' '.$s['tech_prenom'].' '.$s['tech_nom']))?>">
        <td style="color:var(--muted);font-size:12px">#<?=$s['id_service']?></td>
        <td><div class="uc" style="gap:8px">
          <div class="mav" style="width:28px;height:28px;font-size:10px;background:var(--blue-l);color:var(--blue)"><?=initials($s['client_nom'],$s['client_prenom'])?></div>
          <span style="font-size:13px;font-weight:600"><?=htmlspecialchars($s['client_prenom'].' '.$s['client_nom'])?></span>
        </div></td>
        <td><div style="font-size:13px;font-weight:600"><?=htmlspecialchars($s['tech_prenom'].' '.$s['tech_nom'])?></div><div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($s['specialité']??'—')?></div></td>
        <td style="max-width:160px">
          <?php if(!empty($prests)):?>
          <div style="display:flex;flex-wrap:wrap;gap:3px">
            <?php foreach($prests as $pr):?>
            <span style="background:var(--primary-light);color:var(--primary);border-radius:5px;padding:2px 7px;font-size:11px;font-weight:600"><?=htmlspecialchars($pr['nom'])?></span>
            <?php endforeach;?>
          </div>
          <?php else:?><span style="color:var(--muted);font-size:12px">—</span><?php endif;?>
        </td>
        <td style="max-width:150px;font-size:12.5px;color:var(--text2)"><?=htmlspecialchars(mb_substr($s['description']??'—',0,50))?><?=mb_strlen($s['description']??'')>50?'…':''?></td>
        <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?=$s['date_service']?date('d/m/Y',strtotime($s['date_service'])):'—'?></td>
        <td style="white-space:nowrap">
          <?php if($s['prix_final']):?><div style="font-size:13px;font-weight:700;color:var(--green)"><?=number_format($s['prix_final'],0)?> DA</div><?php endif;?>
          <?php if($s['prix_estime']&&!$s['prix_final']):?><div style="font-size:12px;color:var(--muted)">~<?=number_format($s['prix_estime'],0)?> DA</div><?php endif;?>
        </td>
        <td><?=chip($s['statut_service']??'planifié')?></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <script>
  function filterRes(){
    var q=(document.getElementById('res-search').value||'').toLowerCase();
    var st=document.getElementById('res-status').value;
    document.querySelectorAll('#res-table tbody tr').forEach(function(r){
      var mq=!q||r.dataset.search.includes(q);
      var ms=!st||r.dataset.status===st;
      r.style.display=(mq&&ms)?'':'none';
    });
  }
  function filterClients(){
    var q=(document.getElementById('client-search').value||'').toLowerCase();
    document.querySelectorAll('#clients-table tbody tr').forEach(function(r){
      var mq = !q || (r.dataset.search || '').includes(q);
      r.style.display = mq ? '' : 'none';
    });
  }
  function filterFactures(){
    var q=(document.getElementById('facture-search').value||'').toLowerCase();
    document.querySelectorAll('#factures-table tbody tr, #factures-pending-table tbody tr').forEach(function(r){
      var mq = !q || (r.dataset.search || '').includes(q);
      r.style.display = mq ? '' : 'none';
    });
  }
  </script>
  <?php endif;?>

  <?php elseif($active_tab==='messages'):
    $sel_uid = $_GET['with'] ?? ($conversations[0]['other_id'] ?? null);
    $chat_msgs=[]; $chat_user=null;
    if($sel_uid){
      $su=$pdo->prepare("SELECT * FROM utilisateur WHERE id_user=?");$su->execute([$sel_uid]);$chat_user=$su->fetch();
      $sm=$pdo->prepare("SELECT m.*,u.nom,u.prenom FROM message m JOIN utilisateur u ON u.id_user=m.id_expediteur WHERE (m.id_expediteur=? AND m.id_destinataire=?) OR (m.id_expediteur=? AND m.id_destinataire=?) ORDER BY m.data_envoi ASC,m.id_message ASC");
      $sm->execute([$admin_id,$sel_uid,$sel_uid,$admin_id]);$chat_msgs=$sm->fetchAll();
      $pdo->prepare("UPDATE message SET lu=1 WHERE id_destinataire=? AND id_expediteur=?")->execute([$admin_id,$sel_uid]);
    }
  ?>
  <!-- ══ MESSAGES ══ -->
  <div class="msg-layout">
    <div class="msg-list">
      <div class="msg-search"><input type="text" placeholder="Rechercher…" oninput="filterMsgs(this.value)"/></div>
      <?php if(empty($conversations)):?>
      <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px">Aucune conversation</div>
      <?php else:foreach($conversations as $conv):?>
        <a href="?tab=messages&with=<?=$conv['other_id']?>" class="msg-item <?=$conv['other_id']==$sel_uid?'active':''?>">
          <div class="mav" style="width:34px;height:34px;background:var(--primary-light);color:var(--primary)"><?=initials($conv['nom'],$conv['prenom'])?></div>
          <div class="msg-item-info">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px">
              <span class="msg-sender"><?=htmlspecialchars($conv['prenom'].' '.$conv['nom'])?></span>
              <span class="msg-time"><?=$conv['last_date']?date('d/m',strtotime($conv['last_date'])):''?></span>
            </div>
            <div class="msg-preview"><?=ucfirst($conv['role'])?></div>
          </div>
          <?php if($conv['unread']>0):?><div class="msg-unread"></div><?php endif;?>
        </a>
      <?php endforeach;endif;?>
      <div class="new-msg">
        <form method="GET"><input type="hidden" name="tab" value="messages">
          <select name="with" onchange="this.form.submit()">
            <option value="">+ Nouvelle conversation</option>
            <?php foreach($all_users as $u):?><option value="<?=$u['id_user']?>" <?=$u['id_user']==$sel_uid?'selected':''?>><?=htmlspecialchars($u['prenom'].' '.$u['nom'])?> (<?=$u['role']?>)</option><?php endforeach;?>
          </select>
        </form>
      </div>
    </div>
    <div class="chat-area">
      <?php if($chat_user):?>
      <div class="chat-hdr">
        <div class="mav" style="width:36px;height:36px;background:var(--primary-light);color:var(--primary)"><?=initials($chat_user['nom'],$chat_user['prenom'])?></div>
        <div><div style="font-weight:600;font-size:14px"><?=htmlspecialchars($chat_user['prenom'].' '.$chat_user['nom'])?></div>
        <div style="font-size:11px;color:var(--muted)"><?=ucfirst($chat_user['role'])?> · <?=htmlspecialchars($chat_user['email'])?></div></div>
      </div>
      <div class="chat-msgs" id="chat-messages">
        <?php if(empty($chat_msgs)):?>
        <div style="text-align:center;color:var(--muted);font-size:13px;padding:30px" data-placeholder>Commencez la conversation</div>
        <?php else:foreach($chat_msgs as $m):$ia=$m['id_expediteur']==$admin_id;?>
          <div class="mbw <?=$ia?'right':''?>" data-msgid="<?=$m['id_message']?>">
            <div class="mav" style="width:26px;height:26px;font-size:10px;background:<?=$ia?'var(--primary);color:#fff':'var(--primary-light);color:var(--primary)'?>"><?=$ia?initials($admin_info['nom'],$admin_info['prenom']):initials($chat_user['nom'],$chat_user['prenom'])?></div>
            <div class="bubble-inner"><div class="bubble <?=$ia?'adm':'other'?>"><?=nl2br(htmlspecialchars($m['contenu']))?></div>
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
          <textarea id="chat-textarea" name="contenu" placeholder="Écrire un message…" autocomplete="off" rows="1"></textarea>
          <button type="submit" class="send-btn">Envoyer</button>
        </form>
      </div>
      <?php else:?>
      <div style="flex:1;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:14px">Sélectionnez une conversation</div>
      <?php endif;?>
    </div>
  </div>

  <?php elseif($active_tab==='litiges'): ?>
  <!-- ══ LITIGES ══ -->
  <div class="sec-hdr"><div class="sec-title">Litiges ouverts (<?=count($litiges_open)?>)</div></div>
  <?php if(empty($litiges_open)):?>
    <div class="empty"><div class="ico">✅</div><p>Aucun litige actif — tout va bien !</p></div>
  <?php else:foreach($litiges_open as $l):?>
    <div class="litige-card">
      <div>
        <div class="lit-id">#LIT-<?=str_pad($l['id_litige'],3,'0',STR_PAD_LEFT)?> · <?=strtoupper($l['statut_litige']??'OUVERT')?></div>
        <div class="lit-title"><?=htmlspecialchars(substr($l['description_litige']??'Sans description',0,80))?></div>
        <div class="lit-desc"><?=htmlspecialchars($l['description_litige']??'')?></div>
        <div class="lit-meta">
          <span>Client: <?=htmlspecialchars($l['client_prenom'].' '.$l['client_nom'])?></span>
          <span>Technicien: <?=htmlspecialchars($l['tech_prenom'].' '.$l['tech_nom'])?></span>
          <span>Réservation: #<?=$l['id_service']?></span>
          <?php if($l['prix_estime']&&$l['prix_final']&&$l['prix_estime']!=$l['prix_final']):?>
          <span style="color:var(--red)">Devis: <?=number_format($l['prix_estime'],0)?> DA → Facturé: <?=number_format($l['prix_final'],0)?> DA</span>
          <?php endif;?>
          <span>Signalé: <?=$l['date_overture']?date('d/m/Y',strtotime($l['date_overture'])):'—'?></span>
        </div>
      </div>
      <div class="lit-act">
        <?=chip($l['statut_litige']??'ouvert')?>
        <button onclick="openLitigeModal(<?=$l['id_litige']?>,'<?=htmlspecialchars($l['client_prenom'].' '.$l['client_nom'],ENT_QUOTES)?>')" class="btn btn-g btn-sm">✓ Résoudre</button>
        <a href="?tab=messages&with=<?=$l['client_uid']?>" class="btn btn-ghost btn-sm">Contacter</a>
      </div>
    </div>
  <?php endforeach;endif;?>

  <?php elseif($active_tab==='statistiques'):
    $verified=$pdo->query("SELECT COUNT(*) FROM technicien WHERE verifie=1")->fetchColumn();
    $this_month=$pdo->query("SELECT COUNT(*) FROM service WHERE MONTH(date_service)=MONTH(CURDATE()) AND YEAR(date_service)=YEAR(CURDATE())")->fetchColumn();
    $total_l=$pdo->query("SELECT COUNT(*) FROM litige")->fetchColumn();
    $resolved_l=$pdo->query("SELECT COUNT(*) FROM litige WHERE statut_litige='résolu'")->fetchColumn();
  ?>
  <!-- ══ STATISTIQUES ══ -->
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Total utilisateurs</div><div class="stat-val" style="color:var(--primary)"><?=$stats['techniciens']+$stats['clients']?></div><div class="stat-sub">Techniciens + Clients</div></div>
    <div class="stat-card"><div class="stat-label">Techniciens vérifiés</div><div class="stat-val"><?=$verified?></div><div class="stat-sub"><span><?=$stats['techniciens']>0?round($verified/$stats['techniciens']*100):0?>%</span> du total</div></div>
    <div class="stat-card"><div class="stat-label">Services ce mois</div><div class="stat-val"><?=$this_month?></div><div class="stat-sub" style="color:var(--muted)">Mois en cours</div></div>
    <div class="stat-card"><div class="stat-label">Résolution litiges</div><div class="stat-val"><?=$total_l>0?round($resolved_l/$total_l*100):100?><small style="font-size:16px">%</small></div><div class="stat-sub"><span>Excellent</span></div></div>
  </div>
  <div class="two-col">
    <div class="card" style="padding:18px">
      <div class="sec-title" style="margin-bottom:14px">Réservations par mois</div>
      <?php if(!empty($monthly)):$max=max(array_column($monthly,'total'))?:1;?>
      <div class="chart-bars">
        <?php foreach($monthly as $m):?><div class="bar" style="height:<?=round($m['total']/$max*100)?>%" title="<?=$m['month']?>: <?=$m['total']?>"></div><?php endforeach;?>
      </div>
      <div style="display:flex;justify-content:space-between;padding:6px 14px 0;font-size:10px;color:var(--muted)">
        <?php foreach($monthly as $m):?><span><?=$m['month']?></span><?php endforeach;?>
      </div>
      <?php else:?><div class="empty" style="padding:30px 0"><p>Pas encore de données</p></div><?php endif;?>
    </div>
    <div class="card" style="padding:18px">
      <div class="sec-title" style="margin-bottom:14px">Services populaires</div>
      <?php if(!empty($service_types)):$colors=['var(--primary)','var(--blue)','var(--amber)','var(--red)','#8b5cf6'];?>
      <div style="margin-top:6px">
        <?php foreach($service_types as $i=>$st):$pct=round($st['total']/$total_svc_pct*100);?>
        <div class="pr-row"><div class="pr-lbl"><span><?=htmlspecialchars($st['specialité'])?></span><span style="color:<?=$colors[$i%5]?>;font-weight:600"><?=$pct?>%</span></div>
        <div class="pr-track"><div class="pr-fill" style="width:<?=$pct?>%;background:<?=$colors[$i%5]?>"></div></div></div>
        <?php endforeach;?>
      </div>
      <?php else:?><div class="empty" style="padding:30px 0"><p>Pas encore de données</p></div><?php endif;?>
    </div>
  </div>
  <div class="two-col">
    <div class="card" style="padding:18px">
      <div class="sec-title" style="margin-bottom:12px">Répartition utilisateurs</div>
      <?php $tot=$stats['clients']+$stats['techniciens']?:1;?>
      <div style="display:flex;flex-direction:column;gap:10px">
        <div class="pr-row"><div class="pr-lbl"><span>Clients</span><span style="color:var(--blue);font-weight:600"><?=$stats['clients']?></span></div><div class="pr-track"><div class="pr-fill" style="width:<?=round($stats['clients']/$tot*100)?>%;background:var(--blue)"></div></div></div>
        <div class="pr-row"><div class="pr-lbl"><span>Techniciens</span><span style="color:var(--amber);font-weight:600"><?=$stats['techniciens']?></span></div><div class="pr-track"><div class="pr-fill" style="width:<?=round($stats['techniciens']/$tot*100)?>%;background:var(--amber)"></div></div></div>
      </div>
    </div>
    <div class="card" style="padding:18px">
      <div class="sec-title" style="margin-bottom:12px">Litiges</div>
      <div style="display:flex;gap:24px;align-items:center;padding:10px 0">
        <div style="text-align:center"><div style="font-size:28px;font-weight:800;font-family:'Syne',sans-serif;color:var(--red)"><?=$stats['litiges']?></div><div style="font-size:12px;color:var(--muted)">Ouverts</div></div>
        <div style="text-align:center"><div style="font-size:28px;font-weight:800;font-family:'Syne',sans-serif;color:var(--green)"><?=$resolved_l?></div><div style="font-size:12px;color:var(--muted)">Résolus</div></div>
        <div style="text-align:center"><div style="font-size:28px;font-weight:800;font-family:'Syne',sans-serif;color:var(--text)"><?=$total_l?></div><div style="font-size:12px;color:var(--muted)">Total</div></div>
      </div>
    </div>
  </div>

  <?php elseif($active_tab==='avis'): ?>
  <!-- ══ AVIS MODERATION ══ -->
  <div class="card">
    <div class="card-hdr"><div class="card-title">Modération des avis (<?=count($avis_all)?>)</div></div>
    <?php if(empty($avis_all)):?>
    <div style="padding:48px;text-align:center;color:var(--muted)">Aucun avis</div>
    <?php else:?>
    <table>
      <thead><tr><th>#</th><th>Client</th><th>Technicien</th><th>Note</th><th>Commentaire</th><th>Date</th><th>Statut</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach($avis_all as $a):?>
      <tr>
        <td style="color:var(--muted);font-size:12px">#<?=$a['id_avis']?></td>
        <td><?=htmlspecialchars($a['c_prenom'].' '.$a['c_nom'])?></td>
        <td><div><?=htmlspecialchars($a['t_prenom'].' '.$a['t_nom'])?></div><div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($a['specialité']??'')?></div></td>
        <td style="color:var(--amber);letter-spacing:1px"><?=str_repeat('★',$a['note']).str_repeat('☆',5-$a['note'])?></td>
        <td style="max-width:200px;font-size:13px"><?=htmlspecialchars(substr($a['commentaire']??'—',0,80))?><?=strlen($a['commentaire']??'')>80?'…':''?></td>
        <td style="font-size:12px;color:var(--muted)"><?=$a['date_avis']?date('d/m/Y',strtotime($a['date_avis'])):'—'?></td>
        <td><?=($a['visible']??1)?'<span class="chip chip-green">Visible</span>':'<span class="chip chip-red">Masqué</span>'?></td>
        <td>
          <div style="display:flex;gap:5px">
            <?php if($a['visible']??1):?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="hide_avis"><input type="hidden" name="id_avis" value="<?=$a['id_avis']?>"><input type="hidden" name="tab" value="avis"><button class="btn btn-ghost btn-sm" style="color:var(--amber)">Masquer</button></form>
            <?php else:?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="restore_avis"><input type="hidden" name="id_avis" value="<?=$a['id_avis']?>"><input type="hidden" name="tab" value="avis"><button class="btn btn-ghost btn-sm" style="color:var(--green)">Restaurer</button></form>
            <?php endif;?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet avis ?')"><input type="hidden" name="action" value="delete_avis"><input type="hidden" name="id_avis" value="<?=$a['id_avis']?>"><input type="hidden" name="tab" value="avis"><button class="btn btn-ghost btn-sm" style="color:var(--red)">Supprimer</button></form>
          </div>
        </td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
  </div>

  <?php elseif($active_tab==='signalements'): ?>
  <!-- ══ SIGNALEMENTS ══ -->
  <?php $pending=array_filter($signalements,fn($s)=>($s['statut_signal']??'en attente')==='en attente'); ?>
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
    <div class="sec-title" style="margin:0">Signalements</div>
    <span style="background:rgba(239,68,68,.1);color:#dc2626;border-radius:20px;padding:3px 12px;font-size:12px;font-weight:700"><?=count($pending)?> en attente</span>
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);cursor:pointer;margin-left:auto">
      <input type="checkbox" id="show-treated" onchange="toggleTreated()" style="width:14px;height:14px"> Voir traités (<?=count($signalements)-count($pending)?>)
    </label>
  </div>
  <?php if(empty($signalements)):?>
  <div class="card"><div style="padding:48px;text-align:center;color:var(--muted)">Aucun signalement</div></div>
  <?php else:?>
  <div style="display:flex;flex-direction:column;gap:10px">
  <?php foreach($signalements as $s):
    $sp=($s['statut_signal']??'en attente');
    $is_pending=$sp==='en attente';?>
  <div class="sig-card" data-treated="<?=$is_pending?'0':'1'?>" style="background:#fff;border:1.5px solid <?=$is_pending?'rgba(239,68,68,.35)':'var(--border)'?>;border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;<?=$is_pending?'':'opacity:.55'?>">
    <div style="flex:1;min-width:200px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap">
        <span style="font-size:13.5px;font-weight:700;color:var(--text)"><?=htmlspecialchars($s['rep_prenom'].' '.$s['rep_nom'])?></span>
        <span style="font-size:11px;color:var(--muted);background:var(--bg);border:1px solid var(--border);border-radius:5px;padding:1px 7px"><?=ucfirst($s['rep_role']??'')?></span>
        <span class="chip chip-red" style="font-size:11px"><?=htmlspecialchars($s['motif']??'—')?></span>
      </div>
      <?php if($s['description']):?>
      <div style="font-size:12.5px;color:var(--text2);line-height:1.55;margin-bottom:4px"><?=htmlspecialchars(mb_substr($s['description']??'',0,150))?><?=mb_strlen($s['description']??'')>150?'…':''?></div>
      <?php endif;?>
      <div style="font-size:11px;color:var(--muted)">Signalé par : <strong><?=htmlspecialchars(($s['sig_prenom']??'Anonyme').' '.($s['sig_nom']??''))?></strong> · <?=$s['date_signalement']?date('d/m/Y',strtotime($s['date_signalement'])):'—'?></div>
    </div>
    <div style="display:flex;gap:6px;flex-shrink:0;align-items:center">
      <?php if($is_pending):?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="handle_signalement">
          <input type="hidden" name="id_signalement" value="<?=$s['id_signalement']?>">
          <input type="hidden" name="statut_signal" value="traité">
          <input type="hidden" name="tab" value="signalements">
          <button class="btn btn-g btn-sm">✓ Marquer traité</button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Suspendre cet utilisateur ?')">
          <input type="hidden" name="action" value="suspend_user">
          <input type="hidden" name="id_user" value="<?=$s['id_user']?>">
          <input type="hidden" name="tab" value="signalements">
          <button class="btn btn-r btn-sm">⛔ Suspendre</button>
        </form>
        <a href="?tab=messages&with=<?=$s['id_user']?>" class="btn btn-ghost btn-sm">✉</a>
      <?php else:?>
        <span style="font-size:12px;color:var(--green);font-weight:600;padding:4px 10px;background:rgba(22,163,74,.08);border:1px solid rgba(22,163,74,.2);border-radius:8px">✓ Traité</span>
      <?php endif;?>
    </div>
  </div>
  <?php endforeach;?>
  </div>
  <script>
  function toggleTreated(){var s=document.getElementById('show-treated').checked;document.querySelectorAll('.sig-card[data-treated="1"]').forEach(function(r){r.style.display=s?'flex':'none';});}
  document.addEventListener('DOMContentLoaded',function(){toggleTreated();});
  </script>
  <?php endif;?>

  <?php elseif($active_tab==='factures'): ?>
  <!-- ══ FACTURES ══ -->
  <?php if(!empty($services_no_facture)):?>
  <div class="card" style="margin-bottom:20px">
    <div class="card-hdr"><div class="card-title">⚠️ Services sans facture (<?=count($services_no_facture)?>)</div></div>
    <table id="factures-pending-table">
      <thead><tr><th>#Service</th><th>Client</th><th>Technicien</th><th>Date</th><th>Montant</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach($services_no_facture as $s):?>
      <?php $pending_fact_search = htmlspecialchars(strtolower(trim(($s['c_prenom']??'').' '.($s['c_nom']??'').' '.($s['t_prenom']??'').' '.($s['t_nom']??'').' '.($s['specialité']??'').' '.($s['date_service']??'').' '.number_format($s['prix_final']??$s['prix_estime']??0,0)))); ?>
      <tr data-search="<?=$pending_fact_search?>">
        <td style="color:var(--muted);font-size:12px">#<?=$s['id_service']?></td>
        <td><?=htmlspecialchars($s['c_prenom'].' '.$s['c_nom'])?></td>
        <td><?=htmlspecialchars($s['t_prenom'].' '.$s['t_nom'])?> <span style="font-size:11px;color:var(--muted)">(<?=htmlspecialchars($s['specialité']??'—')?>)</span></td>
        <td style="font-size:12px;color:var(--muted)"><?=$s['date_service']?date('d/m/Y',strtotime($s['date_service'])):'—'?></td>
        <td style="color:var(--green);font-weight:700"><?=number_format($s['prix_final']??$s['prix_estime']??0,0)?> DA</td>
        <td>
          <?php $s_amount = floatval($s['prix_final'] ?? $s['prix_estime'] ?? 0); ?>
          <?php if($s_amount >= facture_tva_threshold()): ?>
          <form method="POST" style="display:inline"><input type="hidden" name="action" value="gen_facture"><input type="hidden" name="id_service" value="<?=$s['id_service']?>"><input type="hidden" name="montant" value="<?=$s_amount?>"><input type="hidden" name="tab" value="factures"><button class="btn btn-primary btn-sm">🧾 Générer</button></form>
          <?php else: ?>
          <span style="font-size:12px;color:var(--muted)">Paiement uniquement</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif;?>
  <div class="card">
    <div class="card-hdr"><div class="card-title">Toutes les factures (<?=count($factures)?>)</div></div>
    <div style="padding:12px 18px;border-bottom:1px solid var(--border);background:var(--surface2)">
      <input id="facture-search" type="text" placeholder="🔍 Rechercher facture, client, technicien, prestation…" oninput="filterFactures()" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;outline:none;background:#fff">
    </div>
    <?php if(empty($factures)):?>
    <div style="padding:48px;text-align:center;color:var(--muted)">Aucune facture générée</div>
    <?php else:?>
    <table id="factures-table">
      <thead><tr><th>#Facture</th><th>Client</th><th>Technicien</th><th>Prestations</th><th>Date</th><th>Montant</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach($factures as $f):
        $fprests = $admin_fac_prests[(int)$f['id_service']] ?? [];
        $fact_search = htmlspecialchars(strtolower(trim(($f['c_prenom']??'').' '.($f['c_nom']??'').' '.($f['t_prenom']??'').' '.($f['t_nom']??'').' '.implode(' ',$fprests).' '.($f['montant_total']??'')))); ?>
      <tr data-search="<?=$fact_search?>">
        <td style="color:var(--muted);font-size:12px">FAC-<?=str_pad($f['id_facture'],6,'0',STR_PAD_LEFT)?></td>
        <td><?=htmlspecialchars($f['c_prenom'].' '.$f['c_nom'])?></td>
        <td><?=htmlspecialchars($f['t_prenom'].' '.$f['t_nom'])?></td>
        <td style="max-width:180px">
          <?php if(!empty($fprests)):?>
          <div style="display:flex;flex-wrap:wrap;gap:3px">
            <?php foreach($fprests as $pn):?>
            <span style="background:var(--primary-light);color:var(--primary);border-radius:5px;padding:2px 7px;font-size:11px;font-weight:600"><?=htmlspecialchars($pn)?></span>
            <?php endforeach;?>
          </div>
          <?php else:?><span style="color:var(--muted);font-size:12px">—</span><?php endif;?>
        </td>
        <td style="font-size:12px;color:var(--muted)"><?=$f['date_facture']?date('d/m/Y',strtotime($f['date_facture'])):'—'?></td>
        <td style="color:var(--green);font-weight:700"><?=number_format($f['montant_total']??0,0)?> DA</td>
        <td><a href="facture.php?id=<?=$f['id_facture']?>" target="_blank" class="btn btn-ghost btn-sm">🧾 Voir</a></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
  </div>

  <?php elseif($active_tab==='commissions'): ?>
  <!-- ══ COMMISSIONS ══ -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:18px">
    <div class="card" style="padding:18px 20px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:6px">Ce mois à collecter</div>
      <div style="font-size:22px;font-weight:800;color:var(--primary)"><?=number_format($current_month_due,0,',',' ')?> <span style="font-size:13px;font-weight:500">DA</span></div>
      <div style="font-size:11px;color:var(--muted);margin-top:3px"><?=number_format($current_month_techs_due)?> technicien(s)</div>
    </div>
    <div class="card" style="padding:18px 20px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:6px">Volume d'affaires</div>
      <div style="font-size:22px;font-weight:800;color:var(--text)"><?=number_format($comm_stats['volume_affaires']??0,0,',',' ')?> <span style="font-size:13px;font-weight:500">DA</span></div>
    </div>
    <div class="card" style="padding:18px 20px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:6px">Total commissions</div>
      <div style="font-size:22px;font-weight:800;color:var(--primary)"><?=number_format($comm_stats['total_montant']??0,0,',',' ')?> <span style="font-size:13px;font-weight:500">DA</span></div>
    </div>
    <div class="card" style="padding:18px 20px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:6px">Encore dues</div>
      <div style="font-size:22px;font-weight:800;color:#ef4444"><?=number_format($comm_stats['montant_du']??0,0,',',' ')?> <span style="font-size:13px;font-weight:500">DA</span></div>
    </div>
    <div class="card" style="padding:18px 20px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:6px">Déjà reçues</div>
      <div style="font-size:22px;font-weight:800;color:var(--green)"><?=number_format($comm_stats['montant_recu']??0,0,',',' ')?> <span style="font-size:13px;font-weight:500">DA</span></div>
    </div>
  </div>

  <div class="card" style="margin-bottom:18px">
    <div class="card-hdr" style="padding:20px 22px 16px">
      <div class="card-title" style="font-size:16px">🔔 Rappels mensuels</div>
      
    
    </div>
    <div style="padding:0 18px 18px;display:flex;flex-direction:column;gap:10px">
      <?php if(empty($tech_debts)): ?>
        <div style="padding:16px 20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;color:#166534;font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px">
          <span style="font-size:20px">✅</span> Aucun technicien n'a de dette à régler pour le moment.
        </div>
      <?php else: ?>
        <?php foreach($tech_debts as $td): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 18px">
          <div>
            <div style="font-weight:800;font-size:14px"><?=htmlspecialchars(($td['prenom']??'').' '.($td['nom']??''))?></div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px"><?=htmlspecialchars($td['specialité']??'')?> · <?=htmlspecialchars($td['email']??'')?></div>
          </div>
          <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <div style="font-size:18px;font-weight:800;color:#c2410c"><?=number_format($td['montant_du'],0,',',' ')?> DA</div>
            <div style="font-size:12px;color:#92400e;background:#fef3c7;padding:5px 12px;border-radius:8px;font-weight:600">Voir détail ci-dessous ↓</div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-hdr" style="padding:20px 22px 16px">
      <div class="card-title" style="font-size:16px">💰 Commissions par mois et par technicien</div>
    </div>
    <div style="padding:0 18px 12px">
      <input type="text" id="comm-search" placeholder="🔍 Rechercher technicien, spécialité, mois…"
        style="width:100%;max-width:420px;padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface2);color:var(--text)"
        oninput="filterComm(this.value)">
    </div>

    <?php if(empty($commission_months)):?>
      <div style="padding:48px;text-align:center;color:var(--muted)">Aucune commission pour le moment.</div>
    <?php else:?>
    <div style="overflow-x:auto;padding:0 12px 14px">
      <table id="comm-table" style="margin-top:0;min-width:980px">
        <thead>
          <tr>
            <th>Technicien</th>
            <th>Mois</th>
            <th>Interventions</th>
            <th>Total service</th>
            <th>Commission à payer</th>
            <th>Statut</th>
            <th>Actions</th>
            <th>Détails</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($commission_months as $ms):
          $month_key = (int)$ms['id_technicien'] . '|' . ($ms['ym'] ?? '');
          $rows = $commission_month_details[$month_key] ?? [];
          $monthStatus = ((int)($ms['nb_due'] ?? 0) === 0) ? 'payée' : (((int)($ms['nb_payee'] ?? 0) > 0) ? 'partiel' : 'due');
          $statusLabel = $monthStatus === 'payée' ? '✓ Payé' : ($monthStatus === 'partiel' ? 'Partiel' : 'À payer');
          $statusStyle = $monthStatus === 'payée' ? 'chip-green' : ($monthStatus === 'partiel' ? 'chip-blue' : '');
          $search_val = strtolower(trim(($ms['prenom']??'').' '.($ms['nom']??'').' '.($ms['specialité']??'').' '.($ms['month_label']??'').' '.($ms['email']??'')));
          $highlight = (($ms['ym'] ?? '') === $current_month_key) ? 'background:#fffdf5' : '';
        ?>
        <tr class="month-summary-row" data-search="<?=htmlspecialchars($search_val)?>" style="<?=htmlspecialchars($highlight)?>">
          <td>
            <div style="font-weight:800;font-size:13px"><?=htmlspecialchars(($ms['prenom']??'').' '.($ms['nom']??''))?></div>
            <div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($ms['specialité']??'')?> · <?=htmlspecialchars($ms['email']??'')?></div>
          </td>
          <td style="white-space:nowrap;font-weight:700;color:var(--text)"><?=htmlspecialchars($ms['month_label']??'—')?></td>
          <td style="font-weight:700;color:var(--text)"><?=intval($ms['nb_interventions'] ?? 0)?></td>
          <td style="font-weight:700;color:var(--text)"><?=number_format($ms['montant_service_total'] ?? 0,0,',',' ')?> DA</td>
          <td style="font-weight:800;color:<?=((float)($ms['montant_du'] ?? 0) > 0 ? '#dc2626' : 'var(--green)')?>"><?=number_format($ms['montant_du'] ?? 0,0,',',' ')?> DA</td>
          <td><span class="chip <?= $statusStyle ?>" style="font-size:11px"><?=htmlspecialchars($statusLabel)?></span></td>
          <td>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <?php if((float)($ms['montant_du'] ?? 0) > 0): ?>
              <button class="btn btn-primary btn-sm" type="button" onclick="monthAction(event,'settle',this)" data-tech-id="<?=intval($ms['id_technicien'])?>" data-ym="<?=htmlspecialchars($ms['ym'] ?? '')?>" data-type="commission">Marquer payé</button>
              <button class="btn btn-ghost btn-sm" type="button" onclick="monthAction(event,'remind',this)" data-tech-id="<?=intval($ms['id_technicien'])?>" data-ym="<?=htmlspecialchars($ms['ym'] ?? '')?>" data-type="commission">Rappel</button>
              <?php else: ?>
              <span style="font-size:12px;color:var(--muted)"><?=!empty($ms['last_payment']) ? 'Réglé le '.date('d/m/Y', strtotime($ms['last_payment'])) : 'Rien à régler'?></span>
              <?php endif; ?>

            </div>
          </td>
          <td>
            <a href="admin-commission-detail.php?tech=<?=intval($ms['id_technicien'])?>&ym=<?=urlencode($ms['ym'] ?? '')?>" target="_blank" style="font-size:12px;color:var(--primary);font-weight:700;text-decoration:none;white-space:nowrap">
              ▶ Voir <?=count($rows)?> intervention(s)
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <script>
  function filterComm(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#comm-table tbody tr.month-summary-row').forEach(function(r){
      r.style.display = r.dataset.search.includes(q) ? '' : 'none';
    });
  }

  function monthAction(e, actionName, btn) {
    e.preventDefault();
    var techId = btn.dataset.techId;
    var ym = btn.dataset.ym;
    var type = btn.dataset.type || 'commission';
    if (!techId || !ym) return false;
    var promptMsg = actionName === 'settle' ? 'Confirmer le règlement complet de ce mois ?' : 'Envoyer un rappel pour ce mois ?';
    if (!confirm(promptMsg)) return false;
    btn.disabled = true;
    var original = btn.textContent;
    btn.textContent = '⏳...';
    var endpoint = (type === 'tva') ? 'actions/tva_month_action.php' : 'actions/commission_month_action.php';
    fetch(endpoint, {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'action=' + encodeURIComponent(actionName) + '&id_technicien=' + encodeURIComponent(techId) + '&ym=' + encodeURIComponent(ym) + '&type=' + encodeURIComponent(type)
    }).then(function(res){ return res.json(); }).then(function(js){
      if (js && js.ok) {
        if (actionName === 'settle') {
          location.reload();
        } else {
          alert('Rappel envoyé pour ' + (js.month || ym) + ' (' + (js.amount || 0) + ' DA)');
          btn.disabled = false;
          btn.textContent = original;
        }
      } else {
        alert('Erreur: ' + (js && js.error ? js.error : 'server'));
        btn.disabled = false;
        btn.textContent = original;
      }
    }).catch(function(){
      alert('Erreur réseau');
      btn.disabled = false;
      btn.textContent = original;
    });
    return false;
  }
  </script>

  <?php elseif($active_tab==='logs'): ?>
  <!-- ══ LOGS ══ -->
  <div class="card">
    <div class="card-hdr"><div class="card-title">Journal des actions (<?=count($logs)?> dernières entrées)</div></div>
    <?php if(empty($logs)):?>
    <div style="padding:48px;text-align:center;color:var(--muted)">Aucun log encore (les actions seront enregistrées dès maintenant)</div>
    <?php else:?>
    <table>
      <thead><tr><th>Date</th><th>Utilisateur</th><th>Rôle</th><th>Action</th><th>Détails</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach($logs as $l):?>
      <tr>
        <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?=$l['date_action']?date('d/m/Y H:i',strtotime($l['date_action'])):'—'?></td>
        <td style="font-size:13px"><?=htmlspecialchars(($l['prenom']??'Système').' '.($l['nom']??''))?></td>
        <td><span class="chip <?=$l['role']==='admin'?'chip-red':($l['role']==='technicien'?'chip-blue':'chip-green')?>"><?=ucfirst($l['role']??'—')?></span></td>
        <td style="font-family:monospace;font-size:12px;color:var(--primary)"><?=htmlspecialchars($l['action'])?></td>
        <td style="font-size:12px;color:var(--muted);max-width:200px"><?=htmlspecialchars(substr($l['details']??'',0,80))?></td>
        <td style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($l['ip']??'—')?></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
  </div>

  <?php elseif($active_tab==='profil'): ?>
  <!-- ══ PROFIL ══ -->
  <div class="profile-hero">
    <div class="profile-av"><?=initials($admin_info['nom']??'',$admin_info['prenom']??'')?></div>
    <div class="profile-main">
      <div class="profile-name"><?=htmlspecialchars(($admin_info['prenom']??'').' '.($admin_info['nom']??''))?></div>
      <div class="profile-role">Super Administrateur · Khidma DZ</div>
      <div class="profile-meta">
        <div class="meta-item">📧 <strong><?=htmlspecialchars($admin_info['email']??'')?></strong></div>
        <div class="meta-item">📞 <strong><?=htmlspecialchars($admin_info['telephone']??'Non renseigné')?></strong></div>
        <div class="meta-item">📍 <strong><?=htmlspecialchars(wilaya_display($admin_info['ville']??'Algérie', $lang ?? 'fr'))?></strong></div>
        <div class="meta-item">🗓 Depuis <strong><?=$admin_info['date_inscription']?date('M Y',strtotime($admin_info['date_inscription'])):'—'?></strong></div>
      </div>
    </div>
  </div>
  <div id="admin-profile-summary" class="card" style="padding:22px;margin-bottom:14px">
    <div class="sec-title" style="margin-bottom:14px">Mes informations personnelles</div>
    <div class="fg">
      <div class="fgrp"><div class="flbl">Prénom</div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"> <?=htmlspecialchars($admin_info['prenom']??'')?></div></div>
      <div class="fgrp"><div class="flbl">Nom</div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"> <?=htmlspecialchars($admin_info['nom']??'')?></div></div>
      <div class="fgrp"><div class="flbl">Email</div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($admin_info['email']??'')?></div></div>
      <div class="fgrp"><div class="flbl">Téléphone</div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"><?=htmlspecialchars($admin_info['telephone']??'Non renseigné')?></div></div>
      <div class="fgrp"><div class="flbl">Wilaya</div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"><?=htmlspecialchars(wilaya_display($admin_info['ville']??'Algérie', $lang ?? 'fr'))?></div></div>
      <div class="fgrp"><div class="flbl">Depuis</div><div class="finput" style="background:#fff;display:flex;align-items:center;min-height:44px;"><?=$admin_info['date_inscription']?date('M Y',strtotime($admin_info['date_inscription'])):'—'?></div></div>
    </div>
    <div style="display:flex;justify-content:flex-start;margin-top:18px">
      <button class="btn btn-primary" type="button" onclick="toggleAdminProfileEdit(true)">✏️ Modifier</button>
    </div>
  </div>
  <div id="admin-profile-form" class="card" style="padding:22px;display:none">
    <div class="sec-title" style="margin-bottom:16px">Modifier le compte</div>
    <form method="POST" action="actions/handle_update_profile.php">
      <div class="fg">
        <div class="fgrp"><div class="flbl">Prénom</div><input class="finput" name="prenom" value="<?=htmlspecialchars($admin_info['prenom']??'')?>"/></div>
        <div class="fgrp"><div class="flbl">Nom</div><input class="finput" name="nom" value="<?=htmlspecialchars($admin_info['nom']??'')?>"/></div>
        <div class="fgrp"><div class="flbl">Email</div><input class="finput" type="email" name="email" value="<?=htmlspecialchars($admin_info['email']??'')?>"/></div>
        <div class="fgrp"><div class="flbl">Téléphone</div><input class="finput" name="telephone" value="<?=htmlspecialchars($admin_info['telephone']??'')?>"/></div>
        <div class="fgrp"><div class="flbl">Nouveau mot de passe</div><input class="finput" type="password" name="mot_de_passe" placeholder="Laisser vide pour ne pas changer"/></div>
        <div class="fgrp"><div class="flbl">Confirmer mot de passe</div><input class="finput" type="password" name="mot_de_passe_confirm" placeholder="••••••••"/></div>
      </div>
      <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-start">
        <button class="btn btn-primary" type="submit">Enregistrer</button>
        <button type="button" class="btn btn-ghost" onclick="toggleAdminProfileEdit(false)">Annuler</button>
      </div>
    </form>
  </div>

  <!-- ── Créer un admin (super admin only) ── -->
  <?php if((int)$_SESSION['user_id'] === 12): ?>
  <div class="card" style="padding:22px;margin-top:18px;border:1.5px solid var(--border)">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
      <div style="width:34px;height:34px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:16px">🛡</div>
      <div>
        <div class="sec-title" style="margin:0">Créer un nouvel administrateur</div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">Le compte aura accès complet au tableau de bord admin.</div>
      </div>
    </div>
    <div style="height:1px;background:var(--border);margin:16px 0"></div>
    <?php
    $admin_exists_err = $_GET['admin_err'] ?? null;
    $admin_ok = $_GET['admin_ok'] ?? null;
    if($admin_exists_err):?>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:14px">⚠️ <?=htmlspecialchars($admin_exists_err)?></div>
    <?php endif; if($admin_ok):?>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:14px">✓ Administrateur créé avec succès.</div>
    <?php endif;?>
    <form method="POST" action="actions/handle_create_admin.php">
      <div class="fg">
        <div class="fgrp"><div class="flbl">Prénom</div><input class="finput" name="prenom" required placeholder="Prénom"/></div>
        <div class="fgrp"><div class="flbl">Nom</div><input class="finput" name="nom" required placeholder="Nom"/></div>
        <div class="fgrp"><div class="flbl">Email</div><input class="finput" type="email" name="email" required placeholder="email@khidma.dz"/></div>
        <div class="fgrp"><div class="flbl">Téléphone</div><input class="finput" name="telephone" placeholder="05XXXXXXXX"/></div>
        <div class="fgrp"><div class="flbl">Mot de passe</div><input class="finput" type="password" name="mot_de_passe" required placeholder="Min. 8 caractères"/></div>
        <div class="fgrp"><div class="flbl">Confirmer mot de passe</div><input class="finput" type="password" name="mot_de_passe_confirm" required placeholder="••••••••"/></div>
      </div>
      <div style="display:flex;gap:8px;margin-top:18px;align-items:center">
        <button class="btn btn-primary" type="submit" onclick="return confirm('Créer ce compte administrateur ?')">🛡 Créer l\'administrateur</button>
        <span style="font-size:12px;color:var(--muted)">Cette action est irréversible depuis l\'interface.</span>
      </div>
    </form>
  </div>
  <?php endif; // super admin ?>
  <?php endif;?>

  </div><!-- /content -->
</main>

<!-- LITIGE RESOLUTION MODAL -->
<div class="modal-overlay" id="litige-modal">
  <div class="modal">
    <div class="modal-hdr"><div class="modal-title" id="lit-modal-title">Résoudre le litige</div><div class="close-btn" onclick="document.getElementById('litige-modal').classList.remove('open')">✕</div></div>
    <div style="padding:22px 26px">
      <form method="POST">
        <input type="hidden" name="action" value="resolve_litige">
        <input type="hidden" name="tab" value="litiges">
        <input type="hidden" name="id_litige" id="lit-id">
        <div style="margin-bottom:14px;font-size:13px;color:var(--muted)">Client : <strong id="lit-client-name"></strong></div>
        <div style="margin-bottom:14px">
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px">Décision</div>
          <select name="statut_litige" class="finput" required>
            <option value="resolu">✅ Résolu — en faveur du client</option>
            <option value="resolu_tech">✅ Résolu — en faveur du technicien</option>
            <option value="ferme">🔒 Fermé sans suite</option>
          </select>
        </div>
        <div style="margin-bottom:14px">
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:6px">Message de résolution</div>
          <textarea name="resolution" class="finput" rows="4" placeholder="Expliquez la décision prise…" style="width:100%;resize:vertical"></textarea>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('litige-modal').classList.remove('open')">Annuler</button>
          <button type="submit" class="btn btn-primary">✓ Confirmer la résolution</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CV MODAL -->
<div class="modal-overlay" id="cv-modal">
  <div class="modal">
    <div class="modal-hdr"><div class="modal-title" id="cv-title">CV Technicien</div><div class="close-btn" onclick="closeCV()">✕</div></div>
    <div class="modal-body" id="cv-body"></div>
    <div class="modal-footer" id="cv-footer"></div>
  </div>
</div>

<!-- TOASTS -->
<div class="toast-cont" id="toast-cont">
<?php if($toast):
  $ttype = $toast['type'];
  $tclass = in_array($ttype,['ok','success']) ? 'success' : 'error';
  $ticon  = in_array($ttype,['ok','success']) ? '✅' : '❌';
?>
<div class="toast <?=$tclass?>" id="php-toast"><?=$ticon?> <span><?=htmlspecialchars($toast['msg'])?></span></div>
<?php endif;?>
</div>

<script>
// Auto-dismiss toast
const pt = document.getElementById('php-toast');
if(pt) setTimeout(()=>{pt.style.opacity='0';pt.style.transition='opacity .4s';setTimeout(()=>pt.remove(),400);},3500);

// CV Modal
function openCV(d) {
  document.getElementById('cv-title').textContent = 'CV — '+(d.prenom||'')+' '+(d.nom||'');
  document.getElementById('cv-body').innerHTML = `
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--border)">
      <div style="width:52px;height:52px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;font-family:'Syne',sans-serif;color:var(--primary);border:2px solid var(--primary)">
        ${(d.prenom||'?')[0]}${(d.nom||'?')[0]}
      </div>
      <div>
        <div style="font-family:'Syne',sans-serif;font-size:18px;font-weight:700">${(d.prenom||'')+' '+(d.nom||'')}</div>
        <div style="color:var(--muted);font-size:12px;margin-top:2px">${d.specialité||'—'} · ${d.ville||'—'}</div>
      </div>
      <span class="chip ${d.verifie?'chip-green':'chip-amber'}" style="margin-left:auto">${d.verifie?'Approuvé':'En attente'}</span>
    </div>
    <div class="cv-sec">
      <div class="cv-sec-title">Informations</div>
      <div class="cv-row"><span class="lbl">Spécialité</span><strong>${d.specialité||'—'}</strong></div>
      <div class="cv-row"><span class="lbl">Ville</span><strong>${d.ville||'—'}</strong></div>
      <div class="cv-row"><span class="lbl">Expérience</span><strong>${d.experience?d.experience+' ans':'—'}</strong></div>
      <div class="cv-row"><span class="lbl">Email</span><strong>${d.email||'—'}</strong></div>
      <div class="cv-row"><span class="lbl">Téléphone</span><strong>${d.telephone||'—'}</strong></div>
    </div>
    ${d.description_profil?`<div class="cv-sec"><div class="cv-sec-title">Description</div><p style="font-size:13px;color:var(--muted);line-height:1.6">${d.description_profil}</p></div>`:''}
    <div class="cv-sec">
      <div class="cv-sec-title">Documents</div>
      <div style="display:flex;gap:8px;margin-top:4px">
        ${(d.cv||d.cv_name)?`<a href="uploads/cv/${d.cv||d.cv_name}" target="_blank" class="btn btn-ghost" style="flex:1;justify-content:center;border-radius:9px">📄 Voir le CV</a>`:'<div style="background:var(--surface2);border:1px dashed var(--border);border-radius:9px;padding:10px;font-size:13px;flex:1;text-align:center;color:var(--muted)">Pas de CV soumis</div>'}
        ${d['diplôme']?`<a href="uploads/cv/${d['diplôme']}" target="_blank" class="btn btn-ghost" style="flex:1;justify-content:center;border-radius:9px">📜 Diplôme</a>`:'<div style="background:var(--surface2);border:1px dashed var(--border);border-radius:9px;padding:10px;font-size:13px;flex:1;text-align:center;color:var(--muted)">Pas de diplôme</div>'}
      </div>
    </div>`;

  const footer = document.getElementById('cv-footer');
  footer.innerHTML = !d.verifie
    ? `<button class="btn btn-ghost" onclick="closeCV()">Annuler</button>
       <form method="POST" style="display:inline"><input type="hidden" name="action" value="reject_tech"><input type="hidden" name="id_technicien" value="${d.id_technicien}"><button class="btn btn-r">Rejeter</button></form>
       <form method="POST" style="display:inline"><input type="hidden" name="action" value="approve_tech"><input type="hidden" name="id_technicien" value="${d.id_technicien}"><button class="btn btn-primary">✓ Approuver</button></form>`
    : `<button class="btn btn-ghost" onclick="closeCV()">Fermer</button>`;

  document.getElementById('cv-modal').classList.add('open');
}
function closeCV(){document.getElementById('cv-modal').classList.remove('open');}
document.getElementById('cv-modal').addEventListener('click',function(e){if(e.target===this)closeCV();});

// Litige Resolution Modal
function openLitigeModal(id, clientName) {
  document.getElementById('lit-id').value = id;
  document.getElementById('lit-modal-title').textContent = 'Résoudre le litige #LIT-'+String(id).padStart(3,'0');
  document.getElementById('lit-client-name').textContent = clientName;
  document.getElementById('litige-modal').classList.add('open');
}
document.getElementById('litige-modal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});

function filterTechs(sel){
  var q = '';
  var searchTech = document.getElementById('tech-search');
  var searchValidation = document.getElementById('validation-search');
  if (searchTech && searchTech.value) q = searchTech.value.toLowerCase();
  if (searchValidation && searchValidation.value) q = searchValidation.value.toLowerCase();

  var st = 'all';
  if (typeof sel === 'string' && sel) {
    st = sel;
  } else {
    var techFilter = document.getElementById('tech-filter');
    if (techFilter && techFilter.value) st = techFilter.value;
  }

  document.querySelectorAll('#techs-table tbody tr').forEach(function(r){
    var text = (r.dataset.search || '').toLowerCase();
    var mq = !q || text.includes(q);
    var ms = !st || st==='all' || (r.dataset.status||'')===st;
    r.style.display = (mq && ms) ? '' : 'none';
  });

  document.querySelectorAll('.validation-tech-card').forEach(function(card){
    var text = (card.dataset.search || '').toLowerCase();
    card.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
}

function filterMsgs(v){document.querySelectorAll('.msg-item').forEach(i=>{const n=i.querySelector('.msg-sender')?.textContent.toLowerCase()||'';i.style.display=n.includes(v.toLowerCase())?'':'none';});}

function toggleAdminProfileEdit(showEdit){
  const summary=document.getElementById('admin-profile-summary');
  const form=document.getElementById('admin-profile-form');
  if(!form||!summary) return;
  summary.style.display = showEdit ? 'none' : 'block';
  form.style.display = showEdit ? 'block' : 'none';
  window.scrollTo({top:0, behavior:'smooth'});
}

const cm=document.getElementById('chat-messages');if(cm)cm.scrollTop=cm.scrollHeight;
</script>
<script>
window.KhidmaRT = {
  role: 'admin',
  base: '',
  myId: <?=$admin_id?>,
  me: { initials: 'AD' },
  seed: {
    notif_id: <?=(int)$seed_notif_id?>,
    msg_id:   <?=(int)$seed_msg_id?>,
    user_id:  <?=(int)$seed_user_id?>,
  }
};
</script>
<script src="js/khidma-realtime.js"></script>
<script src="js/khidma-pwa.js" defer></script>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<nav class="mob-nav">
  <a href="?tab=dashboard" class="mob-tab <?=$active_tab==='dashboard'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a href="?tab=validation" class="mob-tab <?=$active_tab==='validation'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    Validation
  </a>
  <a href="?tab=reservations" class="mob-tab <?=$active_tab==='reservations'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    Réservations
  </a>
  <a href="?tab=messages" class="mob-tab <?=$active_tab==='messages'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <?php $unread_adm=array_sum(array_column($conversations,'unread'));if($unread_adm>0):?><span class="mob-badge nav-badge-msg"><?=$unread_adm?></span><?php endif;?>
    Messages
  </a>
  <a href="?tab=litiges" class="mob-tab <?=$active_tab==='litiges'?'active':''?>">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Litiges
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