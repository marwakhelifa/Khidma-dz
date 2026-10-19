<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom        = trim($_POST['nom'] ?? '');
    $prenom     = trim($_POST['prenom'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['telephone'] ?? '');
    $ville      = trim($_POST['ville'] ?? '');
    $specialite = trim($_POST['specialite'] ?? '');
    $experience = intval($_POST['experience'] ?? 0);
    $tarif      = floatval($_POST['tarif'] ?? 0);
    $bio        = trim($_POST['bio'] ?? '');
    $password   = $_POST['mot_de_passe'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($specialite)) {
        die("Veuillez remplir tous les champs obligatoires.");
    }
    if ($password !== $confirm) {
        die("Les mots de passe ne correspondent pas.");
    }

    // Check email unique
    $stmt = $pdo->prepare("SELECT id_user FROM utilisateur WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        die("Cet e-mail est déjà utilisé.");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert utilisateur
    $stmt = $pdo->prepare("
        INSERT INTO utilisateur (nom, prenom, email, telephone, ville, mot_de_passe, date_inscription, statut_compte, role)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'actif', 'technicien')
    ");
    $stmt->execute([$nom, $prenom, $email, $phone, $ville, $hash]);
    $user_id = $pdo->lastInsertId();

    // Handle CV upload
    $cv_name = null;
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === 0) {
        $ext = pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION);
        $cv_name = 'cv_' . $user_id . '_' . time() . '.' . $ext;
        $upload_dir = '../uploads/cv/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        move_uploaded_file($_FILES['cv']['tmp_name'], $upload_dir . $cv_name);
    }

    // Handle diplome upload
    $diplome_name = null;
    if (isset($_FILES['diplome']) && $_FILES['diplome']['error'] === 0) {
        $ext2 = pathinfo($_FILES['diplome']['name'], PATHINFO_EXTENSION);
        $diplome_name = 'diplome_' . $user_id . '_' . time() . '.' . $ext2;
        $upload_dir = '../uploads/cv/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        move_uploaded_file($_FILES['diplome']['tmp_name'], $upload_dir . $diplome_name);
    }

    // Insert technicien with all fields
    $stmt = $pdo->prepare("
        INSERT INTO technicien (id_user, specialité, experience, tarif, description_profil, cv, diplôme, verifie)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([$user_id, $specialite, $experience, $tarif, $bio, $cv_name, $diplome_name]);

    // Send welcome email
    require_once '../includes/mailer.php';
    send_welcome_tech($email, $nom, $prenom);

    header("Location: ../login.php");
    exit;
}
?>