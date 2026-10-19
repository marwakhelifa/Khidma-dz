<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php'); exit();
}

$token = trim($_POST['token'] ?? '');
$pw    = $_POST['password'] ?? '';
$pw2   = $_POST['password_confirm'] ?? '';

if (!$token || strlen($pw) < 8) {
    $_SESSION['reset_pw_error'] = 'Mot de passe trop court (8 caractères minimum).';
    session_write_close();
    header("Location: ../reset-password.php?token=" . urlencode($token)); exit();
}

if ($pw !== $pw2) {
    $_SESSION['reset_pw_error'] = 'Les mots de passe ne correspondent pas.';
    session_write_close();
    header("Location: ../reset-password.php?token=" . urlencode($token)); exit();
}

// Validate token
$row = $pdo->prepare("
    SELECT rt.id_user, u.role, u.nom, u.prenom, u.langue, u.statut_compte
    FROM reset_token rt
    JOIN utilisateur u ON u.id_user = rt.id_user
    WHERE rt.token = ? AND rt.used = 0 AND rt.expires_at > NOW()
");
$row->execute([$token]);
$r = $row->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    $_SESSION['reset_pw_error'] = 'Ce lien a expiré ou déjà été utilisé.';
    session_write_close();
    header("Location: ../reset-password.php?token="); exit();
}

if ($r['statut_compte'] === 'suspendu') {
    $_SESSION['login_error'] = "Votre compte a été suspendu. Vous ne pouvez pas vous connecter.";
    session_write_close();
    header('Location: ../login.php'); exit();
}

// Update password & mark token used
$hash = password_hash($pw, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE id_user = ?")->execute([$hash, $r['id_user']]);
$pdo->prepare("UPDATE reset_token SET used = 1 WHERE token = ?")->execute([$token]);

// Auto-login — no need to make them type the password again
$_SESSION['user_id']   = $r['id_user'];
$_SESSION['user_role'] = $r['role'];
$_SESSION['user_name'] = $r['prenom'] . ' ' . $r['nom'];
$_SESSION['user_lang'] = $r['langue'] ?? 'fr';

log_action($pdo, $r['id_user'], 'password_reset', 'Mot de passe réinitialisé');

session_write_close();

// Send them straight to their dashboard with a welcome toast
switch ($r['role']) {
    case 'client':     header('Location: ../dashboard-client.php?reset=1'); break;
    case 'technicien': header('Location: ../dashboard-tech.php?reset=1');   break;
    case 'admin':      header('Location: ../dashboard-admin.php?reset=1');  break;
    default:           header('Location: ../login.php');
}
exit();