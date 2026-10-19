<?php
// khidma-dz011/actions/handle_evaluation.php
// Client soumet une évaluation pour un technicien (par demande)

session_start();
require_once '../includes/db.php';

// Vérifier que c'est un client connecté
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard-client.php');
    exit();
}

$client_id  = (int) $_SESSION['user_id'];
$demande_id = (int) ($_POST['demande_id'] ?? 0);
$note       = (int) ($_POST['note'] ?? 0);
$commentaire = trim($_POST['commentaire'] ?? '');

// Validation basique
if ($demande_id <= 0 || $note < 1 || $note > 5) {
    $_SESSION['error'] = "Évaluation invalide.";
    header('Location: ../dashboard-client.php');
    exit();
}

// Vérifier que la demande appartient bien à ce client et est terminée (statut = 'termine')
$stmt = $pdo->prepare("
    SELECT d.id, d.tech_id, d.service, d.statut
    FROM demandes d
    WHERE d.id = ? AND d.client_id = ? AND d.statut = 'termine'
    LIMIT 1
");
$stmt->execute([$demande_id, $client_id]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    $_SESSION['error'] = "Demande introuvable ou non terminée.";
    header('Location: ../dashboard-client.php');
    exit();
}

// Vérifier que le client n'a pas déjà évalué cette demande
$check = $pdo->prepare("SELECT id FROM evaluations WHERE demande_id = ?");
$check->execute([$demande_id]);
if ($check->fetch()) {
    $_SESSION['error'] = "Vous avez déjà évalué ce service.";
    header('Location: ../dashboard-client.php');
    exit();
}

// Insérer l'évaluation
$insert = $pdo->prepare("
    INSERT INTO evaluations (demande_id, client_id, tech_id, service_nom, note, commentaire)
    VALUES (?, ?, ?, ?, ?, ?)
");
$insert->execute([
    $demande_id,
    $client_id,
    $demande['tech_id'],
    $demande['service'],
    $note,
    $commentaire ?: null
]);

$_SESSION['success'] = "Merci pour votre évaluation !";
header('Location: ../dashboard-client.php');
exit();
