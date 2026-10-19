<?php
// ============================================================
// includes/services.php — Single source of truth for all services
// Used by: register-tech.php, dashboard-client.php, index.php, lang.php
//
// Each entry: 'key' => ['fr' => label, 'ar' => label, 'emoji' => icon]
// The 'key' is what gets stored in the DB (technicien.specialité)
// ============================================================

function all_services(): array {
    return [
        'Plomberie'      => ['fr' => 'Plomberie',                    'ar' => 'سباكة',                    'emoji' => '🔧'],
        'Électricité'    => ['fr' => 'Électricité',                  'ar' => 'كهرباء',                   'emoji' => '⚡'],
        'Menuiserie'     => ['fr' => 'Menuiserie',                   'ar' => 'نجارة',                    'emoji' => '🪵'],
        'Peinture'       => ['fr' => 'Peinture',                     'ar' => 'دهان',                     'emoji' => '🎨'],
        'Climatisation'  => ['fr' => 'Climatisation & Chauffage',    'ar' => 'تكييف وتدفئة',            'emoji' => '❄️'],
        'Électroménager' => ['fr' => 'Réparation électroménager',    'ar' => 'إصلاح أجهزة كهربائية',   'emoji' => '🔌'],
        'Carrelage'      => ['fr' => 'Carrelage & Revêtements',      'ar' => 'بلاط وتغليف',             'emoji' => '🏠'],
        'Maçonnerie'     => ['fr' => 'Maçonnerie & Gros œuvre',      'ar' => 'بناء وأعمال البناء',      'emoji' => '🧱'],
        'Serrurerie'     => ['fr' => 'Serrurerie & Sécurité',        'ar' => 'أقفال وأمن',              'emoji' => '🔐'],
        'Vitrerie'       => ['fr' => 'Vitrerie & Aluminium',         'ar' => 'زجاج وألومنيوم',          'emoji' => '🪟'],
        'Toiture'        => ['fr' => 'Toiture & Étanchéité',         'ar' => 'سقف وعزل',                'emoji' => '🏗️'],
        'Jardinage'      => ['fr' => 'Jardinage & Espaces verts',    'ar' => 'بستنة ومساحات خضراء',    'emoji' => '🌿'],
        'Informatique'   => ['fr' => 'Dépannage informatique',       'ar' => 'إصلاح كمبيوتر',           'emoji' => '💻'],
        'Télévision'     => ['fr' => 'Antenne & TV satellite',       'ar' => 'هوائي وبث فضائي',        'emoji' => '📡'],
        'Déménagement'   => ['fr' => 'Déménagement & Transport',     'ar' => 'نقل وتحريك',              'emoji' => '🚛'],
        'Nettoyage'      => ['fr' => 'Nettoyage & Entretien',        'ar' => 'نظافة وصيانة',            'emoji' => '🧹'],
        'Électronique'   => ['fr' => 'Réparation électronique',      'ar' => 'إصلاح إلكترونيات',       'emoji' => '📱'],
        'Marbre'         => ['fr' => 'Marbre & Granit',              'ar' => 'رخام وغرانيت',            'emoji' => '🪨'],
        'Soudure'        => ['fr' => 'Soudure & Ferronnerie',        'ar' => 'لحام وحدادة',             'emoji' => '🔩'],
        'Autre'          => ['fr' => 'Autre service',                'ar' => 'خدمة أخرى',               'emoji' => '🛠️'],
    ];
}

// Helper: get label for current language
function svc_label(string $key, string $lang = 'fr'): string {
    $all = all_services();
    if (!isset($all[$key])) return $key;
    return $all[$key][$lang] ?? $all[$key]['fr'];
}

// Helper: render a <select> of services
function services_select(string $name, string $lang, string $selected = '', string $placeholder = ''): string {
    $all = all_services();
    $ph  = $placeholder ?: ($lang === 'ar' ? '— اختر خدمة —' : '— Choisir un service —');
    $out = "<select name=\"{$name}\" required>\n  <option value=\"\">{$ph}</option>\n";
    foreach ($all as $key => $info) {
        $label = htmlspecialchars($lang === 'ar' ? $info['emoji'].' '.$info['ar'] : $info['emoji'].' '.$info['fr']);
        $sel   = ($selected === $key) ? ' selected' : '';
        $out  .= "  <option value=\"" . htmlspecialchars($key) . "\"{$sel}>{$label}</option>\n";
    }
    $out .= "</select>";
    return $out;
}