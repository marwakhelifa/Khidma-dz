<?php
// ============================================================
// includes/helpers.php — Logging, Notifications, i18n
// ============================================================

// --- ACTION LOGGER -------------------------------------------
function log_action(PDO $pdo, $user_id, string $action, string $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    try {
        $pdo->prepare("INSERT INTO log_action (id_user, action, details, ip) VALUES (?,?,?,?)")
            ->execute([$user_id, $action, $details, $ip]);
    } catch(Exception $e) { /* silent fail if table not yet created */ }
}

// --- NOTIFICATION CREATOR ------------------------------------
function notifier(PDO $pdo, $id_user, string $titre, string $contenu, string $lien = '') {
    try {
        $pdo->prepare("INSERT INTO notification (id_user, titre, contenu, lien) VALUES (?,?,?,?)")
            ->execute([$id_user, $titre, $contenu, $lien]);
    } catch(Exception $e) { /* silent fail */ }
}

function get_unread_notifs(PDO $pdo, $user_id): array {
    try {
        $s = $pdo->prepare("SELECT * FROM notification WHERE id_user=? AND lu=0 ORDER BY date_notif DESC LIMIT 20");
        $s->execute([$user_id]);
        return $s->fetchAll();
    } catch(Exception $e) { return []; }
}

function mark_notifs_read(PDO $pdo, $user_id) {
    try {
        $pdo->prepare("UPDATE notification SET lu=1 WHERE id_user=?")->execute([$user_id]);
    } catch(Exception $e) {}
}

function count_unread_notifs(PDO $pdo, $user_id): int {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user=? AND lu=0");
        $s->execute([$user_id]);
        return (int)$s->fetchColumn();
    } catch(Exception $e) { return 0; }
}

// --- INVOICE RULES ------------------------------------------
function facture_tva_threshold(): float {
    return 5000.0;
}

function facture_uses_tva(float $amount): bool {
    return $amount >= facture_tva_threshold();
}

// Safe formatter for DB date fields (handles NULL / empty / '0000-00-00')
function format_db_date($date, $format = 'd/m/Y', $fallback = '—') {
    if ($date === null || $date === '' || $date === '0000-00-00') return $fallback;
    $ts = strtotime($date);
    if ($ts === false || $ts === -1) return $fallback;
    return date($format, $ts);
}

// --- TRANSLATIONS (FR / AR) ----------------------------------
$TRANSLATIONS = [
    'fr' => [
        'accueil'         => 'Accueil',
        'recherche'       => 'Rechercher',
        'reservations'    => 'Mes réservations',
        'messages'        => 'Messages',
        'profil'          => 'Mon profil',
        'litiges'         => 'Mes litiges',
        'notifications'   => 'Notifications',
        'deconnexion'     => 'Déconnexion',
        'bonjour'         => 'Bonjour',
        'enregistrer'     => 'Enregistrer',
        'annuler'         => 'Annuler',
        'confirmer'       => 'Confirmer',
        'envoyer'         => 'Envoyer',
        'reserver'        => 'Réserver',
        'evaluer'         => 'Évaluer',
        'statut'          => 'Statut',
        'date'            => 'Date',
        'prix'            => 'Prix',
        'technicien'      => 'Technicien',
        'service'         => 'Service',
        'planifie'        => 'Planifié',
        'en_cours'        => 'En cours',
        'termine'         => 'Terminé',
        'annule'          => 'Annulé',
        'ouvert'          => 'Ouvert',
        'resolu'          => 'Résolu',
        'ferme'           => 'Fermé',
    ],
    'ar' => [
        'accueil'         => 'الرئيسية',
        'recherche'       => 'بحث',
        'reservations'    => 'حجوزاتي',
        'messages'        => 'الرسائل',
        'profil'          => 'ملفي',
        'litiges'         => 'النزاعات',
        'notifications'   => 'الإشعارات',
        'deconnexion'     => 'تسجيل الخروج',
        'bonjour'         => 'مرحبا',
        'enregistrer'     => 'حفظ',
        'annuler'         => 'إلغاء',
        'confirmer'       => 'تأكيد',
        'envoyer'         => 'إرسال',
        'reserver'        => 'حجز',
        'evaluer'         => 'تقييم',
        'statut'          => 'الحالة',
        'date'            => 'التاريخ',
        'prix'            => 'السعر',
        'technicien'      => 'التقني',
        'service'         => 'الخدمة',
        'planifie'        => 'مجدول',
        'en_cours'        => 'جاري',
        'termine'         => 'منتهي',
        'annule'          => 'ملغى',
        'ouvert'          => 'مفتوح',
        'resolu'          => 'محلول',
        'ferme'           => 'مغلق',
    ],
];


