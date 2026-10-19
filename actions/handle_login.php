<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php'); exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['mot_de_passe'] ?? '';

function fail($msg, $email='') {
    $_SESSION['login_error'] = $msg;
    session_write_close();
    $q = $email ? '?email='.urlencode($email) : '';
    header('Location: ../login.php'.$q); exit;
}

if ($email === '' || $password === '') {
    fail('Veuillez remplir tous les champs.');
}

$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    fail('Aucun compte trouvé avec cet email. Vérifiez votre adresse ou créez un compte.', $email);
}

if (!password_verify($password, $user['mot_de_passe'])) {
    fail('Mot de passe incorrect. Réessayez ou utilisez "Mot de passe oublié".', $email);
}

if (($user['statut_compte'] ?? 'actif') === 'suspendu') {
    $raison = $user['raison_suspension'] ? ' : ' . $user['raison_suspension'] : '.';
    $_SESSION['login_error'] = "Votre compte a été suspendu par l'administration" . $raison;
    $_SESSION['login_suspended'] = true;
    session_write_close();
    header('Location: ../login.php'); exit;
}

$_SESSION['user_id']   = $user['id_user'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
// Preserve any language chosen before login (via ?lang= or cookie) instead of
// blindly overwriting with the DB value. If the user selected a language on
// the login page we persist that choice to cookie and the DB for future logins.
$chosen = $_SESSION['user_lang'] ?? ($_COOKIE['khidma_lang'] ?? null);
$allowed = ['fr', 'ar'];
if (!empty($chosen) && in_array($chosen, $allowed)) {
    $_SESSION['user_lang'] = $chosen;
    setcookie('khidma_lang', $chosen, time() + 60*60*24*365, '/');
    if (($user['langue'] ?? '') !== $chosen) {
        try { $pdo->prepare("UPDATE utilisateur SET langue=? WHERE id_user=?")->execute([$chosen, $user['id_user']]); } catch (Exception $e) {}
    }
} else {
    $_SESSION['user_lang'] = $user['langue'] ?? 'fr';
    setcookie('khidma_lang', $_SESSION['user_lang'], time() + 60*60*24*365, '/');
}

log_action($pdo, $user['id_user'], 'login', 'Connexion depuis ' . $_SERVER['REMOTE_ADDR']);

switch ($user['role']) {
    case 'client':     header('Location: ../dashboard-client.php'); break;
    case 'technicien': header('Location: ../dashboard-tech.php'); break;
    case 'admin':      header('Location: ../dashboard-admin.php'); break;
    default:           header('Location: ../index.php');
}
exit;