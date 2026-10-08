<?php
/**
 * EcoSense — central configuration.
 * Every value is guarded so this file can be edited without breaking includes.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', APP_ROOT . '/');
}

if (!defined('DB_HOST'))    define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT'))    define('DB_PORT', '3306');
if (!defined('DB_NAME'))    define('DB_NAME', 'ecosense');
if (!defined('DB_USER'))    define('DB_USER', 'root');
if (!defined('DB_PASS'))    define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

if (!defined('APP_NAME'))     define('APP_NAME', 'EcoSense');
if (!defined('APP_TAGLINE'))  define('APP_TAGLINE', 'Real-time weather detection & temperature monitoring');
if (!defined('APP_SHORT'))    define('APP_SHORT', 'IoT weather telemetry');
if (!defined('APP_VERSION'))  define('APP_VERSION', '1.0.0');
if (!defined('APP_AUTHOR'))   define('APP_AUTHOR', 'EcoSense Project Team');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Asia/Manila');

/* When true the app never attempts a DB connection (use for static demos). */
if (!defined('OFFLINE_MODE')) define('OFFLINE_MODE', false);

/* Show errors only while developing; keeps production output clean. */
if (!defined('APP_DEBUG')) define('APP_DEBUG', false);

if (!defined('RETENTION_DAYS'))      define('RETENTION_DAYS', 30);
if (!defined('DEVICE_ONLINE_WINDOW')) define('DEVICE_ONLINE_WINDOW', 60);
if (!defined('TELEMETRY_INTERVAL'))   define('TELEMETRY_INTERVAL', 2);
if (!defined('DASHBOARD_POLL_MS'))    define('DASHBOARD_POLL_MS', 3000);
if (!defined('INGEST_RATE_LIMIT'))    define('INGEST_RATE_LIMIT', 60);

if (!defined('TEMP_WARN_HIGH'))  define('TEMP_WARN_HIGH', 32.0);
if (!defined('TEMP_CRIT_HIGH'))  define('TEMP_CRIT_HIGH', 38.0);
if (!defined('TEMP_WARN_LOW'))   define('TEMP_WARN_LOW', 12.0);
if (!defined('HUMIDITY_WARN'))   define('HUMIDITY_WARN', 80.0);
if (!defined('HUMIDITY_LOW'))    define('HUMIDITY_LOW', 20.0);
if (!defined('PRESSURE_LOW'))    define('PRESSURE_LOW', 990.0);
if (!defined('BATTERY_WARN'))    define('BATTERY_WARN', 25);

if (!defined('UNIT_DEFAULT')) define('UNIT_DEFAULT', 'c');

$eco_tz = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC';
if (in_array($eco_tz, timezone_identifiers_list(), true)) {
    date_default_timezone_set($eco_tz);
}
unset($eco_tz);

if (!defined('DISPLAY_ERRORS')) {
    ini_set('display_errors', APP_DEBUG ? '1' : '0');
    ini_set('log_errors', '1');
    error_reporting(APP_DEBUG ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
}

/* Resolve BASE_URL from DOCUMENT_ROOT so it works in any XAMPP folder. */
if (!defined('BASE_URL')) {
    $eco_scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $eco_host   = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
    $eco_doc    = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) : '';
    $eco_app    = str_replace('\\', '/', APP_ROOT);
    $eco_rel    = '';
    if ($eco_doc !== '' && stripos($eco_app, $eco_doc) === 0) {
        $eco_rel = substr($eco_app, strlen($eco_doc));
    }
    $eco_rel = '/' . trim(str_replace(' ', '%20', $eco_rel), '/');
    define('BASE_URL', $eco_scheme . '://' . $eco_host . ($eco_rel === '/' ? '/' : $eco_rel . '/'));
    unset($eco_scheme, $eco_host, $eco_doc, $eco_app, $eco_rel);
}
