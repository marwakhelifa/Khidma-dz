<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['nom'] ?? '');
    $last_name  = trim($_POST['prenom'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['telephone'] ?? '');
    $address    = trim($_POST['ville'] ?? '');
    $password   = $_POST['mot_de_passe'] ?? '';
    $password_confirm = $_POST['mot_de_passe_confirm'] ?? '';
    

    // Basic validation
    if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id_user FROM utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Cette adresse e-mail est déjà utilisée.";
        }
    }

    if (isset($error)) {
        // In a real app, you might store in session and redirect.
        // For now, just show a simple error.
        echo "<p style='color:red;'>$error</p>";
        echo "<p><a href='../register-client.php'>Retour au formulaire</a></p>";
        exit;
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $stmt = $pdo->prepare(
        "INSERT INTO utilisateur (role, nom, prenom, email, mot_de_passe, telephone, ville, date_inscription, statut_compte, langue)
         VALUES ('client', ?, ?, ?, ?, ?, ?, CURDATE(), 'actif', 'fr')"
    );
    $stmt->execute([$first_name, $last_name, $email, $password_hash, $phone, $address]);

    // Get the new user's ID and insert into client table
    $new_user_id = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO client (id_user) VALUES (?)")->execute([$new_user_id]);

    // Send welcome email
    require_once '../includes/mailer.php';
    send_welcome_client($email, $first_name, $last_name);

    // Redirect to login
    session_write_close();
    header('Location: ../login.php');
    exit;
} else {
    header('Location: ../register-client.php');
    exit;
}