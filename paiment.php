<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/lang.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'client';
$lang      = get_lang();
$is_ar     = $lang === 'ar';
$dir       = $is_ar ? 'rtl' : 'ltr';

$id_service = intval($_GET['id_service'] ?? 0);
if (!$id_service) { header('Location: dashboard-client.php'); exit; }

// ── Handle payment confirmation ──────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['confirm_payment'])) {
    $mode = in_array($_POST['mode_paiment']??'', ['espèces','edahabia','cib','virement'])
            ? $_POST['mode_paiment'] : 'espèces';
    $sid  = intval($_POST['id_service']??0);

  $guard = $pdo->prepare("SELECT id_client,id_technicien,statut_service FROM service WHERE id_service=?");
  $guard->execute([$sid]);
  $svc_guard = $guard->fetch(PDO::FETCH_ASSOC);
  if (!$svc_guard) { header('Location: dashboard-client.php'); exit; }

  $cid = $pdo->prepare("SELECT id_client FROM client WHERE id_user=?");
  $cid->execute([$user_id]);
  $my_client_id = $cid->fetchColumn();
  $tid = $pdo->prepare("SELECT id_technicien FROM technicien WHERE id_user=?");
  $tid->execute([$user_id]);
  $my_tech_id = $tid->fetchColumn();

  $is_authorized = ($user_role==='admin')
    || ($user_role==='client' && $my_client_id && intval($svc_guard['id_client'])===intval($my_client_id));

  if (!$is_authorized || $svc_guard['statut_service']==='annulé') {
    $fallback = $user_role==='technicien' ? 'dashboard-tech.php?tab=demandes' : 'dashboard-client.php?tab=reservations';
    header("Location: $fallback");
    exit;
  }

    // If an explicit paiment id was provided, pay only that paiment (incremental ticket)
    $pay_pid = intval($_POST['id_paiment'] ?? 0);
    if ($pay_pid > 0) {
      $pchk = $pdo->prepare("SELECT * FROM paiment WHERE id_paiment=? AND id_service=?");
      $pchk->execute([$pay_pid,$sid]); $paid_row = $pchk->fetch(PDO::FETCH_ASSOC);
      if ($paid_row) {
        $amount = floatval($paid_row['montant']);
        $pdo->prepare("UPDATE paiment SET statut_paiment='payé',mode_paiment=?,date_paiment=NOW() WHERE id_paiment=?")->execute([$mode,$pay_pid]);
        // Update or create a facture corresponding to this paiment
        $fchk = $pdo->prepare("SELECT id_facture FROM facture WHERE id_service=? AND montant_total=? AND statut_facture='émise'");
        $fchk->execute([$sid,$amount]);
        if ($fchk->fetchColumn()) {
          $pdo->prepare("UPDATE facture SET statut_facture='payée',date_facture=CURDATE() WHERE id_service=? AND montant_total=?")->execute([$sid,$amount]);
        } else if (facture_uses_tva($amount)) {
          $pdo->prepare("INSERT INTO facture (id_service,date_facture,montant_total,statut_facture) VALUES (?,CURDATE(),?,'payée')")->execute([$sid,$amount]);
        }
        // No change to service.prix_final here (it's already updated when modifier created paiment)
        if ($user_role==='client') {
          $tq=$pdo->prepare("SELECT t.id_user, u.langue FROM service s JOIN technicien t ON t.id_technicien=s.id_technicien JOIN utilisateur u ON u.id_user=t.id_user WHERE s.id_service=?");
          $tq->execute([$sid]); $tr=$tq->fetch();
          if ($tr) notifier($pdo,$tr['id_user'],($tr['langue']??'fr')==='ar'?'💰 تم استلام الدفع':'💰 Paiement reçu',($tr['langue']??'fr')==='ar'?'تأكيد العميل دفع '.number_format($amount,0).' دج.':'Le client a confirmé le paiement de '.number_format($amount,0).' DA.','dashboard-tech.php?tab=demandes');
        } else {
          $cq=$pdo->prepare("SELECT c.id_user FROM service s JOIN client c ON c.id_client=s.id_client WHERE s.id_service=?");
          $cq->execute([$sid]); $cr=$cq->fetch();
          if ($cr) notifier($pdo,$cr['id_user'],'✅ Paiement confirmé','Votre paiement de '.number_format($amount,0).' DA a été confirmé.','dashboard-client.php?tab=reservations');
        }
        log_action($pdo,$user_id,'confirm_payment_partial',"Service #$sid paiment #$pay_pid — $mode — $amount DA");
        header("Location: paiment.php?id_service=$sid&paid=1"); exit;
      }
    }

    // Otherwise fallback to paying all pending amounts (legacy behavior)
    $pending_q = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM paiment WHERE id_service=? AND statut_paiment='en_attente'");
    $pending_q->execute([$sid]); $pending_sum = floatval($pending_q->fetchColumn());
    // If no pending paiment rows, compute from prestations as before
    if ($pending_sum <= 0) {
      $calc = $pdo->prepare("SELECT COALESCE(SUM(p.prix_unitaire*sp.quantite),0) FROM service_prestation sp JOIN prestation p ON p.id_prestation=sp.id_prestation WHERE sp.id_service=?");
      $calc->execute([$sid]); $total = floatval($calc->fetchColumn());
      $svc_row = $pdo->prepare("SELECT prix_final,prix_estime FROM service WHERE id_service=?");
      $svc_row->execute([$sid]); $sr = $svc_row->fetch();
      if (!$total) $total = $sr['prix_final'] ?? $sr['prix_estime'] ?? 0;
      $use_facture = facture_uses_tva((float)$total);
      $tva_rate = 0.19;
      $tva_amount = $use_facture ? round($total * $tva_rate, 2) : 0;
      $final_amount = round($total + $tva_amount, 2);
      // Upsert payment record as paid
      $pm = $pdo->prepare("SELECT id_paiment FROM paiment WHERE id_service=?");
      $pm->execute([$sid]);
      if ($pm->fetchColumn()) {
        $pdo->prepare("UPDATE paiment SET statut_paiment='payé',mode_paiment=?,montant=?,date_paiment=NOW() WHERE id_service=?")->execute([$mode,$final_amount,$sid]);
      } else {
        $pdo->prepare("INSERT INTO paiment (id_service,mode_paiment,montant,statut_paiment,date_paiment) VALUES (?,?,?,'payé',NOW())")->execute([$sid,$mode,$final_amount]);
      }
      if ($use_facture) {
        $pdo->prepare("UPDATE facture SET statut_facture='payée',montant_total=? WHERE id_service=?")->execute([$final_amount,$sid]);
      } else {
        $pdo->prepare("DELETE FROM facture WHERE id_service=?")->execute([$sid]);
      }
      $pdo->prepare("UPDATE service SET prix_final=? WHERE id_service=?")->execute([$final_amount,$sid]);
      // Notify the other party
      if ($user_role==='client') {
        $tq=$pdo->prepare("SELECT t.id_user FROM service s JOIN technicien t ON t.id_technicien=s.id_technicien WHERE s.id_service=?");
        $tq->execute([$sid]); $tr=$tq->fetch();
        if ($tr) notifier($pdo,$tr['id_user'],($tr['langue']??'fr')==='ar'?'💰 تم استلام الدفع':'💰 Paiement reçu',($tr['langue']??'fr')==='ar'?'تأكيد العميل دفع '.number_format($final_amount,0).' دج.':'Le client a confirmé le paiement de '.number_format($final_amount,0).' DA.','dashboard-tech.php?tab=demandes');
      } else {
        $cq=$pdo->prepare("SELECT c.id_user FROM service s JOIN client c ON c.id_client=s.id_client WHERE s.id_service=?");
        $cq->execute([$sid]); $cr=$cq->fetch();
        if ($cr) notifier($pdo,$cr['id_user'],'✅ Paiement confirmé','Votre paiement de '.number_format($final_amount,0).' DA a été confirmé.','dashboard-client.php?tab=reservations');
      }
      // Generate facture on payment confirmation only for amounts >= 5000 DA
      if ($use_facture) {
        $fchk = $pdo->prepare("SELECT id_facture FROM facture WHERE id_service=?");
        $fchk->execute([$sid]);
        if (!$fchk->fetchColumn()) {
          $pdo->prepare("INSERT INTO facture (id_service,date_facture,montant_total,statut_facture) VALUES (?,CURDATE(),?,'payée')")->execute([$sid,$final_amount]);
        } else {
          $pdo->prepare("UPDATE facture SET montant_total=?,statut_facture='payée',date_facture=CURDATE() WHERE id_service=?")->execute([$final_amount,$sid]);
        }
      } else {
        $pdo->prepare("DELETE FROM facture WHERE id_service=?")->execute([$sid]);
      }
      log_action($pdo,$user_id,'confirm_payment','Service #$sid — $mode — $total DA');
      header("Location: paiment.php?id_service=$sid&paid=1"); exit;
    }
    log_action($pdo,$user_id,'confirm_payment',"Service #$sid — $mode — $total DA");
    header("Location: paiment.php?id_service=$sid&paid=1"); exit;
}

