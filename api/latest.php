<?php
define('ECOSENSE_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (!db_available()) json_response(['ok' => false, 'error' => 'Database not available'], 503);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$deviceUidFilter = isset($_GET['device_uid']) ? trim((string) $_GET['device_uid']) : '';

$devices = [];
if ($deviceUidFilter !== '') {
    $d = db_one('SELECT id, device_uid, name, location, status, last_seen_at FROM devices WHERE device_uid = ? LIMIT 1', [$deviceUidFilter]);
    if ($d) {
        $devices[] = $d;
    }
} else {
    $devices = db_all('SELECT id, device_uid, name, location, status, last_seen_at FROM devices ORDER BY device_uid ASC');
}

$resultDevices = [];
$units = preferred_unit();

foreach ($devices as $dev) {
    $devId = (int) $dev['id'];
    $lastSeen = $dev['last_seen_at'] ?? null;

    $status = (string) ($dev['status'] ?? '');
    if ($status !== 'online' && $status !== 'offline' && $status !== 'maintenance') {
        $status = device_status_from_last_seen($lastSeen);
    }
    if ($status !== 'maintenance') {
        $computed = device_status_from_last_seen($lastSeen);
        if ($computed === 'online') {
            $status = 'online';
        } else {
            $status = 'offline';
        }
    }

    $lastSeenAgo = null;
    if ($lastSeen) {
        $ts = strtotime((string) $lastSeen);
        if ($ts) {
            $lastSeenAgo = max(0, (int) (time() - $ts));
        }
    }

    $latest = db_one('SELECT temperature_c, humidity_pct, rain_raw, rain_detected, heat_index_c, signal_dbm, recorded_at FROM readings WHERE device_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1', [$devId]);

    $deviceData = [
        'device_uid'   => (string) $dev['device_uid'],
        'name'         => (string) $dev['name'],
        'location'     => $dev['location'] ?? '',
        'status'       => $status,
        'last_seen_at' => $lastSeen ?? null,
        'last_seen_ago' => $lastSeenAgo,
    ];

    if ($latest) {
        $deviceData['signal_dbm']  = $latest['signal_dbm'] !== null ? (int) $latest['signal_dbm'] : null;
        $deviceData['temperature_c'] = $latest['temperature_c'] !== null ? (float) $latest['temperature_c'] : null;
        $deviceData['humidity_pct']  = $latest['humidity_pct'] !== null ? (float) $latest['humidity_pct'] : null;
        $deviceData['rain_raw'] = $latest['rain_raw'] !== null ? (int) $latest['rain_raw'] : null;
        $deviceData['rain_detected'] = $latest['rain_detected'] !== null ? (int) $latest['rain_detected'] : null;
        $deviceData['heat_index_c']  = $latest['heat_index_c'] !== null ? (float) $latest['heat_index_c'] : null;
        $deviceData['last_seen_at']  = $latest['recorded_at'] ?? $lastSeen;
        if ($latest['recorded_at']) {
            $lts = strtotime((string) $latest['recorded_at']);
            if ($lts) {
                $deviceData['last_seen_ago'] = max(0, (int) (time() - $lts));
                $deviceData['last_seen_at']  = date('Y-m-d H:i:s', $lts);
            }
        }
        $deviceData['has_reading'] = true;

        $series = isset($_GET['series']) && $_GET['series'] === '0' ? [] : get_series_last60($devId);
        if (!empty($series['labels']) || !empty($series['temperature_c'])) {
            $deviceData['series'] = $series;
        }
    } else {
        $deviceData['has_reading'] = false;
    }

    $resultDevices[] = $deviceData;
}

if (db_error() !== '') json_response(['ok' => false, 'error' => 'Telemetry query failed. Run the database migration.'], 503);

echo json_encode([
    'ok'          => true,
    'server_time' => date('Y-m-d H:i:s'),
    'units'       => $units,
    'devices'     => $resultDevices,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function get_series_last60($devId)
{
    $rows = db_all('SELECT recorded_at, temperature_c, humidity_pct, rain_detected FROM readings WHERE device_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE) ORDER BY recorded_at ASC, id ASC', [$devId]);
    

    $labels = [];
    $temp = [];
    $hum = [];
    $pres = [];

    foreach ($rows as $r) {
        if (!$r['recorded_at']) {
            continue;
        }
        $ts = strtotime((string) $r['recorded_at']);
        if ($ts) {
            $labels[] = date('H:i', $ts);
        } else {
            $labels[] = substr((string) $r['recorded_at'], 11, 5);
        }
        $temp[] = $r['temperature_c'] !== null ? (float) $r['temperature_c'] : null;
        $hum[]  = $r['humidity_pct'] !== null ? (float) $r['humidity_pct'] : null;
        $pres[] = $r['rain_detected'] !== null ? (float) $r['rain_detected'] : null;
    }

    return [
        'labels'        => $labels,
        'temperature_c' => $temp,
        'humidity_pct'  => $hum,
        'rain_detected'  => $pres,
    ];
}
