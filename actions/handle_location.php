<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'technicien') {
    echo json_encode(['ok'=>false,'error'=>'Non autorisé']); exit();
}

$user_id = $_SESSION['user_id'];
$data    = json_decode(file_get_contents('php://input'), true);
$lat     = floatval($data['lat'] ?? 0);
$lng     = floatval($data['lng'] ?? 0);
$active  = isset($data['active']) ? (int)(bool)$data['active'] : 1;

if ($lat === 0.0 && $lng === 0.0) {
    echo json_encode(['ok'=>false,'error'=>'Coordonnées invalides']); exit();
}

$pdo->prepare("UPDATE technicien SET latitude=?, longitude=?, localisation_active=? WHERE id_user=?")
    ->execute([$lat, $lng, $active, $user_id]);

log_action($pdo, $user_id, 'update_location', "lat=$lat lng=$lng");
echo json_encode(['ok'=>true]);