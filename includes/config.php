<?php
// ═══════════════════════════════════════════════════════
//  config.php — charge la configuration locale
//  1) includes/config.local.php (ignoré par git), sinon
//  2) variables d'environnement, sinon valeurs par défaut
// ═══════════════════════════════════════════════════════
if (defined('CONFIG_LOADED')) { return; }
define('CONFIG_LOADED', true);

$local = __DIR__ . '/config.local.php';
if (is_file($local)) { require $local; }

function _cfg(string $key, $default = '') {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

foreach ([
    'DB_HOST'        => 'localhost',
    'DB_NAME'        => 'khidmadz1',
    'DB_USER'        => 'root',
    'DB_PASS'        => '',
    'MAIL_FROM'      => '',
    'MAIL_FROM_NAME' => 'Khidma DZ',
    'MAIL_HOST'      => 'smtp.gmail.com',
    'MAIL_PORT'      => 587,
    'MAIL_USER'      => '',
    'MAIL_PASS'      => '',
    'SITE_URL'       => 'http://localhost/khidma-dz',
] as $k => $default) {
    if (!defined($k)) { define($k, _cfg($k, $default)); }
}