// --- PDF INVOICE GENERATOR -----------------------------------
// Generates a simple HTML invoice (printed via browser or wkhtmltopdf)
function generate_invoice_html(array $service, array $client, array $tech, array $facture): string {
    $date = $facture['date_facture'] ? date('d/m/Y', strtotime($facture['date_facture'])) : date('d/m/Y');
    $montant = number_format($facture['montant_total'] ?? $service['prix_final'] ?? 0, 2);
    $num = str_pad($facture['id_facture'], 6, '0', STR_PAD_LEFT);
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Facture #'.$num.' — Khidma DZ</title>
<style>
body{font-family:Arial,sans-serif;color:#111;padding:40px;max-width:700px;margin:auto;}
.logo{font-size:28px;font-weight:900;color:#16a34a;margin-bottom:4px;}
.logo span{color:#111;}
h2{margin:0 0 4px;font-size:18px;}
.meta{display:flex;justify-content:space-between;margin:30px 0 20px;}
.block{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px 18px;min-width:200px;}
.block-title{font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;}
table{width:100%;border-collapse:collapse;margin:20px 0;}
th{background:#16a34a;color:#fff;padding:10px 14px;text-align:left;font-size:13px;}
td{padding:10px 14px;border-bottom:1px solid #e5e7eb;font-size:14px;}
.total-row td{font-weight:700;font-size:16px;background:#f0fdf4;color:#16a34a;}
.footer{margin-top:40px;font-size:12px;color:#9ca3af;text-align:center;border-top:1px solid #e5e7eb;padding-top:16px;}
@media print{body{padding:20px;}.no-print{display:none;}}
</style></head><body>
<div class="logo">Khidma <span>DZ</span></div>
<p style="color:#6b7280;font-size:13px;margin:0">Plateforme de services techniques en Algérie</p>
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:30px;">
  <div><h2>FACTURE</h2><p style="color:#6b7280;font-size:13px;margin:4px 0">N° FAC-'.$num.'</p><p style="color:#6b7280;font-size:13px;margin:0">Date : '.$date.'</p></div>
  <div style="background:#dcfce7;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:700;color:#16a34a;">✓ Payée</div>
</div>
<div class="meta">
  <div class="block"><div class="block-title">Client</div>
    <div style="font-weight:600">'.htmlspecialchars($client['prenom'].' '.$client['nom']).'</div>
    <div style="font-size:13px;color:#6b7280;margin-top:4px">'.htmlspecialchars($client['email']).'</div>
    <div style="font-size:13px;color:#6b7280">'.htmlspecialchars($client['telephone']??'').'</div>
    <div style="font-size:13px;color:#6b7280">'.htmlspecialchars($client['ville']??'').'</div>
  </div>
  <div class="block"><div class="block-title">Prestataire</div>
    <div style="font-weight:600">'.htmlspecialchars($tech['prenom'].' '.$tech['nom']).'</div>
    <div style="font-size:13px;color:#16a34a;margin-top:4px">'.htmlspecialchars($tech['specialité']??'').'</div>
    <div style="font-size:13px;color:#6b7280">'.htmlspecialchars($tech['telephone']??'').'</div>
    <div style="font-size:13px;color:#6b7280">'.htmlspecialchars($tech['ville']??'').'</div>
  </div>
</div>
<table>
  <thead><tr><th>Description</th><th>Date</th><th>Montant</th></tr></thead>
  <tbody>
    <tr><td>'.htmlspecialchars($service['description']??$tech['specialité']??'Prestation de service').'</td>
        <td>'.($service['date_service']?date('d/m/Y',strtotime($service['date_service'])):'—').'</td>
        <td>'.$montant.' DA</td></tr>
  </tbody>
  <tfoot><tr class="total-row"><td colspan="2">TOTAL TTC</td><td>'.$montant.' DA</td></tr></tfoot>
</table>
<div class="footer">Khidma DZ — Merci pour votre confiance · khidma-dz.com</div>
<div class="no-print" style="margin-top:24px;text-align:center">
  <button onclick="window.print()" style="background:#16a34a;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer">🖨 Imprimer / Télécharger</button>
  <button onclick="window.close()" style="background:#f9fafb;color:#111;border:1px solid #e5e7eb;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-left:10px">Fermer</button>
</div>
</body></html>';
}

// ── Wilaya helpers (FR / AR) ─────────────────────────────────
function wilayas_fr(): array {
    return [1=>'Adrar',2=>'Chlef',3=>'Laghouat',4=>'Oum El Bouaghi',5=>'Batna',6=>'Béjaïa',7=>'Biskra',8=>'Béchar',9=>'Blida',10=>'Bouira',11=>'Tamanrasset',12=>'Tébessa',13=>'Tlemcen',14=>'Tiaret',15=>'Tizi Ouzou',16=>'Alger',17=>'Djelfa',18=>'Jijel',19=>'Sétif',20=>'Saïda',21=>'Skikda',22=>'Sidi Bel Abbès',23=>'Annaba',24=>'Guelma',25=>'Constantine',26=>'Médéa',27=>'Mostaganem',28=>"M'Sila",29=>'Mascara',30=>'Ouargla',31=>'Oran',32=>'El Bayadh',33=>'Illizi',34=>'Bordj Bou Arréridj',35=>'Boumerdès',36=>'El Tarf',37=>'Tindouf',38=>'Tissemsilt',39=>'El Oued',40=>'Khenchela',41=>'Souk Ahras',42=>'Tipaza',43=>'Mila',44=>'Aïn Defla',45=>'Naâma',46=>'Aïn Témouchent',47=>'Ghardaïa',48=>'Relizane',49=>'Timimoun',50=>'Bordj Badji Mokhtar',51=>'Ouled Djellal',52=>'Béni Abbès',53=>'In Salah',54=>'In Guezzam',55=>'Touggourt',56=>'Djanet',57=>"El M'Ghair",58=>'El Meniaa',59=>'Aflou',60=>'El Abiodh Sidi Cheikh',61=>'El Aricha',62=>'El Kantara',63=>'Barika',64=>'Bou Saâda',65=>'Bir El Ater',66=>'Ksar El Boukhari',67=>'Ksar Chellala',68=>'Aïn Oussera',69=>'Messaâd'];
}
function wilayas_ar(): array {
    return [1=>'أدرار',2=>'الشلف',3=>'الأغواط',4=>'أم البواقي',5=>'باتنة',6=>'بجاية',7=>'بسكرة',8=>'بشار',9=>'البليدة',10=>'البويرة',11=>'تمنراست',12=>'تبسة',13=>'تلمسان',14=>'تيارت',15=>'تيزي وزو',16=>'الجزائر',17=>'الجلفة',18=>'جيجل',19=>'سطيف',20=>'سعيدة',21=>'سكيكدة',22=>'سيدي بلعباس',23=>'عنابة',24=>'قالمة',25=>'قسنطينة',26=>'المدية',27=>'مستغانم',28=>'المسيلة',29=>'معسكر',30=>'ورقلة',31=>'وهران',32=>'البيض',33=>'إليزي',34=>'برج بوعريريج',35=>'بومرداس',36=>'الطارف',37=>'تندوف',38=>'تيسمسيلت',39=>'الوادي',40=>'خنشلة',41=>'سوق أهراس',42=>'تيبازة',43=>'ميلة',44=>'عين الدفلى',45=>'النعامة',46=>'عين تيموشنت',47=>'غرداية',48=>'غليزان',49=>'تيميمون',50=>'برج باجي مختار',51=>'أولاد جلال',52=>'بني عباس',53=>'عين صالح',54=>'عين قزام',55=>'تقرت',56=>'جانت',57=>'المغير',58=>'المنيعة',59=>'أفلو',60=>'الأبيض سيدي الشيخ',61=>'الأريشة',62=>'القنطرة',63=>'بريكة',64=>'بوسعادة',65=>'بئر العاتر',66=>'قصر البخاري',67=>'قصر الشلالة',68=>'عين وسارة',69=>'مسعد'];
}

function wilaya_norm(string $v): string {
    $v = trim($v);
    $v = preg_replace('/^\d+\s*[-\x{2013}\x{2014}]\s*/u', '', $v);
    if ($v === '') return '';
    return mb_strtolower(trim((string)$v), 'UTF-8');
}

function wilaya_display($value, $lang = 'fr') {
    if ($value === null || $value === '') return $lang === 'ar' ? '—' : '—';
    $fr = wilayas_fr(); $ar = wilayas_ar();
    // numeric id
    if (is_numeric($value) && isset($fr[(int)$value])) {
        return $lang === 'ar' ? $ar[(int)$value] : $fr[(int)$value];
    }
    // try to match against french list
    $norm = wilaya_norm((string)$value);
    foreach ($fr as $k => $name) {
        if (wilaya_norm($name) === $norm) return $lang === 'ar' ? $ar[$k] : $fr[$k];
    }
    // try to match against arabic list
    foreach ($ar as $k => $name) {
        if (wilaya_norm($name) === $norm) return $lang === 'ar' ? $ar[$k] : $fr[$k];
    }
    // fallback: return original
    return $value;
}