// ── Fetch service ────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.*,
           uc.nom AS client_nom, uc.prenom AS client_prenom, uc.telephone AS client_tel,
           ut.nom AS tech_nom,   ut.prenom AS tech_prenom,   ut.telephone AS tech_tel,
           t.specialité,
           p.id_paiment, p.mode_paiment, p.statut_paiment,
           f.id_facture
    FROM service s
    JOIN client      c  ON c.id_client     = s.id_client
    JOIN utilisateur uc ON uc.id_user      = c.id_user
    JOIN technicien  t  ON t.id_technicien = s.id_technicien
    JOIN utilisateur ut ON ut.id_user      = t.id_user
    LEFT JOIN paiment p ON p.id_service    = s.id_service
    LEFT JOIN facture f ON f.id_facture = (
      SELECT MAX(f2.id_facture)
      FROM facture f2
      WHERE f2.id_service = s.id_service
    )
    WHERE s.id_service = ?
");
$stmt->execute([$id_service]);
$svc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$svc) { header('Location: dashboard-client.php'); exit; }

if (($svc['statut_service'] ?? '') === 'annulé') {
  $fallback = $user_role==='technicien' ? 'dashboard-tech.php?tab=demandes' : 'dashboard-client.php?tab=reservations';
  header("Location: $fallback");
  exit;
}

