<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../forgot-password.php'); exit();
}

$email = trim($_POST['email'] ?? '');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['reset_error'] = 'Email invalide.';
    header('Location: ../forgot-password.php'); exit();
}

// Check user exists
$user = $pdo->prepare("SELECT id_user, nom, prenom FROM utilisateur WHERE email = ? AND statut_compte = 'actif'");
$user->execute([$email]);
$u = $user->fetch(PDO::FETCH_ASSOC);

// Always show success (security: don't reveal if email exists)
// Store token only if user exists
if ($u) {
    // Delete any existing tokens for this user
    $pdo->prepare("DELETE FROM reset_token WHERE id_user = ?")->execute([$u['id_user']]);
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $pdo->prepare("INSERT INTO reset_token (id_user, token, expires_at, used) VALUES (?, ?, ?, 0)")
        ->execute([$u['id_user'], $token, $expires]);
    
    // Send reset email
    require_once '../includes/mailer.php';
    send_reset_email($email, $u['nom'], $u['prenom'], $token);
}

$_SESSION['reset_sent'] = true;
header('Location: ../forgot-password.php'); exit();