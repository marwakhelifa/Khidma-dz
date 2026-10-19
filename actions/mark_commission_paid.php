<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$id_comm = intval($_POST['id_commission'] ?? 0);
if (!$id_comm) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_id']);
    exit;
}

try {
    $q = $pdo->prepare("SELECT statut FROM commission WHERE id_commission = ?");
    $q->execute([$id_comm]);
    $st = $q->fetchColumn();
    if (!$st) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        exit;
    }
    if ($st === 'payée') {
        echo json_encode(['ok' => true, 'already' => true]);
        exit;
    }

    $u = $pdo->prepare("UPDATE commission SET statut='payée', date_paiement=NOW() WHERE id_commission=?");
    $u->execute([$id_comm]);
    log_action($pdo, $_SESSION['user_id'], 'commission_payee', "Commission #$id_comm marked paid via AJAX");

    $d = $pdo->prepare("SELECT date_paiement FROM commission WHERE id_commission=?");
    $d->execute([$id_comm]);
    $date = $d->fetchColumn();

    echo json_encode(['ok' => true, 'date' => $date]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server', 'msg' => $e->getMessage()]);
}

?>
