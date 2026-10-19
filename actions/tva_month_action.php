<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$id_technicien = intval($_POST['id_technicien'] ?? 0);
$ym = trim($_POST['ym'] ?? '');

if (!$id_technicien || !preg_match('/^\d{4}-\d{2}$/', $ym)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$monthDate = DateTime::createFromFormat('Y-m', $ym);
if (!$monthDate) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_month']);
    exit;
}

$monthLabel = $monthDate->format('m/Y');

try {
    $tech = $pdo->prepare("SELECT t.id_technicien, t.id_user, t.specialité, u.nom, u.prenom, u.email, u.telephone, u.langue FROM technicien t JOIN utilisateur u ON u.id_user=t.id_user WHERE t.id_technicien=?");
    $tech->execute([$id_technicien]);
    $techRow = $tech->fetch(PDO::FETCH_ASSOC);

    if (!$techRow) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'tech_not_found']);
        exit;
    }

    $summary = $pdo->prepare("\n        SELECT\n            COUNT(*) AS nb_items,\n            COALESCE(SUM(montant_service),0) AS montant_service_total,\n            COALESCE(SUM(montant_tva),0) AS montant_total,\n            COALESCE(SUM(CASE WHEN statut='due' THEN montant_tva ELSE 0 END),0) AS montant_du,\n            COALESCE(SUM(CASE WHEN statut='payée' THEN montant_tva ELSE 0 END),0) AS montant_paye,\n            COALESCE(SUM(CASE WHEN statut='due' THEN 1 ELSE 0 END),0) AS nb_due,\n            COALESCE(SUM(CASE WHEN statut='payée' THEN 1 ELSE 0 END),0) AS nb_payee\n        FROM tva\n        WHERE id_technicien=? AND DATE_FORMAT(date_tva,'%Y-%m')=?\n    ");
    $summary->execute([$id_technicien, $ym]);
    $row = $summary->fetch(PDO::FETCH_ASSOC) ?: [];

    $dueAmount = floatval($row['montant_du'] ?? 0);
    $nbDue = intval($row['nb_due'] ?? 0);
    $nbItems = intval($row['nb_items'] ?? 0);

    if ($action === 'remind') {
        $title = 'Rappel TVA ' . $monthLabel;
        $message = 'Votre TVA de ' . number_format($dueAmount, 0, ',', ' ') . ' DA pour ' . $monthLabel . ' est en attente. ' . $nbItems . ' élément(s) concernés.';
        notifier($pdo, (int)$techRow['id_user'], $title, $message, 'dashboard-admin.php?tab=commissions');

        if (!empty($techRow['email'])) {
            $subject = 'Rappel TVA mensuelle - Khidma DZ';
            $html = '<h2 style="font-size:22px;font-weight:800;color:#0f0c1d;margin:0 0 8px">Rappel de TVA mensuelle</h2>'
                . '<p style="font-size:15px;color:#3d3655;margin:0 0 20px">Bonjour <strong>' . htmlspecialchars($techRow['prenom'] . ' ' . $techRow['nom']) . '</strong>,</p>'
                . '<p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 16px">Votre TVA de <strong>' . number_format($dueAmount, 0, ',', ' ') . ' DA</strong> pour le mois <strong>' . $monthLabel . '</strong> est toujours en attente.</p>'
                . '<p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 24px">Ce mois contient <strong>' . $nbItems . '</strong> élément(s). Merci de régulariser votre situation.</p>';
            send_email($techRow['email'], $techRow['prenom'] . ' ' . $techRow['nom'], $subject, $html);
        }

        log_action($pdo, $_SESSION['user_id'], 'tva_month_reminder', 'Tech #' . $id_technicien . ' - ' . $ym . ' - ' . $dueAmount . ' DA');
        echo json_encode(['ok' => true, 'type' => 'remind', 'month' => $monthLabel, 'amount' => $dueAmount]);
        exit;
    }

    if ($action === 'settle') {
        if ($nbDue <= 0) {
            echo json_encode(['ok' => true, 'type' => 'settle', 'already' => true, 'month' => $monthLabel, 'amount' => 0]);
            exit;
        }

        $pdo->beginTransaction();
        $u = $pdo->prepare("UPDATE tva SET statut='payée', date_paiement=NOW() WHERE id_technicien=? AND DATE_FORMAT(date_tva,'%Y-%m')=? AND statut='due'");
        $u->execute([$id_technicien, $ym]);

        notifier(
            $pdo,
            (int)$techRow['id_user'],
            'TVA ' . $monthLabel . ' réglée',
            'Votre TVA mensuelle de ' . number_format($dueAmount, 0, ',', ' ') . ' DA pour ' . $monthLabel . ' a été enregistrée comme payée.',
            'dashboard-admin.php?tab=commissions'
        );

        log_action($pdo, $_SESSION['user_id'], 'tva_month_settled', 'Tech #' . $id_technicien . ' - ' . $ym . ' - ' . $dueAmount . ' DA');
        $pdo->commit();

        echo json_encode(['ok' => true, 'type' => 'settle', 'month' => $monthLabel, 'amount' => $dueAmount, 'updated' => $nbDue]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'unknown_action']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server', 'msg' => $e->getMessage()]);
}
