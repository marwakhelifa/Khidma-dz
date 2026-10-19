<?php
session_start();
require_once '../includes/db.php';

// Only the super admin (id=12) can create another admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}
if ((int)$_SESSION['user_id'] !== 12) {
    $_SESSION['toast'] = ['msg' => 'Action réservée au super administrateur.', 'type' => 'error'];
    header("Location: ../dashboard-admin.php?tab=profil"); exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard-admin.php?tab=profil"); exit();
}

$nom    = trim($_POST['nom']    ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$email  = trim($_POST['email']  ?? '');
$tel    = trim($_POST['telephone'] ?? '');
$pwd    = $_POST['mot_de_passe']         ?? '';
$pwd2   = $_POST['mot_de_passe_confirm'] ?? '';

// Basic validation
if (!$nom || !$prenom || !$email || !$pwd) {
    $_SESSION['toast'] = ['msg' => 'Tous les champs obligatoires doivent être remplis.', 'type' => 'error'];
    header("Location: ../dashboard-admin.php?tab=profil"); exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['toast'] = ['msg' => 'Adresse email invalide.', 'type' => 'error'];
    header("Location: ../dashboard-admin.php?tab=profil"); exit();
}

if (strlen($pwd) < 8) {
    $_SESSION['toast'] = ['msg' => 'Le mot de passe doit contenir au moins 8 caractères.', 'type' => 'error'];
    header("Location: ../dashboard-admin.php?tab=profil"); exit();
}

if ($pwd !== $pwd2) {
    $_SESSION['toast'] = ['msg' => 'Les mots de passe ne correspondent pas.', 'type' => 'error'];
    header("Location: ../dashboard-admin.php?tab=profil"); exit();
}

// Check email not already used
$check = $pdo->prepare("SELECT id_user FROM utilisateur WHERE email = ?");
$check->execute([$email]);
if ($check->fetch()) {
    $_SESSION['toast'] = ['msg' => 'Cet email est déjà utilisé par un autre compte.', 'type' => 'error'];
    header("Location: ../dashboard-admin.php?tab=profil"); exit();
}

// Create the admin account
$hash = password_hash($pwd, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe, date_inscription, statut_compte, role, langue)
                        VALUES (?, ?, ?, ?, ?, NOW(), 'actif', 'admin', 'fr')");
$stmt->execute([$nom, $prenom, $email, $tel, $hash]);

// Log the action
$new_id = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO log_action (id_user, action, details, ip, date_action) VALUES (?,?,?,?,NOW())")
    ->execute([$_SESSION['user_id'], 'create_admin', 'Nouvel admin créé : '.$email.' (id='.$new_id.')', $_SERVER['REMOTE_ADDR'] ?? '']);

$_SESSION['toast'] = ['msg' => 'Administrateur "'.$prenom.' '.$nom.'" créé avec succès ✓', 'type' => 'success'];
header("Location: ../dashboard-admin.php?tab=profil"); exit();