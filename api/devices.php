<?php
define('ECOSENSE_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

ensure_session();
require_login();
if (!db_available()) json_response(['ok' => false, 'error' => 'Database not available'], 503);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$devices = db_all('SELECT * FROM devices ORDER BY device_uid ASC');

$result = [];
foreach ($devices as $dev) {
    $devId = (int) $dev['id'];
    $lastSeen = $dev['last_seen_at'] ?? null;
    $status = device_status_from_last_seen($lastSeen);
    if ($dev['status'] === 'maintenance') {
        $status = 'maintenance';
    } elseif ($status === 'online') {
        $status = 'online';
    } else {
        $status = 'offline';
    }

    $latest = db_one('SELECT temperature_c, humidity_pct, rain_detected, heat_index_c, signal_dbm, recorded_at FROM readings WHERE device_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1', [$devId]);

    $min24 = db_val('SELECT MIN(temperature_c) FROM readings WHERE device_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)', [$devId]);
    $max24 = db_val('SELECT MAX(temperature_c) FROM readings WHERE device_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)', [$devId]);
    $avg24 = db_val('SELECT AVG(temperature_c) FROM readings WHERE device_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)', [$devId]);

    $item = [
        'id'           => $devId,
        'device_uid'   => (string) $dev['device_uid'],
        'name'         => (string) $dev['name'],
        'location'     => $dev['location'] ?? '',
        'firmware'     => (string) $dev['firmware'],
        'status'       => $status,
        'last_seen_at' => $lastSeen,
        'created_at'   => $dev['created_at'] ?? null,
    ];
    if (user_has_role('admin')) $item['api_key'] = (string) $dev['api_key'];

    if ($latest) {
        $item['latest'] = [
            'temperature_c' => $latest['temperature_c'] !== null ? (float) $latest['temperature_c'] : null,
            'humidity_pct'  => $latest['humidity_pct'] !== null ? (float) $latest['humidity_pct'] : null,
            'rain_detected' => $latest['rain_detected'] === null ? null : (int) $latest['rain_detected'],
            'heat_index_c'  => $latest['heat_index_c'] !== null ? (float) $latest['heat_index_c'] : null,
            'signal_dbm'    => $latest['signal_dbm'] !== null ? (int) $latest['signal_dbm'] : null,
            'recorded_at'   => $latest['recorded_at'],
        ];
    }

    $item['stats_24h'] = [
        'temp_min' => $min24 !== null ? round((float) $min24, 2) : null,
        'temp_max' => $max24 !== null ? round((float) $max24, 2) : null,
        'temp_avg' => $avg24 !== null ? round((float) $avg24, 2) : null,
    ];

    $result[] = $item;
}

echo json_encode(['ok' => true, 'devices' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
