<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/lang.php';
require_once 'includes/services.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'client';
$lang      = get_lang();
$ar        = $lang === 'ar';
$dir       = $ar ? 'rtl' : 'ltr';

function fmt_money($n, $ar) {
    $f = number_format((float)$n, 0, '', ' ');
    if ($ar) {
        return '<span dir="ltr" style="unicode-bidi:embed">' . $f . ' دج</span>';
    }
    return $f . ' DA';
}

$id_facture = intval($_GET['id'] ?? 0);
if (!$id_facture) { header('Location: dashboard-client.php'); exit; }

$stmt = $pdo->prepare("
    SELECT f.*,
           s.description AS svc_desc, s.date_service, s.statut_service,
           s.prix_estime, s.prix_final, s.id_service,
           t.specialité,
           ut.nom AS tn, ut.prenom AS tp, ut.telephone AS t_tel, ut.email AS t_email,
           uc.nom AS cn, uc.prenom AS cp, uc.telephone AS c_tel, uc.email AS c_email,
           uc.ville AS c_ville,
           p.mode_paiment, p.date_paiment
    FROM facture f
    JOIN service s      ON s.id_service     = f.id_service
    JOIN technicien t   ON t.id_technicien  = s.id_technicien
    JOIN utilisateur ut ON ut.id_user       = t.id_user
    JOIN client c       ON c.id_client      = s.id_client
    JOIN utilisateur uc ON uc.id_user       = c.id_user
    LEFT JOIN paiment p ON p.id_service     = s.id_service
    WHERE f.id_facture = ?
");
$stmt->execute([$id_facture]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) { header('Location: dashboard-client.php'); exit; }

// Fetch prestations for this service
$fac_prests = [];
$sp = $pdo->prepare("SELECT pr.nom, COALESCE(pc.nom_ar,'') as nom_ar, pr.description, pr.prix_unitaire, pr.unite, sp.quantite FROM service_prestation sp JOIN prestation pr ON pr.id_prestation=sp.id_prestation LEFT JOIN prestation_catalogue pc ON pc.id_catalogue=pr.id_catalogue WHERE sp.id_service=?");
$sp->execute([$inv['id_service']]); $fac_prests = $sp->fetchAll();

$ok = ($user_role === 'admin');
if (!$ok && $user_role === 'client') {
    $r = $pdo->prepare("SELECT c.id_user FROM client c JOIN service s ON s.id_client=c.id_client WHERE s.id_service=?");
    $r->execute([$inv['id_service']]); if ($r->fetchColumn() == $user_id) $ok = true;
}
if (!$ok && $user_role === 'technicien') {
    $r = $pdo->prepare("SELECT t.id_user FROM technicien t JOIN service s ON s.id_technicien=t.id_technicien WHERE s.id_service=?");
    $r->execute([$inv['id_service']]); if ($r->fetchColumn() == $user_id) $ok = true;
}
if (!$ok) { header('Location: dashboard-client.php'); exit; }

$invoice_num  = 'KDZ-' . str_pad($id_facture, 6, '0', STR_PAD_LEFT);
$date_facture = format_db_date($inv['date_facture']);
$date_prestation = format_db_date($inv['date_paiment'] ?? $inv['date_facture'] ?? $inv['date_service']);
$same_day_dates = ($date_facture !== '—' && $date_facture === $date_prestation);
// Recalculate from prestations if available (auto-facture)
$calc_q = $pdo->prepare("SELECT COALESCE(SUM(p.prix_unitaire * sp.quantite),0) FROM service_prestation sp JOIN prestation p ON p.id_prestation=sp.id_prestation WHERE sp.id_service=?");
$calc_q->execute([$inv['id_service']]); $calc_total = floatval($calc_q->fetchColumn());
$montant_ttc_db = floatval($inv['montant_total']);
$montant_base = $calc_total > 0 ? $calc_total : $montant_ttc_db;
$montant_ht = $montant_base;
$tva_rate = 0.19;
$tva_amount = facture_uses_tva($montant_base) ? round($montant_base * $tva_rate, 2) : 0;
$montant_ttc = round($montant_base + $tva_amount, 2);
$montant = $montant_ht;
$mode_labels  = ['espèces'=>$ar?'نقداً':'Espèces','edahabia'=>'Edahabia','cib'=>'CIB','virement'=>$ar?'تحويل بنكي':'Virement bancaire'];
$mode_label   = $mode_labels[$inv['mode_paiment'] ?? 'espèces'] ?? 'Espèces';
$back_url     = $user_role === 'technicien' ? 'dashboard-tech.php' : ($user_role === 'admin' ? 'dashboard-admin.php' : 'dashboard-client.php');
?>
<!DOCTYPE html>
<html lang="<?=$lang?>" dir="<?=$dir?>">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?=$ar?'الفاتورة':'Facture'?> <?=$invoice_num?> — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
/* ── Screen styles ── */
:root{
  --v:#7c3aed;--vd:#5b21b6;--vl:#ede9fe;--vm:#c4b5fd;
  --c:#06b6d4;--cl:#ecfeff;
  --green:#16a34a;--green-l:#dcfce7;
  --text:#0f0c1d;--text2:#3d3655;--mu:#8b83a8;
  --border:#e8e4f8;--border2:#d1d5db;
  --bg:#f8f7fc;--surface:#fff;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;line-height:1.6;}
[dir=rtl] body{font-family:'Cairo',sans-serif;}
a{text-decoration:none;color:inherit;}

/* Topbar */
.topbar{position:sticky;top:0;z-index:100;height:62px;background:rgba(248,247,252,.95);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 32px;justify-content:space-between;}
[dir=rtl] .topbar{flex-direction:row-reverse;}
.top-logo{font-family:'Syne',sans-serif;font-weight:800;font-size:18px;color:var(--text);}
.top-logo span{color:var(--v);}
.top-actions{display:flex;gap:10px;align-items:center;}
.btn{display:inline-flex;align-items:center;gap:7px;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;border-radius:10px;font-size:13px;padding:8px 18px;transition:all .2s;text-decoration:none;}
.btn-back{background:transparent;border:1.5px solid var(--border2);color:var(--text2);}
.btn-back:hover{border-color:var(--v);color:var(--v);}
.btn-print{background:var(--v);color:#fff;box-shadow:0 3px 10px rgba(124,58,237,.3);}
.btn-print:hover{background:var(--vd);transform:translateY(-1px);}

/* Page layout */
.page{max-width:820px;margin:0 auto;padding:40px 24px 60px;}

/* Invoice card */
.inv{background:#fff;border:1px solid var(--border);border-radius:24px;overflow:hidden;box-shadow:0 8px 40px rgba(124,58,237,.08),0 2px 8px rgba(0,0,0,.04);}

/* Header stripe */
.inv-head{padding:36px 44px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px;border-bottom:1px solid var(--border);}
.brand-name{font-family:'Syne',sans-serif;font-weight:800;font-size:26px;letter-spacing:-.5px;color:var(--text);margin-bottom:4px;}
.brand-name span{color:var(--v);}
.brand-sub{font-size:12px;color:var(--mu);letter-spacing:.3px;}
.brand-contact{font-size:12px;color:var(--mu);margin-top:10px;}
.inv-meta{text-align:right;}
[dir=rtl] .inv-meta{text-align:left;}
.inv-num{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:var(--v);letter-spacing:.5px;margin-bottom:6px;}
.inv-dates{font-size:12.5px;color:var(--mu);line-height:1.9;}
.inv-dates strong{color:var(--text2);}

/* Paid ribbon */
.inv-ribbon{background:linear-gradient(135deg,var(--green-l),#f0fdf4);border-bottom:1px solid #bbf7d0;padding:12px 44px;display:flex;align-items:center;gap:10px;}
.ribbon-badge{display:flex;align-items:center;gap:7px;background:#fff;border:1.5px solid #86efac;border-radius:20px;padding:4px 13px;}
.ribbon-dot{width:8px;height:8px;border-radius:50%;background:var(--green);flex-shrink:0;}
.ribbon-txt{font-size:12.5px;font-weight:700;color:var(--green);}
.ribbon-mode{margin-left:auto;font-size:12.5px;color:var(--mu);}
[dir=rtl] .ribbon-mode{margin-left:0;margin-right:auto;}
/* Additional RTL fixes */
[dir=rtl] .inv-head{flex-direction:row-reverse;}
[dir=rtl] .inv-meta{text-align:left;}
[dir=rtl] .inv-footer{flex-direction:row-reverse;}
[dir=rtl] .inv-ribbon{flex-direction:row-reverse;}
[dir=rtl] thead th{text-align:right;}
[dir=rtl] thead th:last-child{text-align:left;}
[dir=rtl] .td-right{text-align:left;}
[dir=rtl] .party-label{flex-direction:row-reverse;}
[dir=rtl] .total-row{flex-direction:row-reverse;}
[dir=rtl] .top-actions{flex-direction:row-reverse;}
[dir=rtl] .btn-back::before{content:'→ ';}
[dir=rtl] body{font-family:'Cairo',sans-serif;}
[dir=rtl] .party-name,[dir=rtl] .brand-name,[dir=rtl] .inv-num{font-family:'Syne',sans-serif;}

/* Parties */
.inv-parties{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid var(--border);}
.party{padding:28px 44px;}
.party+.party{border-left:1px solid var(--border);}
[dir=rtl] .party+.party{border-left:none;border-right:1px solid var(--border);}
.party-label{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--v);margin-bottom:12px;display:flex;align-items:center;gap:7px;}
.party-label::before{content:'';width:14px;height:2px;background:linear-gradient(90deg,var(--v),var(--c));border-radius:2px;}
[dir=rtl] .party-label::before{display:none;}
[dir=rtl] .party-label::after{content:'';width:14px;height:2px;background:linear-gradient(270deg,var(--v),var(--c));border-radius:2px;}
.party-name{font-family:'Syne',sans-serif;font-size:17px;font-weight:800;color:var(--text);margin-bottom:8px;}
.party-row{font-size:13px;color:var(--text2);margin-bottom:4px;display:flex;align-items:center;gap:6px;}
.party-row span{color:var(--mu);}

/* Service table */
.inv-table-wrap{padding:28px 44px;border-bottom:1px solid var(--border);}
.table-eyebrow{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--c);margin-bottom:16px;display:flex;align-items:center;gap:7px;}
.table-eyebrow::before{content:'';width:14px;height:2px;background:linear-gradient(90deg,var(--c),var(--v));border-radius:2px;}
[dir=rtl] .table-eyebrow::before{display:none;}
[dir=rtl] .table-eyebrow::after{content:'';width:14px;height:2px;background:linear-gradient(270deg,var(--c),var(--v));border-radius:2px;}
table{width:100%;border-collapse:collapse;}
thead th{font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--mu);padding:10px 0;border-bottom:2px solid var(--border);text-align:left;}
[dir=rtl] thead th{text-align:right;}
thead th:last-child{text-align:right;}
[dir=rtl] thead th:last-child{text-align:left;}
tbody td{padding:16px 0;font-size:14px;border-bottom:1px solid var(--border);vertical-align:top;}
tbody tr:last-child td{border-bottom:none;}
.td-svc{font-weight:700;color:var(--text);}
.td-desc{font-size:12px;color:var(--mu);margin-top:5px;line-height:1.65;}
.td-right{text-align:right;}
[dir=rtl] .td-right{text-align:left;}
.td-amount{font-family:'Syne',sans-serif;font-size:15px;font-weight:800;color:var(--v);}

/* Totals */
.inv-totals{padding:24px 44px;border-bottom:1px solid var(--border);background:#fdfcff;}
.total-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:13.5px;color:var(--mu);}
.total-row.main{padding-top:14px;margin-top:10px;border-top:2px solid var(--border);}
.total-row.main .tl{font-family:'Syne',sans-serif;font-size:17px;font-weight:800;color:var(--text);}
.total-row.main .tv{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:var(--v);}

/* Footer */
.inv-footer{padding:24px 44px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
.inv-note{font-size:12.5px;color:var(--mu);line-height:1.75;max-width:420px;}
.inv-note strong{color:var(--text2);}
.inv-ref{font-size:11px;color:var(--border2);margin-top:8px;}
.stamp{width:76px;height:76px;border-radius:50%;border:2.5px solid var(--green);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;flex-shrink:0;}
.stamp-ico{font-size:22px;line-height:1;}
.stamp-txt{font-size:8.5px;font-weight:800;color:var(--green);letter-spacing:.8px;text-transform:uppercase;}

/* ── PRINT OVERRIDES ── */
@media print{
  @page{size:A4;margin:14mm 12mm;}
  .topbar,.btn-print,.btn-back{display:none!important;}
  body{background:#fff!important;}
  .page{padding:0!important;max-width:100%!important;}
  .inv{border:1px solid #ddd!important;box-shadow:none!important;border-radius:12px!important;}
  .inv-head{background:#f5f3ff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  .inv-ribbon{background:#f0fdf4!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  .inv-totals{background:#fdfcff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  .total-row.main .tv{color:#7c3aed!important;}
  .party-label{color:#7c3aed!important;}
  .brand-name span,.td-amount,.inv-num{color:#7c3aed!important;}
  .table-eyebrow{color:#0891b2!important;}
  .stamp{border-color:#16a34a!important;}
  .stamp-txt{color:#16a34a!important;}
}
@media(max-width:640px){
  .topbar{padding:0 16px;}
  .page{padding:20px 12px 40px;}
  .inv-head,.inv-parties,.inv-table-wrap,.inv-totals,.inv-footer{padding-left:20px;padding-right:20px;}
  .inv-parties{grid-template-columns:1fr;}
  .party+.party{border-left:none;border-top:1px solid var(--border);}
  [dir=rtl] .party+.party{border-right:none;border-top:1px solid var(--border);}
}
</style>
</head>
<body>

<div class="topbar">
  <a href="index.php" class="top-logo">Khidma <span>DZ</span></a>
  <div class="top-actions">
    <a href="<?=$back_url?>" class="btn btn-back"><?=$ar?'رجوع →':'← Retour'?></a>
    <button onclick="window.print()" class="btn btn-print">🖨️ <?=$ar?'طباعة / PDF':'Imprimer / PDF'?></button>
  </div>
</div>

<div class="page">
  <div class="inv">

    <!-- HEADER -->
    <div class="inv-head">
      <div>
        <div class="brand-name">Khidma <span>DZ</span></div>
        <div class="brand-sub"><?=$ar?'منصة الخدمات التقنية في الجزائر':'Plateforme de services techniques en Algérie'?></div>
        <div class="brand-contact">contact@khidmadz.dz &nbsp;·&nbsp; 🇩🇿 Algérie</div>
      </div>
      <div class="inv-meta">
        <div class="inv-num" dir="ltr" style="unicode-bidi:isolate"><?=$invoice_num?></div>
        <div class="inv-dates">
          <?php if($same_day_dates): ?>
          <?=$ar?'التاريخ':'Date'?> : <strong><?=$date_facture?></strong>
          <?php else: ?>
          <?=$ar?'تاريخ الإصدار':'Émission'?> : <strong><?=$date_facture?></strong><br>
          <?=$ar?'تاريخ الخدمة':'Prestation'?> : <strong><?=$date_prestation?></strong>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- PAID RIBBON -->
    <div class="inv-ribbon">
      <div class="ribbon-badge">
        <div class="ribbon-dot"></div>
        <div class="ribbon-txt"><?=$ar?'✓ مدفوعة — مؤكدة من الطرفين':'✓ PAYÉE — Confirmée par les deux parties'?></div>
      </div>
      <div class="ribbon-mode">
        💳 <?=$mode_label?>
        <?php if(!empty($inv['date_paiment']) && $inv['date_paiment'] !== '0000-00-00'): ?>&nbsp;·&nbsp; <?=format_db_date($inv['date_paiment'])?><?php endif;?>
      </div>
    </div>

    <!-- PARTIES -->
    <div class="inv-parties">
      <div class="party">
        <div class="party-label"><?=$ar?'فاتورة إلى':'Facturé à'?></div>
        <div class="party-name"><?=htmlspecialchars($inv['cp'].' '.$inv['cn'])?></div>
        <?php if($inv['c_ville']): ?><div class="party-row">📍 <?=htmlspecialchars($inv['c_ville'])?>, Algérie</div><?php endif;?>
        <div class="party-row">📞 <span><?=htmlspecialchars($inv['c_tel']??'—')?></span></div>
        <div class="party-row">✉️ <span><?=htmlspecialchars($inv['c_email']??'—')?></span></div>
      </div>
      <div class="party">
        <div class="party-label"><?=$ar?'مقدم الخدمة':'Prestataire'?></div>
        <div class="party-name"><?=htmlspecialchars($inv['tp'].' '.$inv['tn'])?></div>
        <div class="party-row">🔧 <?=htmlspecialchars(svc_label($inv['specialité']??'',$lang)?:($inv['specialité']??'—'))?></div>
        <div class="party-row">📞 <span><?=htmlspecialchars($inv['t_tel']??'—')?></span></div>
        <div class="party-row">✉️ <span><?=htmlspecialchars($inv['t_email']??'—')?></span></div>
      </div>
    </div>

    <!-- SERVICE TABLE -->
    <div class="inv-table-wrap">
      <div class="table-eyebrow"><?=$ar?'تفاصيل الخدمة':'Détail de la prestation'?></div>
      <table>
        <thead><tr>
          <th style="width:55%"><?=$ar?'الخدمة':'Prestation'?></th>
          <th><?=$ar?'الكمية':'Qté'?></th>
          <th><?=$ar?'سعر الوحدة':'Prix unit.'?></th>
          <th class="td-right"><?=$ar?'المجموع':'Total'?></th>
        </tr></thead>
        <tbody>
          <?php if(!empty($fac_prests)): ?>
            <?php foreach($fac_prests as $pr): ?>
            <?php $subtotal = ($pr['prix_unitaire']??0) * ($pr['quantite']??1); ?>
            <tr>
              <td>
                <div class="td-svc"><?=htmlspecialchars(($ar&&!empty($pr['nom_ar']))?$pr['nom_ar']:$pr['nom'])?></div>
                <?php if($pr['description']): ?><div class="td-desc"><?=htmlspecialchars($pr['description'])?></div><?php endif;?>
              </td>
              <td><span dir="ltr" style="unicode-bidi:embed"><?=$pr['quantite']??1?></span> <?php if($ar){$um=['heure'=>'ساعة','jour'=>'يوم','unité'=>'وحدة','intervention'=>'تدخل','u'=>'وحدة'];echo $um[strtolower($pr['unite']??'u')]??htmlspecialchars($pr['unite']??'وحدة');}else{echo htmlspecialchars($pr['unite']??'u');}?></td>
              <td><?=fmt_money($pr['prix_unitaire']??0,$ar)?></td>
              <td class="td-right td-amount"><?=fmt_money($subtotal,$ar)?></td>
            </tr>
            <?php endforeach; ?>
            <?php if($inv['svc_desc']): ?>
            <tr><td colspan="4"><div class="td-desc" style="padding:4px 0"><?=nl2br(htmlspecialchars($inv['svc_desc']))?></div></td></tr>
            <?php endif; ?>
          <?php else: ?>
          <tr>
            <td>
              <div class="td-svc"><?=htmlspecialchars($inv['specialité']??'Service')?></div>
              <?php if($inv['svc_desc']): ?><div class="td-desc"><?=nl2br(htmlspecialchars($inv['svc_desc']))?></div><?php endif;?>
            </td>
            <td>1</td>
            <td><?=fmt_money($montant,$ar)?></td>
            <td class="td-right td-amount"><?=fmt_money($montant,$ar)?></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- TOTALS -->
    <div class="inv-totals">
      <?php if($inv['prix_estime'] && floatval($inv['prix_estime']) !== $montant): ?>
      <div class="total-row"><span><?=$ar?'السعر التقديري':'Estimé initial'?></span><span><?=fmt_money($inv['prix_estime'],$ar)?></span></div>
      <?php endif;?>
      <div class="total-row"><span><?=$ar?'المبلغ دون ضرائب':'Montant HT'?></span><span><?=fmt_money($montant_ht,$ar)?></span></div>
      <?php if(facture_uses_tva($montant_base)):?>
      <div class="total-row"><span>TVA (19%)</span><span><?=fmt_money($tva_amount,$ar)?></span></div>
      <?php endif;?>
      <div class="total-row main">
        <span class="tl"><?=$ar?'المبلغ الإجمالي مع الضريبة':'Total TTC'?></span>
        <span class="tv"><?=fmt_money($montant_ttc,$ar)?></span>
      </div>
    </div>

    <!-- FOOTER -->
    <div class="inv-footer">
      <div class="inv-note">
        <strong><?=$ar?'شكراً لثقتكم في Khidma DZ':'Merci de votre confiance en Khidma DZ'?></strong><br>
        <?=$ar?'هذه الفاتورة تُثبت اكتمال الخدمة وتأكيد الدفع من الطرفين. يُرجى الاحتفاظ بها كوثيقة رسمية.':'Cette facture atteste de la réalisation du service et de la confirmation du paiement par les deux parties.'?>
        <div class="inv-ref"><?=$invoice_num?> · <?=$ar?'مرجع':'Réf.'?> #<?=$inv['id_service']?> · Fait avec ❤️ en Algérie 🇩🇿</div>
      </div>
      <div class="stamp">
        <div class="stamp-ico">✓</div>
        <div class="stamp-txt"><?=$ar?'مدفوعة':'PAYÉE'?></div>
      </div>
    </div>

  </div>
</div>
</body>
</html>