<?php
define('ECOSENSE_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

ensure_session();
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = file_get_contents('php://input');
$data = [];
if ($input) {
    $decoded = json_decode(trim($input), true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

if (!verify_csrf(isset($data['csrf_token']) ? $data['csrf_token'] : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$deviceUid = isset($data['device_uid']) ? trim((string) $data['device_uid']) : '';
if ($deviceUid === '') {
    $deviceUid = isset($_GET['device_uid']) ? trim((string) $_GET['device_uid']) : '';
}

$device = null;
if ($deviceUid !== '') {
    $device = db_one('SELECT id, device_uid FROM devices WHERE device_uid = ? LIMIT 1', [$deviceUid]);
}
if (!$device) {
    $device = db_one('SELECT id, device_uid FROM devices ORDER BY device_uid ASC LIMIT 1');
}
if (!$device) {
    echo json_encode(['ok' => false, 'error' => 'No devices found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$devId = (int) $device['id'];
$hour = (int) date('H');
$min = (int) date('i');
$second = (int) date('s');
$tod = ($hour * 60 + $min + $second / 60.0) / (24.0 * 60.0) * 2 * pi();

$offset = ($devId % 4) * 0.8;
$temp = 24.0 + 4.0 * sin($tod) + $offset + (rand(-5, 5) / 10.0);
$hum = 60.0 + 15.0 * cos($tod) + ($offset * 2) + (rand(-8, 8) / 10.0);
$pres = 1010.0 + 3.0 * sin($tod + 0.5) + (rand(-10, 10) / 100.0);
$lux = 8000.0 + 6000.0 * max(0, sin($tod)) + (rand(-2000, 2000));
$batt = max(0, min(100, 85 + rand(-3, 3)));
$signal = max(-120, min(0, -55 + rand(-5, 5)));

$temp = round(max(-60, min(85, $temp)), 2);
$hum = round(max(0, min(100, $hum)), 2);
$pres = round(max(300, min(1200, $pres)), 2);
$lux = round(max(0, min(200000, $lux)), 2);
$heat = compute_heat_index($temp, $hum);
if ($heat !== null) {
    $heat = round((float) $heat, 2);
}

$recordedAt = date('Y-m-d H:i:s');
$pdo = db();
if ($pdo === null) {
    echo json_encode(['ok' => false, 'error' => 'Database not available'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO readings (device_id, recorded_at, temperature_c, humidity_pct, pressure_hpa, heat_index_c, lux, battery_pct, signal_dbm) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$devId, $recordedAt, $temp, $hum, $pres, $heat, $lux, $batt, $signal]);
    $readingId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE devices SET last_seen_at = ?, status = ? WHERE id = ?')->execute([$recordedAt, 'online', $devId]);

    echo json_encode(['ok' => true, 'reading_id' => $readingId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error'], JSON_UNESCAPED_UNICODE);
}