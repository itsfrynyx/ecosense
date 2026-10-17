<?php
define('ECOSENSE_API', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/telemetry.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!db_available()) json_response(['ok' => false, 'error' => 'Database not available'], 503);
$deviceUid = isset($_GET['device_uid']) && is_string($_GET['device_uid']) ? trim($_GET['device_uid']) : '';
if ($deviceUid === '') json_response(['ok' => false, 'error' => 'device_uid required'], 400);
$device = db_one('SELECT id, device_uid FROM devices WHERE device_uid = ? LIMIT 1', [$deviceUid]);
if (!$device) json_response(['ok' => false, 'error' => 'Device not found'], 404);
try {
    $filters = telemetry_filters($_GET);
    $limit = max(0, min(10000, (int) ($_GET['limit'] ?? 0)));
    if ($limit === 1) $limit = 2;
    $data = telemetry_history((int) $device['id'], $deviceUid, $filters, $limit);
    if (db_error() !== '') json_response(['ok' => false, 'error' => 'History query failed'], 503);
    json_response($data);
} catch (InvalidArgumentException $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 422);
}
