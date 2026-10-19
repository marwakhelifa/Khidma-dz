<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['user_role'];
$action  = $_POST['action'] ?? '';

// CLIENT: open a litige
if ($action === 'open' && $role === 'client') {
    $id_service = intval($_POST['id_service'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    if (!$id_service || !$description) {
        $_SESSION['toast'] = ['msg' => 'Veuillez remplir tous les champs.', 'type' => 'err'];
        header('Location: ../dashboard-client.php?tab=litiges'); exit();
    }
    // Get client_id and tech info
    $s = $pdo->prepare("SELECT s.*, t.id_technicien, ut.id_user as tech_uid FROM service s JOIN technicien t ON t.id_technicien=s.id_technicien JOIN utilisateur ut ON ut.id_user=t.id_user WHERE s.id_service=?");
    $s->execute([$id_service]); $svc = $s->fetch();
    if (!$svc) { $_SESSION['toast']=['msg'=>'Service introuvable','type'=>'err']; header('Location: ../dashboard-client.php?tab=litiges'); exit(); }

    $pdo->prepare("INSERT INTO litige (id_service, id_client, id_technicien, description_litige, statut_litige, date_overture) VALUES (?,?,?,?,'ouvert',CURDATE())")
        ->execute([$id_service, $svc['id_client'], $svc['id_technicien'], $description]);
    $id_litige = $pdo->lastInsertId();

    // Notify admin(s)
    $admins = $pdo->query("SELECT id_user FROM utilisateur WHERE role='admin'")->fetchAll();
    foreach ($admins as $adm) {
        notifier($pdo, $adm['id_user'], 'Nouveau litige #'.$id_litige, 'Un client a ouvert un litige pour le service #'.$id_service, '../dashboard-admin.php?tab=litiges');
    }
    // Notify tech
    notifier($pdo, $svc['tech_uid'], 'Litige ouvert', 'Un litige a été ouvert concernant votre service #'.$id_service, '../dashboard-tech.php?tab=reservations');

    log_action($pdo, $user_id, 'open_litige', 'Litige #'.$id_litige.' service #'.$id_service);
    $_SESSION['toast'] = ['msg' => 'Litige soumis. L\'administrateur va examiner votre demande.', 'type' => 'ok'];
    header('Location: ../dashboard-client.php?tab=litiges'); exit();
}

// ADMIN: update litige status + resolution
if ($action === 'resolve' && $role === 'admin') {
    $id_litige  = intval($_POST['id_litige'] ?? 0);
    $statut     = $_POST['statut_litige'] ?? 'resolu';
    $resolution = trim($_POST['resolution'] ?? '');

    $pdo->prepare("UPDATE litige SET statut_litige=?, resolution=?, date_resolution=CURDATE() WHERE id_litige=?")
        ->execute([$statut, $resolution, $id_litige]);

    // Notify client and tech
    $l = $pdo->prepare("SELECT l.*, uc.id_user as client_uid, ut.id_user as tech_uid FROM litige l JOIN client c ON c.id_client=l.id_client JOIN utilisateur uc ON uc.id_user=c.id_user JOIN technicien t ON t.id_technicien=l.id_technicien JOIN utilisateur ut ON ut.id_user=t.id_user WHERE l.id_litige=?");
    $l->execute([$id_litige]); $lit = $l->fetch();
    if ($lit) {
        notifier($pdo, $lit['client_uid'], 'Litige #'.$id_litige.' '.$statut, 'Votre litige a été traité : '.($resolution?:'Voir le tableau de bord'), '../dashboard-client.php?tab=litiges');
        notifier($pdo, $lit['tech_uid'],   'Litige #'.$id_litige.' '.$statut, 'Le litige a été traité par l\'administrateur.', '../dashboard-tech.php');
    }

    log_action($pdo, $user_id, 'resolve_litige', 'Litige #'.$id_litige.' → '.$statut);
    $_SESSION['toast'] = ['msg' => 'Litige mis à jour.', 'type' => 'ok'];
    header('Location: ../dashboard-admin.php?tab=litiges'); exit();
}

$_SESSION['toast'] = ['msg' => 'Action non autorisée.', 'type' => 'err'];
header('Location: ../login.php'); exit();