<?php
/**
 * Shared helpers. Requires config.php and db.php; safe to include anywhere.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/icons.php';

function e($value)
{
    if ($value === null || is_array($value) || is_object($value)) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function h($number, $decimals = 0)
{
    if ($number === null || $number === '' || !is_numeric($number)) {
        return '—';
    }
    return number_format((float) $number, (int) $decimals, '.', ',');
}

function url($path = '')
{
    $path = (string) $path;
    if ($path === '') {
        return BASE_URL;
    }
    if (preg_match('#^(?:https?:)?//#i', $path) || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)) {
        return $path;
    }
    // A return URL from REQUEST_URI already includes the app directory.
    if ($path[0] === '/') {
        $base = parse_url(BASE_URL);
        $origin = $base['scheme'] . '://' . $base['host'];
        if (isset($base['port'])) {
            $origin .= ':' . $base['port'];
        }
        return $origin . $path;
    }
    return BASE_URL . ltrim($path, '/');
}

function asset($path)
{
    return url(ltrim((string) $path, '/'));
}

function redirect($path)
{
    if (!headers_sent()) {
        $target = url((string) $path);
        header('Location: ' . $target, true, 302);
    }
    exit;
}

function ensure_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (function_exists('auth_start_session')) {
        auth_start_session();
        return;
    }
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
}

function flash($type, $message)
{
    ensure_session();
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => (string) $type, 'message' => (string) $message];
}

function get_flashes()
{
    ensure_session();
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($all) ? $all : [];
}

function has_flashes()
{
    ensure_session();
    return !empty($_SESSION['flash']);
}

function page_title($suffix = null)
{
    $base = defined('APP_NAME') ? APP_NAME : 'EcoSense';
    if ($suffix === null || trim((string) $suffix) === '') {
        $tag = defined('APP_TAGLINE') ? APP_TAGLINE : '';
        return $base . ' — ' . $tag;
    }
    return trim((string) $suffix) . ' · ' . $base;
}

function active_nav($key, $current = null)
{
    if ($current === null) {
        $current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $current = pathinfo($current, PATHINFO_FILENAME);
    }
    $key = strtolower((string) $key);
    $current = strtolower((string) $current);
    return $key === $current ? 'active' : '';
}

function time_ago($datetime, $now = null)
{
    if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
    if (!$ts) {
        return '—';
    }
    $diff = ($now === null ? time() : (int) $now) - $ts;
    $future = $diff < 0;
    $diff   = abs($diff);

    if ($diff < 10) {
        return 'just now';
    }
    if ($diff < 60)     return $diff . 's ago';
    if ($diff < 3600)   return intdiv($diff, 60) . 'm ago';
    if ($diff < 86400)  return intdiv($diff, 3600) . 'h ago';
    if ($diff < 2592000) return intdiv($diff, 86400) . 'd ago';

    return date('M j, Y', $ts) . ($future ? ' (ahead)' : '');
}

function device_status_from_last_seen($lastSeen, $window = null)
{
    $window = $window === null ? (defined('DEVICE_ONLINE_WINDOW') ? DEVICE_ONLINE_WINDOW : 60) : (int) $window;
    if ($lastSeen === null || $lastSeen === '') {
        return 'offline';
    }
    $ts = is_numeric($lastSeen) ? (int) $lastSeen : strtotime((string) $lastSeen);
    if (!$ts) {
        return 'offline';
    }
    return (time() - $ts) <= $window ? 'online' : 'offline';
}

function status_badge($status)
{
    $status = strtolower(trim((string) $status));
    $map = [
        'online'      => ['label' => 'Online',      'mod' => 'good'],
        'ok'          => ['label' => 'Online',      'mod' => 'good'],
        'warning'     => ['label' => 'Warning',     'mod' => 'warn'],
        'warn'        => ['label' => 'Warning',     'mod' => 'warn'],
        'critical'    => ['label' => 'Critical',    'mod' => 'crit'],
        'offline'     => ['label' => 'Offline',     'mod' => 'crit'],
        'maintenance' => ['label' => 'Maintenance', 'mod' => 'info'],
        'unknown'     => ['label' => 'Unknown',     'mod' => 'idle'],
    ];
    $entry = $map[$status] ?? ['label' => ucfirst($status ?: 'unknown'), 'mod' => 'idle'];

    return '<span class="badge badge--' . $entry['mod'] . '"><span class="badge__dot" aria-hidden="true"></span>'
        . e($entry['label']) . '</span>';
}

function preferred_unit()
{
    $cookie = $_COOKIE['eco_unit'] ?? null;
    if (is_string($cookie) && in_array($cookie, ['c', 'f'], true)) return $cookie;
    $unit = strtolower((string) setting('unit', defined('UNIT_DEFAULT') ? UNIT_DEFAULT : 'c'));
    return $unit === 'f' ? 'f' : 'c';
}

function unit_symbol($unit = null)
{
    $unit = $unit === null ? preferred_unit() : strtolower((string) $unit);
    return $unit === 'f' ? '°F' : '°C';
}

function to_fahrenheit($celsius)
{
    return ((float) $celsius * 9 / 5) + 32;
}

function format_temp($celsius, $unit = null, $decimals = 1)
{
    if ($celsius === null || !is_numeric($celsius)) {
        return '—';
    }
    $unit = $unit === null ? preferred_unit() : strtolower((string) $unit);
    $value = $unit === 'f' ? to_fahrenheit($celsius) : (float) $celsius;
    return number_format($value, (int) $decimals, '.', '');
}

function unit_temp($celsius, $unit = null, $decimals = 1)
{
    if ($celsius === null || !is_numeric($celsius)) {
        return '—';
    }
    return format_temp($celsius, $unit, $decimals) . ' ' . unit_symbol($unit);
}

/** NOAA heat index (Steadman/Rothfusz), Celsius in / Celsius out. */
function compute_heat_index($tempC, $rhPct)
{
    if (!is_numeric($tempC) || !is_numeric($rhPct)) {
        return null;
    }
    $t  = (float) $tempC;
    $rh = (float) $rhPct;

    if ($rh < 40 || $t < 26) {
        return round($t, 2);
    }

    $f  = ($t * 9 / 5) + 32;
    $simple = 0.5 * ($f + 61 + (($f - 68) * 1.2) + ($rh * 0.094));
    $avg    = ($simple + $f) / 2;

    if ($avg >= 80) {
        $hi = -42.379
            + 2.04901523 * $f
            + 10.14333127 * $rh
            - 0.22475541 * $f * $rh
            - 0.00683783 * $f * $f
            - 0.05481717 * $rh * $rh
            + 0.00122874 * $f * $f * $rh
            + 0.00085282 * $f * $rh * $rh
            - 0.00000199 * $f * $f * $rh * $rh;
    } elseif ($simple < 80) {
        $hi = $simple;
    } else {
        $hi = $avg;
    }

    return round(($hi - 32) * 5 / 9, 2);
}

