<?php
define('ECOSENSE_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$deviceUid = isset($_GET['device_uid']) ? trim((string) $_GET['device_uid']) : '';
$range     = isset($_GET['range']) ? trim((string) $_GET['range']) : '24h';
$limit     = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;

if ($deviceUid === '') {
    echo json_encode(['ok' => false, 'error' => 'device_uid required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$device = db_one('SELECT id, device_uid FROM devices WHERE device_uid = ? LIMIT 1', [$deviceUid]);
if (!$device) {
    echo json_encode(['ok' => false, 'error' => 'Device not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$devId = (int) $device['id'];
$now = date('Y-m-d H:i:s');
$from = null;
$labelInterval = 'HOUR';
$labelFormat = 'H:i';

switch ($range) {
    case '1h':
        $from = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $labelFormat = 'H:i';
        break;
    case '6h':
        $from = date('Y-m-d H:i:s', strtotime('-6 hours'));
        $labelFormat = 'H:i';
        break;
    case '7d':
        $from = date('Y-m-d H:i:s', strtotime('-7 days'));
        $labelFormat = 'Y-m-d';
        break;
    case '24h':
    default:
        $from = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $labelFormat = 'H:i';
        break;
}

$sql = 'SELECT recorded_at, temperature_c, humidity_pct, pressure_hpa, heat_index_c, lux FROM readings WHERE device_id = ? AND recorded_at >= ? ORDER BY recorded_at ASC, id ASC';
$params = [$devId, $from];
if ($limit > 0) {
    $sql .= ' LIMIT ' . $limit;
}

$rows = db_all($sql, $params);
if (empty($rows) && $limit <= 0) {
    $rows = db_all('SELECT recorded_at, temperature_c, humidity_pct, pressure_hpa, heat_index_c, lux FROM readings WHERE device_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 500', [$devId]);
    if (!empty($rows)) {
        $rows = array_reverse($rows);
    }
}

$labels = [];
$temp = [];
$hum = [];
$pres = [];
$lux = [];
$heat = [];

foreach ($rows as $r) {
    if (!$r['recorded_at']) {
        continue;
    }
    $ts = strtotime((string) $r['recorded_at']);
    if ($ts) {
        $labels[] = date($labelFormat, $ts);
    } else {
        $labels[] = substr((string) $r['recorded_at'], 11, 5);
    }
    $temp[] = $r['temperature_c'] !== null ? (float) $r['temperature_c'] : null;
    $hum[]  = $r['humidity_pct'] !== null ? (float) $r['humidity_pct'] : null;
    $pres[] = $r['pressure_hpa'] !== null ? (float) $r['pressure_hpa'] : null;
    $lux[]  = $r['lux'] !== null ? (float) $r['lux'] : null;
    $heat[] = $r['heat_index_c'] !== null ? (float) $r['heat_index_c'] : null;
}

$stats = [
    'temperature_c' => stats_for($temp),
    'humidity_pct'  => stats_for($hum),
    'pressure_hpa'  => stats_for($pres),
];

$series = [
    [
        'key'   => 'temperature_c',
        'label' => 'Temperature',
        'unit'  => '°C',
        'color' => '#22D3EE',
        'values' => $temp,
    ],
    [
        'key'   => 'humidity_pct',
        'label' => 'Humidity',
        'unit'  => '%',
        'color' => '#34D399',
        'values' => $hum,
    ],
    [
        'key'   => 'pressure_hpa',
        'label' => 'Pressure',
        'unit'  => 'hPa',
        'color' => '#A78BFA',
        'values' => $pres,
    ],
];

echo json_encode([
    'ok'         => true,
    'device_uid' => $deviceUid,
    'range'      => $range,
    'labels'     => $labels,
    'series'     => $series,
    'stats'      => $stats,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function stats_for($vals)
{
    $clean = [];
    foreach ($vals as $v) {
        if ($v !== null && is_numeric($v)) {
            $clean[] = (float) $v;
        }
    }
    if (empty($clean)) {
        return ['min' => null, 'avg' => null, 'max' => null];
    }
    $count = count($clean);
    $min = min($clean);
    $max = max($clean);
    $avg = array_sum($clean) / $count;
    return [
        'min' => round($min, 2),
        'avg' => round($avg, 2),
        'max' => round($max, 2),
    ];
}