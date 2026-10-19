<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Admin only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php'); exit;
}

$id_technicien = intval($_GET['tech'] ?? 0);
$ym            = preg_replace('/[^0-9\-]/', '', $_GET['ym'] ?? '');

if (!$id_technicien || !$ym) {
    header('Location: dashboard-admin.php?tab=commissions'); exit;
}

// Tech info
$tech_q = $pdo->prepare("
    SELECT ut.nom, ut.prenom, ut.email, ut.telephone, t.specialité
    FROM technicien t
    JOIN utilisateur ut ON ut.id_user = t.id_user
    WHERE t.id_technicien = ?
");
$tech_q->execute([$id_technicien]);
$tech = $tech_q->fetch(PDO::FETCH_ASSOC);
if (!$tech) { header('Location: dashboard-admin.php?tab=commissions'); exit; }

// Month label
$month_label = '';
if (preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
    $dt = DateTime::createFromFormat('Y-m', $ym);
    $month_label = $dt ? ucfirst(strftime('%B %Y', $dt->getTimestamp())) : $ym;
    if (!$month_label || $month_label === $ym) {
        $months_fr = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $month_label = ($months_fr[(int)$m[2]] ?? '') . ' ' . $m[1];
    }
}

// Interventions for this tech + month
$rows_q = $pdo->prepare("
    SELECT c.id_commission, c.montant_service, c.montant_commission, c.taux_commission,
           c.statut, c.date_commission, c.date_paiement,
           s.nom AS snom, s.id_service
    FROM commission c
    JOIN service s ON s.id_service = c.id_service
    WHERE c.id_technicien = ?
      AND DATE_FORMAT(c.date_commission, '%Y-%m') = ?
    ORDER BY c.date_commission DESC
");
$rows_q->execute([$id_technicien, $ym]);
$rows = $rows_q->fetchAll(PDO::FETCH_ASSOC);

// Totals
$total_service    = array_sum(array_column($rows, 'montant_service'));
$total_commission = array_sum(array_column($rows, 'montant_commission'));
$total_due        = array_sum(array_map(fn($r) => $r['statut'] === 'due' ? $r['montant_commission'] : 0, $rows));
$total_payee      = array_sum(array_map(fn($r) => $r['statut'] === 'payée' ? $r['montant_commission'] : 0, $rows));

// Handle mark all as payée
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settle_all') {
    $pdo->prepare("
        UPDATE commission SET statut='payée', date_paiement=NOW()
        WHERE id_technicien = ? AND DATE_FORMAT(date_commission,'%Y-%m') = ? AND statut='due'
    ")->execute([$id_technicien, $ym]);
    log_action($pdo, $_SESSION['user_id'], 'commission_payee', "Toutes commissions $ym Tech #$id_technicien marquées payées");
    header("Location: admin-commission-detail.php?tech=$id_technicien&ym=$ym"); exit;
}

// Handle mark single as payée
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'settle_one') {
    $id_c = intval($_POST['id_commission'] ?? 0);
    if ($id_c) {
        $pdo->prepare("UPDATE commission SET statut='payée', date_paiement=NOW() WHERE id_commission=?")->execute([$id_c]);
        log_action($pdo, $_SESSION['user_id'], 'commission_payee', "Commission #$id_c marquée payée");
    }
    header("Location: admin-commission-detail.php?tech=$id_technicien&ym=$ym"); exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Commissions — <?=htmlspecialchars(($tech['prenom']??'').' '.($tech['nom']??''))?> — <?=htmlspecialchars($month_label)?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f6f9;color:#1a1a2e;min-height:100vh;padding:0}
  .page{max-width:860px;margin:0 auto;padding:28px 16px 60px}

  /* Header */
  .back{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#6b7280;text-decoration:none;margin-bottom:20px;font-weight:500}
  .back:hover{color:#4f46e5}
  .page-title{font-size:22px;font-weight:800;color:#1a1a2e;margin-bottom:4px}
  .page-sub{font-size:13px;color:#6b7280;margin-bottom:24px}

  /* Tech card */
  .tech-card{background:#fff;border-radius:16px;border:1px solid #e5e7eb;padding:18px 22px;display:flex;align-items:center;gap:18px;margin-bottom:20px;flex-wrap:wrap}
  .tech-avatar{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;font-weight:800;flex-shrink:0}
  .tech-name{font-size:16px;font-weight:800;color:#1a1a2e}
  .tech-meta{font-size:12px;color:#6b7280;margin-top:2px}
  .chip{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
  .chip-blue{background:#eff6ff;color:#1d4ed8}

  /* KPI row */
  .kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}
  .kpi{background:#fff;border-radius:14px;border:1px solid #e5e7eb;padding:16px 18px}
  .kpi-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:6px}
  .kpi-val{font-size:20px;font-weight:800;color:#1a1a2e}
  .kpi-val.red{color:#dc2626}
  .kpi-val.green{color:#16a34a}
  .kpi-val.purple{color:#4f46e5}

  /* Actions bar */
  .actions-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px}
  .section-title{font-size:14px;font-weight:700;color:#1a1a2e}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;border:none;transition:opacity .15s}
  .btn:hover{opacity:.85}
  .btn-primary{background:#4f46e5;color:#fff}
  .btn-ghost{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}

  /* Intervention cards */
  .int-list{display:flex;flex-direction:column;gap:10px}
  .int-card{background:#fff;border-radius:14px;border:1px solid #e5e7eb;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;transition:box-shadow .15s}
  .int-card:hover{box-shadow:0 2px 12px rgba(0,0,0,.07)}
  .int-card.is-due{border-left:4px solid #ef4444}
  .int-card.is-payee{border-left:4px solid #22c55e}
  .int-info{display:flex;flex-direction:column;gap:3px}
  .int-name{font-size:14px;font-weight:700;color:#1a1a2e}
  .int-date{font-size:11px;color:#9ca3af}
  .int-amounts{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
  .amount-block{text-align:right}
  .amount-label{font-size:10px;color:#9ca3af;font-weight:600}
  .amount-val{font-size:14px;font-weight:800;color:#1a1a2e}
  .amount-val.comm{color:#4f46e5}
  .status-badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700}
  .status-due{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
  .status-payee{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
  .settle-btn{padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid #d1d5db;background:#f9fafb;color:#374151;transition:all .15s}
  .settle-btn:hover{background:#4f46e5;color:#fff;border-color:#4f46e5}

  .empty{text-align:center;padding:60px 20px;color:#9ca3af;font-size:14px}
  .divider{height:1px;background:#f3f4f6;margin:6px 0}
</style>
</head>
<body>
<div class="page">

  <a href="dashboard-admin.php?tab=commissions" class="back">← Retour aux commissions</a>

  <div class="page-title">Détail des interventions</div>
  <div class="page-sub"><?=htmlspecialchars($month_label)?></div>

  <!-- Tech info -->
  <div class="tech-card">
    <div class="tech-avatar"><?=mb_strtoupper(mb_substr($tech['prenom']??'?',0,1))?></div>
    <div style="flex:1">
      <div class="tech-name"><?=htmlspecialchars(($tech['prenom']??'').' '.($tech['nom']??''))?></div>
      <div class="tech-meta"><?=htmlspecialchars($tech['email']??'')?> · <?=htmlspecialchars($tech['telephone']??'')?></div>
    </div>
    <span class="chip chip-blue"><?=htmlspecialchars($tech['specialité']??'—')?></span>
  </div>

  <!-- KPIs -->
  <div class="kpi-row">
    <div class="kpi">
      <div class="kpi-label">Interventions</div>
      <div class="kpi-val"><?=count($rows)?></div>
    </div>
    <div class="kpi">
      <div class="kpi-label">Volume service</div>
      <div class="kpi-val"><?=number_format($total_service,0,',',' ')?> <span style="font-size:13px;font-weight:500">DA</span></div>
    </div>
    <div class="kpi">
      <div class="kpi-label">Commission totale</div>
      <div class="kpi-val purple"><?=number_format($total_commission,0,',',' ')?> <span style="font-size:13px;font-weight:500">DA</span></div>
    </div>
    <div class="kpi">
      <div class="kpi-label">⚠️ Encore due</div>
      <div class="kpi-val <?=$total_due>0?'red':'green'?>"><?=number_format($total_due,0,',',' ')?> <span style="font-size:13px;font-weight:500">DA</span></div>
    </div>
    <div class="kpi">
      <div class="kpi-label">✓ Déjà reçue</div>
      <div class="kpi-val green"><?=number_format($total_payee,0,',',' ')?> <span style="font-size:13px;font-weight:500">DA</span></div>
    </div>
  </div>

  <!-- Actions bar -->
  <div class="actions-bar">
    <div class="section-title"><?=count($rows)?> intervention(s) ce mois</div>
    <?php if($total_due > 0): ?>
    <form method="POST" onsubmit="return confirm('Marquer toutes les commissions dues comme reçues ?')">
      <input type="hidden" name="action" value="settle_all">
      <button class="btn btn-primary" type="submit">✓ Tout marquer reçu (<?=number_format($total_due,0,',',' ')?> DA)</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Interventions list -->
  <?php if(empty($rows)): ?>
  <div class="empty">Aucune intervention pour ce mois.</div>
  <?php else: ?>
  <div class="int-list">
    <?php foreach($rows as $row):
      $is_due = ($row['statut'] === 'due');
    ?>
    <div class="int-card <?=$is_due?'is-due':'is-payee'?>">
      <div class="int-info">
        <div class="int-name"><?=htmlspecialchars($row['snom'] ?? 'Service #'.$row['id_service'])?></div>
        <div class="int-date">
          <?=!empty($row['date_commission']) ? date('d/m/Y à H:i', strtotime($row['date_commission'])) : '—'?>
          · Commission #<?=intval($row['id_commission'])?>
          <?php if(!$is_due && !empty($row['date_paiement'])): ?>
          · <span style="color:#16a34a">Reçu le <?=date('d/m/Y', strtotime($row['date_paiement']))?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="int-amounts">
        <div class="amount-block">
          <div class="amount-label">Montant service</div>
          <div class="amount-val"><?=number_format($row['montant_service']??0,0,',',' ')?> DA</div>
        </div>
        <div class="amount-block">
          <div class="amount-label">Taux</div>
          <div class="amount-val"><?=number_format($row['taux_commission']??8,0)?> %</div>
        </div>
        <div class="amount-block">
          <div class="amount-label">Commission</div>
          <div class="amount-val comm"><?=number_format($row['montant_commission']??0,0,',',' ')?> DA</div>
        </div>
        <span class="status-badge <?=$is_due?'status-due':'status-payee'?>">
          <?=$is_due?'Due':'✓ Reçue'?>
        </span>
        <?php if($is_due): ?>
        <form method="POST">
          <input type="hidden" name="action" value="settle_one">
          <input type="hidden" name="id_commission" value="<?=intval($row['id_commission'])?>">
          <button class="settle-btn" type="submit">✓ Reçu</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>