function moving_average($values, $window = 5)
{
    $values = is_array($values) ? array_values(array_filter($values, 'is_numeric')) : [];
    $window = max(1, (int) $window);
    if (!$values) {
        return [];
    }
    $out = [];
    foreach ($values as $i => $value) {
        $start = max(0, $i - $window + 1);
        $slice = array_slice($values, $start, $i - $start + 1);
        $out[$i] = round(array_sum($slice) / count($slice), 3);
    }
    return $out;
}

/** Latest reading row per device, keyed by device id. Empty array when DB is down. */
function latest_readings($deviceIds = null)
{
    if (!db_available()) {
        return [];
    }

    $sql = 'SELECT d.id AS device_id, d.device_uid, d.name, d.name AS device_name, d.status, d.location,
                   d.last_seen_at, d.firmware,
                   r.recorded_at, r.temperature_c, r.humidity_pct, r.rain_raw, r.rain_detected,
                   r.heat_index_c, r.signal_dbm
            FROM devices d
            LEFT JOIN readings r ON r.id = (
                SELECT id FROM readings WHERE device_id = d.id ORDER BY recorded_at DESC, id DESC LIMIT 1
            )';
    $params = [];
    if ($deviceIds !== null) {
        $ids = array_values(array_filter(array_map('intval', (array) $deviceIds)));
        if (!$ids) {
            return [];
        }
        $sql .= ' WHERE d.id IN (' . implode(',', $ids) . ')';
    }
    $sql .= ' ORDER BY d.id';

    $rows = db_all($sql, $params);
    $out  = [];
    foreach ($rows as $row) {
        $out[(int) $row['device_id']] = $row;
    }
    return $out;
}

/** Chart-ready series for one device. */
function reading_series($deviceId, $hours = 24, $limit = 500)
{
    if (!db_available()) {
        return [];
    }
    $hours = max(1, (int) $hours);
    $rows  = db_all(
        'SELECT recorded_at, temperature_c, humidity_pct, rain_detected
         FROM readings
         WHERE device_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR)
         ORDER BY recorded_at ASC
         LIMIT ' . max(1, (int) $limit),
        [$deviceId]
    );
    return array_map('reading_json', $rows);
}

function reading_json($row)
{
    $row = is_array($row) ? $row : [];
    $ts  = !empty($row['recorded_at']) ? strtotime((string) $row['recorded_at']) : false;

    return [
        't'          => $ts === false ? 0 : $ts,
        'label'      => $ts === false ? '' : date('M j H:i', $ts),
        'temperature' => isset($row['temperature_c']) ? (float) $row['temperature_c'] : null,
        'humidity'   => isset($row['humidity_pct']) ? (float) $row['humidity_pct'] : null,
        'rain'   => isset($row['rain_detected']) ? (float) $row['rain_detected'] : null,
        'heat'       => isset($row['heat_index_c']) ? (float) $row['heat_index_c'] : null,
        'signal'     => isset($row['signal_dbm']) ? (int) $row['signal_dbm'] : null,
    ];
}

/** Trimmed, tag-free request value — always pass through e() before printing. */
function param($key, $default = '')
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim(strip_tags((string) $value));
}

function is_post()
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}
