<?php
/**
 * Document head. Pages may set these before including it:
 *   $ecoTitle, $ecoStyles (array), $ecoScripts (array), $ecoBodyClass, $ecoMeta (array)
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}
if (!function_exists('page_title')) {
    require_once __DIR__ . '/functions.php';
}

$ecoTitle      = isset($ecoTitle) && $ecoTitle !== '' ? page_title($ecoTitle) : page_title();
$ecoStyles     = isset($ecoStyles) && is_array($ecoStyles) ? $ecoStyles : [];
$ecoScripts    = isset($ecoScripts) && is_array($ecoScripts) ? $ecoScripts : [];
$ecoBodyClass  = isset($ecoBodyClass) ? (string) $ecoBodyClass : '';
$ecoMeta       = isset($ecoMeta) && is_array($ecoMeta) ? $ecoMeta : [];
$ecoPage       = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$ecoIsLanding  = ($ecoPage === 'index.php' || $ecoPage === '');

if (!$ecoStyles) {
    $appCss = BASE_PATH . 'assets/css/app.css';
    $ecoStyles = $ecoIsLanding || !file_exists($appCss) ? ['assets/css/landing.css'] : ['assets/css/app.css'];
}

$ecoFontUrl = 'https://fonts.googleapis.com/css2'
    . '?family=Inter:wght@400;500;600'
    . '&family=JetBrains+Mono:wght@400;500;700'
    . '&family=Space+Grotesk:wght@500;700&display=swap';

$ecoFavicon = 'data:image/svg+xml,'
    . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
    . '<rect width="32" height="32" rx="7" fill="#0B0E13"/>'
    . '<g fill="none" stroke="#22D3EE" stroke-width="1.8">'
    . '<circle cx="16" cy="16" r="11"/><circle cx="16" cy="16" r="5.4"/></g>'
    . '<circle cx="16" cy="16" r="1.8" fill="#22D3EE"/></svg>');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0B0E13">
<title><?= e($ecoTitle) ?></title>
<meta name="description" content="EcoSense — an DHT22 and rain monitoring system that streams temperature, humidity and rain detection over Wi-Fi into a PHP/MySQL ingest API and a live monitoring dashboard.">
<meta name="author" content="<?= e(APP_AUTHOR) ?>">
<?php foreach ($ecoMeta as $name => $content): ?>
<meta name="<?= e($name) ?>" content="<?= e($content) ?>">
<?php endforeach; ?>
<script>document.documentElement.classList.add('js');</script>
<link rel="icon" href="<?= e($ecoFavicon) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e($ecoFavicon) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e($ecoFontUrl) ?>" media="print" onload="this.media='all'">
<noscript>
<link rel="stylesheet" href="<?= e($ecoFontUrl) ?>">
</noscript>
<?php foreach ($ecoStyles as $style): ?>
<link rel="stylesheet" href="<?= e(asset($style)) ?>">
<?php endforeach; ?>
</head>
<body class="page <?= e($ecoIsLanding ? 'page--landing' : 'page--app') ?> <?= e($ecoBodyClass) ?>" data-unit="<?= e($ecoUnit ?? preferred_unit()) ?>">
