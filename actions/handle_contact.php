<?php
session_start();
require_once __DIR__ . '/../includes/mailer.php';

$nom = trim($_POST['nom'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$nom || !$email || !$message || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['toast'] = ['msg' => 'Veuillez remplir correctement le formulaire.', 'type' => 'err'];
    header('Location: ../index.php#contact');
    exit();
}

$subject = 'Contact formulaire — ' . substr($nom,0,100);
$body = '<p><strong>Nom :</strong> ' . htmlspecialchars($nom) . '</p>' .
        '<p><strong>Email :</strong> ' . htmlspecialchars($email) . '</p>' .
        '<p><strong>Message :</strong><br>' . nl2br(htmlspecialchars($message)) . '</p>';

$sent = send_email(MAIL_FROM, MAIL_FROM_NAME, $subject, $body);

if ($sent) {
    $_SESSION['toast'] = ['msg' => 'Merci — votre message a été envoyé.', 'type' => 'ok'];
} else {
    $_SESSION['toast'] = ['msg' => 'Erreur d\'envoi. Veuillez réessayer plus tard.', 'type' => 'err'];
}

header('Location: ../index.php#contact');
exit();
