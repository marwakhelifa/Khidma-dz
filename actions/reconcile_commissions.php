<?php
// Recompute commissions using TTC as canonical base.
// Usage: run from CLI or open in browser (careful).

require_once __DIR__ . '/../includes/db.php';

$updated = 0;
$skipped = 0;

$stmt = $pdo->query("SELECT id_commission, id_paiment, id_service, id_technicien, montant_service, taux_commission FROM commission");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $id = $r['id_commission'];
    $id_paiment = $r['id_paiment'];
    $new_comm = null;

    if ($id_paiment) {
        $p = $pdo->prepare("SELECT montant FROM paiment WHERE id_paiment = ?");
        $p->execute([$id_paiment]);
        $prow = $p->fetch(PDO::FETCH_ASSOC);
        if ($prow && isset($prow['montant'])) {
            $montant_ttc = floatval($prow['montant']);
            $taux = floatval($r['taux_commission'] ?? 10.00);
            $new_comm = round($montant_ttc * $taux / 100, 2);
        }
    }

    if ($new_comm === null) {
        // Can't find related payment — skip and log
        $skipped++;
        continue;
    }

    $u = $pdo->prepare("UPDATE commission SET montant_commission = ?, taux_commission = ? WHERE id_commission = ?");
    $u->execute([$new_comm, $taux, $id]);
    $updated++;
}

echo "Reconciliation completed. Updated: $updated, Skipped: $skipped\n";

?>
