<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim($_POST['nom'] ?? '');
    $prenom  = trim($_POST['prenom'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $tel     = trim($_POST['telephone'] ?? '');
    $pwd     = $_POST['mot_de_passe'] ?? '';
    $pwd2    = $_POST['mot_de_passe_confirm'] ?? '';

    if ($pwd !== '' && $pwd !== $pwd2) {
        $_SESSION['toast'] = ['msg' => 'Les mots de passe ne correspondent pas', 'type' => 'error'];
        header("Location: ../dashboard-admin.php?tab=profil"); exit();
    }

    if ($pwd !== '') {
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE utilisateur SET nom=?, prenom=?, email=?, telephone=?, mot_de_passe=? WHERE id_user=?");
        $stmt->execute([$nom, $prenom, $email, $tel, $hash, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE utilisateur SET nom=?, prenom=?, email=?, telephone=? WHERE id_user=?");
        $stmt->execute([$nom, $prenom, $email, $tel, $_SESSION['user_id']]);
    }

    $_SESSION['toast'] = ['msg' => 'Profil mis à jour ✓', 'type' => 'success'];
    header("Location: ../dashboard-admin.php?tab=profil"); exit();
}
header("Location: ../dashboard-admin.php?tab=profil"); exit();