// Access control
$cid = $pdo->prepare("SELECT id_client FROM client WHERE id_user=?"); $cid->execute([$user_id]);
$tid = $pdo->prepare("SELECT id_technicien FROM technicien WHERE id_user=?"); $tid->execute([$user_id]);
$is_client = ($user_role==='client'     && $svc['id_client']     == $cid->fetchColumn());
$is_tech   = ($user_role==='technicien' && $svc['id_technicien'] == $tid->fetchColumn());
if (!$is_client && !$is_tech && $user_role!=='admin') { header('Location: dashboard-client.php'); exit; }

// Hide payment page for technicians when a facture already exists for this service
if ($is_tech && !empty($svc['id_facture'])) {
  header('Location: dashboard-tech.php?tab=demandes'); exit;
}

// Prestations + total
$sp = $pdo->prepare("SELECT pr.nom,pr.prix_unitaire,pr.unite,sp.quantite FROM service_prestation sp JOIN prestation pr ON pr.id_prestation=sp.id_prestation WHERE sp.id_service=? ORDER BY pr.nom");
$sp->execute([$id_service]); $prests=$sp->fetchAll();
$total = array_sum(array_map(fn($p)=>($p['prix_unitaire']??0)*($p['quantite']??1),$prests));
if (!$total) $total = $svc['prix_final'] ?? $svc['prix_estime'] ?? 0;
$tva_rate = 0.19;
$apply_tva = facture_uses_tva((float)$total);
$tva_amount = $apply_tva ? round($total * $tva_rate, 2) : 0;
$total_ttc = round($total + $tva_amount, 2);

$already_paid = $svc['statut_paiment']==='payé';
$just_paid    = isset($_GET['paid']);
$back = $is_tech ? 'dashboard-tech.php?tab=demandes' : 'dashboard-client.php?tab=reservations';

