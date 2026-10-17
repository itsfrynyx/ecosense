<?php
/**
 * EcoSense device ingest endpoint.
 * Accepts JSON or form-encoded from ESP8266 / ESP32 DHT22 and rain nodes. No human session required.
 */

define('ECOSENSE_INGEST', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function ingest_json_error($code, $message, $extra = [])
{
    http_response_code((int) $code);
    $payload = ['ok' => false, 'error' => (string) $message];
    if (is_array($extra) && !empty($extra)) {
        foreach ($extra as $key => $value) {
            if (!array_key_exists($key, $payload)) {
                $payload[$key] = $value;
            }
        }
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ingest_success($readingId, $alertsTriggered)
{
    $serverTime = date('Y-m-d H:i:s');
    echo json_encode([
        'ok'              => true,
        'reading_id'      => (int) $readingId,
        'alerts_triggered' => (int) $alertsTriggered,
        'server_time'     => $serverTime,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    ingest_json_error(405, 'Method not allowed');
}
if (!db_available()) ingest_json_error(503, 'Database not available');

$inputRaw = file_get_contents('php://input');
$data = [];

if ($inputRaw !== '' && $inputRaw !== false) {
    $trimmed = trim($inputRaw);
    if ($trimmed !== '') {
        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) ingest_json_error(400, 'Invalid JSON payload');
        $data = $decoded;
    }
}

if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

if (isset($data['device_uid']) && !is_string($data['device_uid'])) ingest_json_error(422, 'device_uid must be text');
$deviceUid = isset($data['device_uid']) ? trim($data['device_uid']) : '';
if ($deviceUid === '') {
    if (isset($_GET['device_uid'])) {
        $deviceUid = trim((string) $_GET['device_uid']);
    }
}

$apiKey = '';
if (isset($_SERVER['HTTP_X_DEVICE_KEY'])) {
    $apiKey = trim((string) $_SERVER['HTTP_X_DEVICE_KEY']);
}
if ($apiKey === '' && isset($data['api_key'])) {
    $apiKey = trim((string) $data['api_key']);
}
if ($apiKey === '' && isset($_GET['api_key'])) {
    $apiKey = trim((string) $_GET['api_key']);
}

if ($deviceUid === '') {
    ingest_json_error(400, 'device_uid is required');
}
if ($apiKey === '') {
    ingest_json_error(401, 'API key missing');
}

$device = db_one('SELECT id, device_uid, api_key, status FROM devices WHERE device_uid = ? LIMIT 1', [$deviceUid]);
if (!$device) {
    ingest_json_error(401, 'Invalid device credentials');
}

if (!hash_equals((string) $device['api_key'], $apiKey)) {
    ingest_json_error(401, 'Invalid device credentials');
}

$deviceId = (int) $device['id'];
$now = time();

$recent = db_one(
    'SELECT COUNT(*) AS c FROM readings WHERE device_id = ? AND recorded_at >= ?',
    [$deviceId, date('Y-m-d H:i:s', $now - 60)]
);
if ($recent && (int) $recent['c'] >= (int) INGEST_RATE_LIMIT) {
    ingest_json_error(429, 'Rate limit exceeded', ['limit_per_minute' => (int) INGEST_RATE_LIMIT, 'retry_after' => 60]);
}

function ingest_pick(array $data, array $keys)
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && $data[$key] !== '') {
            return $data[$key];
        }
    }
    return null;
}

$errors = [];
$values = [];
$ranges = [
    'temperature_c' => [['temperature_c', 'temperature', 'temp'], -40, 80, 2],
    'humidity_pct'  => [['humidity_pct', 'humidity'], 0, 100, 2],
    'signal_dbm'    => [['signal_dbm', 'signal'], -120, 0, 0],
];

foreach ($ranges as $field => $rule) {
    list($aliases, $min, $max, $precision) = $rule;
    $raw = ingest_pick($data, $aliases);
    if ($raw === null) {
        $values[$field] = null;
        continue;
    }
    if (!is_numeric($raw)) {
        $errors[$field] = 'Expected a number';
        $values[$field] = null;
        continue;
    }
    $num = (float) $raw;
    if (!is_finite($num) || $num < $min || $num > $max) {
        $errors[$field] = 'Out of range (' . $min . ' to ' . $max . ')';
        $values[$field] = null;
        continue;
    }
    $values[$field] = $precision === 0 ? (int) round($num) : round($num, $precision);
}

if ($errors) {
    ingest_json_error(422, 'Validation failed', ['errors' => $errors]);
}

$rainRaw = ingest_pick($data, ['rain_raw', 'rain_value']);
if ($rainRaw !== null && (!is_numeric($rainRaw) || (float)$rainRaw < 0 || (float)$rainRaw > 1023 || (float)(int)$rainRaw != (float)$rainRaw)) {
    ingest_json_error(422, 'rain_raw must be an integer between 0 and 1023');
}
$rainRaw = $rainRaw === null ? null : (int)$rainRaw;
$rainDetected = ingest_pick($data, ['rain_detected']);
if ($rainDetected !== null && !in_array($rainDetected, [0, 1, '0', '1', false, true], true)) {
    ingest_json_error(422, 'rain_detected must be 0 (dry) or 1 (wet)');
}
$rainDetected = $rainDetected === null ? null : (int) $rainDetected;

$temperatureC = $values['temperature_c'];
$humidityPct  = $values['humidity_pct'];
$signalDbm    = $values['signal_dbm'];

$heatIndexC = compute_heat_index($temperatureC, $humidityPct);
if ($heatIndexC !== null) {
    $heatIndexC = round((float) $heatIndexC, 2);
}

$recordedAt = date('Y-m-d H:i:s');
$pdo = db();
if ($pdo === null) {
    ingest_json_error(503, 'Database not available');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO readings (device_id, recorded_at, temperature_c, humidity_pct, rain_raw, rain_detected, heat_index_c, signal_dbm) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $deviceId,
        $recordedAt,
        $temperatureC,
        $humidityPct,
        $rainRaw,
        $rainDetected,
        $heatIndexC,
        $signalDbm,
    ]);
    $readingId = (int) $pdo->lastInsertId();

    $updateStmt = $pdo->prepare('UPDATE devices SET last_seen_at = ?, status = ? WHERE id = ?');
    $updateStmt->execute([$recordedAt, $device['status'] === 'maintenance' ? 'maintenance' : 'online', $deviceId]);

    $alertsTriggered = 0;
    $rules = db_all('SELECT id, device_id, metric, operator, threshold, severity, enabled FROM alert_rules WHERE enabled = 1 AND (device_id IS NULL OR device_id = ?) ORDER BY id', [$deviceId]);

    foreach ($rules as $rule) {
        $metric = (string) ($rule['metric'] ?? '');
        $value = null;
        switch ($metric) {
            case 'temperature_c':
                $value = $temperatureC;
                break;
            case 'rain_detected':
                $value = $rainDetected;
                break;
            case 'humidity_pct':
                $value = $humidityPct;
                break;
        }

        if ($value === null || !is_numeric($value)) {
            continue;
        }

        $threshold = (float) ($rule['threshold'] ?? 0);
        $triggered = false;
        $op = (string) ($rule['operator'] ?? '>');

        switch ($op) {
            case '>':
                $triggered = ($value > $threshold);
                break;
            case '>=':
                $triggered = ($value >= $threshold);
                break;
            case '<':
                $triggered = ($value < $threshold);
                break;
            case '<=':
                $triggered = ($value <= $threshold);
                break;
        }

        if ($triggered) {
            $severity = (string) ($rule['severity'] ?? 'warning');
            if ($severity !== 'critical') {
                $severity = 'warning';
            }
            $msg = ucfirst($metric) . ' ' . $op . ' ' . $threshold . ' (' . round($value, 2) . ')';
            $alertStmt = $pdo->prepare('INSERT INTO alerts (rule_id, device_id, metric, value, severity, message, triggered_at, acknowledged_at) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)');
            $alertStmt->execute([
                $rule['id'],
                $deviceId,
                $metric,
                round($value, 2),
                $severity,
                $msg,
                $recordedAt,
            ]);
            $alertsTriggered++;
        }
    }

    if (db_error() !== '') throw new RuntimeException('Alert query failed');
    $pdo->commit();
    ingest_success($readingId, $alertsTriggered);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ingest_json_error(500, 'Database error');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ingest_json_error(500, 'Server error');
}
