<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }

$user_id     = $_SESSION['user_id'];
$role        = $_SESSION['user_role'];
$id_reported = intval($_POST['id_user_signale'] ?? 0);
$motif       = trim($_POST['motif'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$id_reported || !$motif) {
    $_SESSION['toast'] = ['msg' => 'Veuillez remplir tous les champs.', 'type' => 'err'];
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboard-client.php')); exit();
}

// Prevent self-reporting
if ($id_reported == $user_id) {
    $_SESSION['toast'] = ['msg' => 'Vous ne pouvez pas vous signaler vous-même.', 'type' => 'err'];
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboard-client.php')); exit();
}

$pdo->prepare("INSERT INTO signalement (id_user, id_signaleur, motif, description, date_signalement, statut_signal) VALUES (?,?,?,?,CURDATE(),'en attente')")
    ->execute([$id_reported, $user_id, $motif, $description]);

// Notify admins
$admins = $pdo->query("SELECT id_user FROM utilisateur WHERE role='admin'")->fetchAll();
foreach ($admins as $adm) {
    notifier($pdo, $adm['id_user'], 'Nouveau signalement', 'Un utilisateur a été signalé (ID:'.$id_reported.') — Motif: '.$motif, '../dashboard-admin.php?tab=signalements');
}

log_action($pdo, $user_id, 'signalement', 'Signalement de user #'.$id_reported.' motif: '.$motif);
$_SESSION['toast'] = ['msg' => 'Signalement envoyé. Merci.', 'type' => 'ok'];

$redirect = $role === 'client' ? '../dashboard-client.php' : '../dashboard-tech.php';
header('Location: ' . $redirect); exit();