// Pending incremental paiment rows: when modifier created an extra paiment, it appears here
$pending_q = $pdo->prepare("SELECT * FROM paiment WHERE id_service=? AND statut_paiment='en_attente' ORDER BY id_paiment ASC");
$pending_q->execute([$id_service]);
$pending_rows = $pending_q->fetchAll(PDO::FETCH_ASSOC);
$pending_sum = 0;
foreach ($pending_rows as $pr) $pending_sum += floatval($pr['montant']);
$display_amount = $pending_sum > 0 ? $pending_sum : $total_ttc;
?>
<!DOCTYPE html>
<html lang="<?=$lang?>" dir="<?=$dir?>">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?=$is_ar?'الدفع':'Paiement'?> — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#7c3aed">
<style>
*,::before,::after{margin:0;padding:0;box-sizing:border-box;}
:root{--v:#7c3aed;--vd:#5b21b6;--vl:#ede9fe;--vm:#c4b5fd;--c:#06b6d4;--green:#16a34a;--bg:#faf9fe;--s:#fff;--t:#0f0c1d;--t2:#3d3655;--mu:#8b83a8;--br:#e8e4f8;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);min-height:100vh;padding:24px 16px 48px;}
.wrap{max-width:520px;margin:0 auto;}
.back{display:inline-flex;align-items:center;gap:6px;color:var(--mu);font-size:13px;font-weight:600;text-decoration:none;margin-bottom:20px;transition:color .2s;}
.back:hover{color:var(--v);}
/* Summary card */
.summary{background:linear-gradient(135deg,var(--v),var(--vd));border-radius:20px;padding:28px;color:#fff;margin-bottom:16px;position:relative;overflow:hidden;}
.summary::after{content:'';position:absolute;right:-30px;top:-30px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none;}
.summary-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;opacity:.7;margin-bottom:8px;}
.summary-amount{font-family:'Syne',sans-serif;font-size:48px;font-weight:800;line-height:1;margin-bottom:6px;}
.summary-amount span{font-size:22px;opacity:.75;}
.summary-meta{font-size:13px;opacity:.75;}
.summary-parties{display:flex;gap:20px;margin-top:18px;padding-top:18px;border-top:1px solid rgba(255,255,255,.2);}
.party{flex:1;}
.party-role{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.6;margin-bottom:3px;}
.party-name{font-size:13px;font-weight:700;}
.party-tel{font-size:12px;opacity:.65;margin-top:1px;}
/* Card */
.card{background:var(--s);border:1.5px solid var(--br);border-radius:18px;overflow:hidden;box-shadow:0 4px 20px rgba(124,58,237,.06);margin-bottom:14px;}
.card-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:var(--mu);padding:16px 20px 0;}
/* Prestations */
.prest{padding:12px 20px;border-bottom:1px solid var(--br);display:flex;justify-content:space-between;align-items:center;gap:12px;}
.prest:last-child{border-bottom:none;}
.prest-name{font-size:13.5px;font-weight:600;color:var(--t);}
.prest-sub{font-size:11.5px;color:var(--mu);margin-top:1px;}
.prest-amt{font-size:14px;font-weight:700;color:var(--v);flex-shrink:0;}
.total-bar{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;background:var(--vl);border-top:2px solid var(--vm);}
.total-lbl{font-size:13px;font-weight:700;color:var(--vd);}
.total-val{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--v);}
/* Payment methods */
.methods{display:flex;flex-direction:column;gap:10px;padding:16px 20px;}
.method{display:flex;align-items:center;gap:14px;padding:14px 16px;border:2px solid var(--br);border-radius:13px;cursor:pointer;transition:all .18s;user-select:none;}
.method:hover{border-color:var(--vm);}
.method.selected{border-color:var(--v);background:var(--vl);}
.method input[type=radio]{display:none;}
.method-icon{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.method-icon.cash{background:#f0fdf4;}
.method-icon.eda{background:#fff7ed;}
.method-icon.cib{background:#eff6ff;}
.method-info{flex:1;}
.method-name{font-size:14px;font-weight:700;color:var(--t);}
.method-desc{font-size:12px;color:var(--mu);margin-top:2px;}
.method-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;flex-shrink:0;}
.badge-available{background:rgba(22,163,74,.1);color:#16a34a;}
.badge-soon{background:rgba(107,114,128,.1);color:#6b7280;}
.method-check{width:20px;height:20px;border-radius:50%;border:2px solid var(--br);background:#fff;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .18s;}
.method.selected .method-check{border-color:var(--v);background:var(--v);}
.method.selected .method-check::after{content:'';width:8px;height:8px;border-radius:50%;background:#fff;}
/* Confirm button */
.confirm-btn{width:100%;padding:16px;background:linear-gradient(135deg,var(--v),var(--vd));color:#fff;border:none;border-radius:14px;font-size:16px;font-weight:800;cursor:pointer;font-family:'Syne',sans-serif;letter-spacing:.3px;box-shadow:0 6px 20px rgba(124,58,237,.35);transition:all .2s;margin-top:4px;}
.confirm-btn:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(124,58,237,.4);}
.confirm-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}
/* Paid state */
.paid-banner{background:#f0fdf4;border:2px solid #bbf7d0;border-radius:16px;padding:24px;text-align:center;margin-bottom:14px;}
.paid-icon{font-size:48px;margin-bottom:12px;}
.paid-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#15803d;margin-bottom:6px;}
.paid-sub{font-size:13px;color:#166534;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:11px 22px;border-radius:11px;font-size:13.5px;font-weight:700;cursor:pointer;text-decoration:none;border:none;transition:all .2s;font-family:'Plus Jakarta Sans',sans-serif;}
.btn-primary{background:linear-gradient(135deg,var(--v),var(--vd));color:#fff;box-shadow:0 4px 14px rgba(124,58,237,.3);}
.btn-ghost{background:var(--bg);color:var(--t2);border:1.5px solid var(--br);}
.btn-ghost:hover{border-color:var(--v);color:var(--v);}
.btns{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:16px;}
@media(max-width:480px){
  .summary-amount{font-size:36px;}
  .summary-parties{flex-direction:column;gap:12px;}
}

[dir=rtl] body{font-family:'Cairo',sans-serif;}
[dir=rtl] .topbar{flex-direction:row-reverse;}
[dir=rtl] .summary-amount{text-align:center;}
[dir=rtl] .prest-row{flex-direction:row-reverse;}
[dir=rtl] .prest-info{text-align:right;}
[dir=rtl] .total-row{flex-direction:row-reverse;}
</style>
</head>
<body>
<div class="wrap">
  <a href="<?=$back?>" class="back">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    <?=$is_ar?'رجوع':'Retour'?>
  </a>

  <!-- Summary header -->
  <div class="summary">
    <div class="summary-label"><?=$is_ar?'المبلغ الإجمالي مع الضريبة':'Montant TTC'?></div>
    <div class="summary-amount" dir="ltr" style="unicode-bidi:embed"><?=number_format($total_ttc,0)?> <span><?=$is_ar?'دج':'DA'?></span></div>
    <?php if($user_role==='technicien'):
      $_cp = round($total_ttc * 0.10);
      $_np = $total_ttc - $_cp;
    ?>
    <div style="margin-top:8px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;font-size:12px">
      <span style="background:rgba(245,158,11,.18);color:#b45309;padding:3px 10px;border-radius:20px;font-weight:600">
        Commission due à Khidma DZ : <?=number_format($_cp,0)?> <?=$is_ar?'دج':'DA'?>
      </span>
      <span style="background:rgba(22,163,74,.18);color:#15803d;padding:3px 10px;border-radius:20px;font-weight:700">
        Votre part : <?=number_format($_np,0)?> <?=$is_ar?'دج':'DA'?>
      </span>
    </div>
    <?php endif;?>
    <div class="summary-meta">
      #<?=$id_service?> · <?=$svc['date_service']?date('d/m/Y',strtotime($svc['date_service'])):'—'?>
      · <?=htmlspecialchars($svc['specialité']??'Service')?>
    </div>
    <div class="summary-parties">
      <div class="party">
        <div class="party-role">👤 <?=$is_ar?'العميل':'Client'?></div>
        <div class="party-name"><?=htmlspecialchars($svc['client_prenom'].' '.$svc['client_nom'])?></div>
        <div class="party-tel"><?=htmlspecialchars($svc['client_tel']??'')?></div>
      </div>
      <div class="party">
        <div class="party-role">🔧 <?=$is_ar?'التقني':'Technicien'?></div>
        <div class="party-name"><?=htmlspecialchars($svc['tech_prenom'].' '.$svc['tech_nom'])?></div>
        <div class="party-tel"><?=htmlspecialchars($svc['tech_tel']??'')?></div>
      </div>
    </div>
  </div>

  <?php if($just_paid || $already_paid): ?>
  <!-- Already paid -->
  <?php if($is_tech): ?>
  <div class="paid-banner">
    <div class="paid-icon">✅</div>
    <div class="paid-title"><?=$is_ar?'تم الدفع بنجاح!':'Paiement confirmé !'?></div>
    <div class="paid-sub" dir="ltr" style="unicode-bidi:embed"><?=number_format($total_ttc,0)?> <?=$is_ar?'دج':'DA'?></div>
    <div class="btns">
      <?php if($svc['id_facture']):?>
      <a href="facture.php?id=<?=$svc['id_facture']?>" class="btn btn-primary" target="_blank">📄 <?=$is_ar?'الفاتورة':'Voir la facture'?></a>
      <?php endif;?>
      <a href="<?=$back?>" class="btn btn-ghost">← <?=$is_ar?'رجوع':'Retour'?></a>
    </div>
  </div>
  <?php else: ?>
  <!-- Client view: Simple green banner -->
  <div class="paid-banner">
    <div class="paid-icon">✅</div>
    <div class="paid-title"><?=$is_ar?'تم الدفع بنجاح!':'Paiement confirmé !'?></div>
    <div class="paid-sub">
      <?php
      $modes=['espèces'=>'💵 Espèces','edahabia'=>'📱 Edahabia','cib'=>'💳 CIB','virement'=>'🏦 Virement'];
      echo htmlspecialchars($modes[$svc['mode_paiment']??'espèces']??'Espèces');
      ?> · <?=number_format($total_ttc,0)?> DA
    </div>
    <div class="btns">
      <?php if($svc['id_facture']):?>
      <a href="facture.php?id=<?=$svc['id_facture']?>" class="btn btn-primary" target="_blank">📄 <?=$is_ar?'الفاتورة':'Voir la facture'?></a>
      <?php endif;?>
      <a href="<?=$back?>" class="btn btn-ghost">← <?=$is_ar?'رجوع':'Retour'?></a>
    </div>
  </div>
  <?php endif; ?>

  <?php else: ?>

  <!-- Prestations breakdown -->
  <?php if(!empty($prests)):?>
  <div class="card">
    <div class="card-title"><?=$is_ar?'تفاصيل الخدمات':'Détail des prestations'?></div>
    <?php foreach($prests as $p):
      $sub=($p['prix_unitaire']??0)*($p['quantite']??1);
    ?>
    <div class="prest">
      <div>
        <div class="prest-name"><?=htmlspecialchars($p['nom'])?></div>
        <div class="prest-sub">
          <span dir="ltr" style="unicode-bidi:embed"><?=number_format($p['prix_unitaire'],0)?></span> <?=$is_ar?'دج':'DA'?>/<?=htmlspecialchars($p['unite'])?>
          <?php if(($p['quantite']??1)>1):?> × <?=$p['quantite']?><?php endif;?>
        </div>
      </div>
      <div class="prest-amt" dir="ltr" style="unicode-bidi:embed"><?=number_format($sub,0)?> <?=$is_ar?'دج':'DA'?></div>
    </div>
    <?php endforeach;?>
    <div class="total-bar">
      <span class="total-lbl"><?=$is_ar?'المبلغ دون ضرائب':'Total HT'?></span>
      <span class="total-val" dir="ltr" style="unicode-bidi:embed"><?=number_format($total,0)?> <?=$is_ar?'دج':'DA'?></span>
    </div>
    <div class="total-bar">
      <span class="total-lbl">TVA (19%)</span>
      <span class="total-val" dir="ltr" style="unicode-bidi:embed"><?=number_format($tva_amount,0)?> <?=$is_ar?'دج':'DA'?></span>
    </div>
    <div class="total-bar main">
      <span class="total-lbl"><?=$is_ar?'المبلغ الإجمالي مع الضريبة':'Total TTC'?></span>
      <span class="total-val" dir="ltr" style="unicode-bidi:embed"><?=number_format($total_ttc,0)?> <?=$is_ar?'دج':'DA'?></span>
    </div>
    <?php if($user_role==='technicien'):
      $comm_preview = round($total_ttc * 0.10, 2);
      $net_preview  = $total_ttc - $comm_preview;
    ?>
    <div class="total-bar" style="background:var(--amber-50,#fffbeb);border-top:2px dashed #f59e0b;margin-top:4px">
      <span class="total-lbl" style="color:#b45309;font-weight:600">⚠️ Commission Khidma DZ (10%)</span>
      <span class="total-val" style="color:#b45309" dir="ltr">−<?=number_format($comm_preview,0)?> <?=$is_ar?'دج':'DA'?></span>
    </div>
    <div class="total-bar" style="background:var(--green-50,#f0fdf4)">
      <span class="total-lbl" style="color:var(--green,#16a34a);font-weight:700"><?=$is_ar?'صافي الراتب':'Votre net à percevoir'?></span>
      <span class="total-val" style="color:var(--green,#16a34a);font-weight:700" dir="ltr"><?=number_format($net_preview,0)?> <?=$is_ar?'دج':'DA'?></span>
    </div>
    <?php endif;?>
  </div>
  <?php endif;?>

  <?php if($user_role==='technicien'): ?>
  <div class="paid-banner" style="margin-bottom:14px;background:#eff6ff;border-color:#bfdbfe;">
    <div class="paid-icon">⏳</div>
    <div class="paid-title" style="color:#1d4ed8;">
      <?=$is_ar?'في انتظار دفع العميل':'En attente de paiement du client'?>
    </div>
    <div class="paid-sub" style="color:#1e40af;">
      <?=$is_ar?'سيظهر هنا نفس التذكرة الخاصة بالعميل.':'La même ticket du client apparaît ici.'?>
    </div>
  </div>
  <?php else: ?>

  <?php if (facture_uses_tva((float)$display_amount)): ?>
    <div class="card">
      <div class="card-title"><?=$is_ar?'الدفع عبر الفاتورة':'Paiement'?></div>
      <form method="POST" id="pay-form" style="padding:0 20px 8px;">
        <input type="hidden" name="confirm_payment" value="1">
        <input type="hidden" name="id_service" value="<?=$id_service?>">
        <?php if(!empty($svc['id_paiment'])): ?>
          <input type="hidden" name="id_paiment" value="<?=intval($svc['id_paiment'])?>">
        <?php endif; ?>
        <div class="methods" style="padding:12px 0 16px;">
          <label class="method selected" onclick="selectMethod(this,'espèces')">
            <input type="radio" name="mode_paiment" value="espèces" checked>
            <div class="method-icon cash">💵</div>
            <div class="method-info">
              <div class="method-name"><?=$is_ar?'نقداً':'Espèces'?></div>
              <div class="method-desc"><?=$is_ar?'دفع نقدي مباشر':'Paiement en main propre'?></div>
            </div>
            <span class="method-badge badge-available"><?=$is_ar?'متاح':'Disponible'?></span>
            <div class="method-check"></div>
          </label>

          <label class="method" onclick="selectMethod(this,'edahabia')">
            <input type="radio" name="mode_paiment" value="edahabia">
            <div class="method-icon eda">📱</div>
            <div class="method-info">
              <div class="method-name">Edahabia</div>
              <div class="method-desc"><?=$is_ar?'بطاقة بريد الجزائر':'Carte Algérie Poste'?></div>
            </div>
            <span class="method-badge badge-soon"><?=$is_ar?'قريباً':'Bientôt'?></span>
            <div class="method-check"></div>
          </label>

          <label class="method" onclick="selectMethod(this,'cib')">
            <input type="radio" name="mode_paiment" value="cib">
            <div class="method-icon cib">💳</div>
            <div class="method-info">
              <div class="method-name">CIB</div>
              <div class="method-desc"><?=$is_ar?'البطاقة الإلكترونية البنكية':'Carte interbancaire'?></div>
            </div>
            <span class="method-badge badge-soon"><?=$is_ar?'قريباً':'Bientôt'?></span>
            <div class="method-check"></div>
          </label>
        </div>
        <div style="padding:0 0 12px;">
          <button type="submit" class="confirm-btn" id="confirm-btn" style="margin-top:0;">
            ✓ <?=$is_ar?'تأكيد الدفع':'Confirmer paiement'?>
          </button>
        </div>
      </form>
      <div class="btns" style="padding:16px 20px;">
        <?php if($svc['id_facture']):?>
        <a href="facture.php?id=<?=$svc['id_facture']?>" class="btn btn-primary" target="_blank">📄 <?=$is_ar?'الفاتورة':'Voir la facture'?></a>
        <?php endif;?>
      </div>
    </div>
  <?php else: ?>

  <!-- Payment method selection -->
  <div class="card">
    <div class="card-title" style="padding-bottom:10px"><?=$is_ar?'طريقة الدفع':'Mode de paiement'?></div>
    <form method="POST" id="pay-form">
      <input type="hidden" name="confirm_payment" value="1">
      <input type="hidden" name="id_service" value="<?=$id_service?>">
      <?php if(count($pending_rows) === 1 && $user_role === 'client'): ?>
        <input type="hidden" name="id_paiment" value="<?=intval($pending_rows[0]['id_paiment'])?>">
      <?php endif; ?>
      <div class="methods">

        <label class="method selected" onclick="selectMethod(this,'espèces')">
          <input type="radio" name="mode_paiment" value="espèces" checked>
          <div class="method-icon cash">💵</div>
          <div class="method-info">
            <div class="method-name"><?=$is_ar?'نقداً':'Espèces'?></div>
            <div class="method-desc"><?=$is_ar?'دفع نقدي مباشر':'Paiement en main propre'?></div>
          </div>
          <span class="method-badge badge-available"><?=$is_ar?'متاح':'Disponible'?></span>
          <div class="method-check"></div>
        </label>

        <label class="method" onclick="selectMethod(this,'edahabia')">
          <input type="radio" name="mode_paiment" value="edahabia">
          <div class="method-icon eda">📱</div>
          <div class="method-info">
            <div class="method-name">Edahabia</div>
            <div class="method-desc"><?=$is_ar?'بطاقة بريد الجزائر':'Carte Algérie Poste'?></div>
          </div>
          <span class="method-badge badge-soon"><?=$is_ar?'قريباً':'Bientôt'?></span>
          <div class="method-check"></div>
        </label>

        <label class="method" onclick="selectMethod(this,'cib')">
          <input type="radio" name="mode_paiment" value="cib">
          <div class="method-icon cib">💳</div>
          <div class="method-info">
            <div class="method-name">CIB</div>
            <div class="method-desc"><?=$is_ar?'البطاقة الإلكترونية البنكية':'Carte interbancaire'?></div>
          </div>
          <span class="method-badge badge-soon"><?=$is_ar?'قريباً':'Bientôt'?></span>
          <div class="method-check"></div>
        </label>

      </div>
      <div style="padding:0 20px 20px">
        <button type="submit" class="confirm-btn" id="confirm-btn">
          ✓ <?=$is_ar?'تأكيد الدفع':'Confirmer paiement'?>
        </button>
      </div>
    </form>
  </div>

  <?php endif; ?>

  <?php endif;?>
  <?php endif; ?>
</div>

<script>
function selectMethod(el, val) {
  document.querySelectorAll('.method').forEach(m => m.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input[type=radio]').checked = true;
  // Disable confirm for methods not yet available across all confirm buttons
  document.querySelectorAll('button.confirm-btn').forEach(function(btn){
    if (val === 'edahabia' || val === 'cib') {
      btn.disabled = true;
      btn.textContent = '⏳ <?=$is_ar?"قريباً":"Bientôt disponible"?>';
    } else {
      btn.disabled = false;
      btn.innerHTML = '✓ <?=$is_ar?"تأكيد الدفع":"Confirmer paiement"?>';
    }
  });
}

// Attach submit handlers to all payment forms: confirm and disable button to avoid double submits
document.querySelectorAll('form#pay-form').forEach(function(f){
  f.addEventListener('submit', function(e){
    if (!confirm('<?=$is_ar?"تأكيد الدفع؟":"Confirmer le paiement de ".number_format($display_amount,0)." DA ?"?>')) {
      e.preventDefault();
      return;
    }
    const btn = f.querySelector('button[type=submit], button.confirm-btn');
    if (btn) {
      btn.disabled = true;
      btn.textContent = '⏳ <?=$is_ar?"قيد المعالجة":"Traitement..."?>';
    }
  });
});
</script>
</body>
</html>