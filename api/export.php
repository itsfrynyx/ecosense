<?php
define('ECOSENSE_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

ensure_session();
require_login();

$deviceUid = isset($_GET['device_uid']) ? trim((string) $_GET['device_uid']) : '';
$from = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
$to   = isset($_GET['to']) ? trim((string) $_GET['to']) : '';

$device = null;
if ($deviceUid !== '') {
    $device = db_one('SELECT id, device_uid FROM devices WHERE device_uid = ? LIMIT 1', [$deviceUid]);
}

$filename = 'ecosense_readings_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');
fputcsv($output, ['timestamp', 'device_uid', 'temperature_c', 'humidity_pct', 'pressure_hpa', 'heat_index_c', 'lux', 'battery_pct', 'signal_dbm']);

$sql = 'SELECT d.device_uid, r.recorded_at, r.temperature_c, r.humidity_pct, r.pressure_hpa, r.heat_index_c, r.lux, r.battery_pct, r.signal_dbm FROM readings r JOIN devices d ON d.id = r.device_id WHERE 1=1';
$params = [];

if ($device) {
    $sql .= ' AND r.device_id = ?';
    $params[] = (int) $device['id'];
}
if ($from !== '') {
    $sql .= ' AND r.recorded_at >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $sql .= ' AND r.recorded_at <= ?';
    $params[] = $to;
}
$sql .= ' ORDER BY r.recorded_at ASC, r.id ASC LIMIT 100000';

$rows = db_all($sql, $params);
foreach ($rows as $r) {
    fputcsv($output, [
        $r['recorded_at'] ?? '',
        $r['device_uid'] ?? ($deviceUid ?? ''),
        $r['temperature_c'] ?? '',
        $r['humidity_pct'] ?? '',
        $r['pressure_hpa'] ?? '',
        $r['heat_index_c'] ?? '',
        $r['lux'] ?? '',
        $r['battery_pct'] ?? '',
        $r['signal_dbm'] ?? '',
    ]);
}

fclose($output);
exit;