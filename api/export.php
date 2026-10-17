<?php
define('ECOSENSE_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/telemetry.php';
require_login();
if (!db_available()) json_response(['ok' => false, 'error' => 'Database not available'], 503);
$deviceUid = isset($_GET['device_uid']) && is_string($_GET['device_uid']) ? trim($_GET['device_uid']) : '';
$device = null;
if ($deviceUid !== '') {
    $device = db_one('SELECT id, device_uid FROM devices WHERE device_uid = ? LIMIT 1', [$deviceUid]);
    if (!$device) json_response(['ok' => false, 'error' => 'Device not found'], 404);
}
try {
    $filters = telemetry_filters($_GET);
} catch (InvalidArgumentException $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 422);
}
$rows = telemetry_rows($device ? (int) $device['id'] : null, $filters);
if (db_error() !== '') json_response(['ok' => false, 'error' => 'Export query failed'], 503);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ecosense_readings_' . date('Ymd_His') . '.csv"');
header('Cache-Control: no-store');
$output = fopen('php://output', 'w');
fputcsv($output, ['timestamp', 'device_uid', 'temperature_c', 'humidity_pct', 'rain_detected', 'heat_index_c', 'signal_dbm']);
foreach ($rows as $row) {
    $uid = (string) $row['device_uid'];
    if (preg_match('/^[=+@\t\r-]/', $uid)) $uid = "'" . $uid;
    fputcsv($output, [$row['recorded_at'], $uid, $row['temperature_c'], $row['humidity_pct'], $row['rain_detected'], $row['heat_index_c'], $row['signal_dbm']]);
}
fclose($output);
