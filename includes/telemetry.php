<?php
/** Shared history queries for the HTML page, API and CSV export. */
require_once __DIR__ . '/functions.php';

function telemetry_filters(array $input)
{
    $range = isset($input['range']) && is_string($input['range']) ? $input['range'] : '24h';
    $durations = ['1h' => 3600, '6h' => 21600, '24h' => 86400, '7d' => 604800];
    if (!isset($durations[$range])) {
        throw new InvalidArgumentException('Choose a valid range: 1h, 6h, 24h or 7d');
    }
    $dates = [];
    foreach (['from', 'to'] as $key) {
        $raw = $input[$key] ?? '';
        if (!is_string($raw)) {
            throw new InvalidArgumentException('Invalid ' . $key . ' date');
        }
        $raw = trim($raw);
        if ($raw === '') { $dates[$key] = null; continue; }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $raw);
        if (!$date || $date->format('Y-m-d H:i:s') !== $raw) {
            throw new InvalidArgumentException('Use YYYY-MM-DD HH:MM:SS for ' . $key);
        }
        $dates[$key] = $date;
    }
    $to = $dates['to'] ?? new DateTimeImmutable();
    $from = $dates['from'] ?? $to->modify('-' . $durations[$range] . ' seconds');
    if ($from > $to) {
        throw new InvalidArgumentException('From must be earlier than or equal to To');
    }
    return ['range' => $range, 'from' => $from->format('Y-m-d H:i:s'), 'to' => $to->format('Y-m-d H:i:s')];
}

function telemetry_rows($deviceId, array $filters)
{
    $sql = 'SELECT r.recorded_at, r.temperature_c, r.humidity_pct, r.rain_detected,
                   r.heat_index_c, r.signal_dbm, d.device_uid
            FROM readings r JOIN devices d ON d.id = r.device_id
            WHERE r.recorded_at >= ? AND r.recorded_at <= ?';
    $params = [$filters['from'], $filters['to']];
    if ($deviceId !== null) {
        $sql .= ' AND r.device_id = ?';
        $params[] = (int) $deviceId;
    }
    return db_all($sql . ' ORDER BY r.recorded_at ASC, r.id ASC', $params);
}

function telemetry_stats(array $values)
{
    $values = array_values(array_filter($values, 'is_numeric'));
    if (!$values) return ['min' => null, 'avg' => null, 'max' => null];
    return ['min' => round(min($values), 2), 'avg' => round(array_sum($values) / count($values), 2), 'max' => round(max($values), 2)];
}

function telemetry_history($deviceId, $deviceUid, array $filters, $limit = 0)
{
    $rows = telemetry_rows($deviceId, $filters);
    $values = ['temperature_c' => [], 'humidity_pct' => [], 'rain_detected' => []];
    $labels = $timestamps = [];
    foreach ($rows as $row) {
        $labels[] = $row['recorded_at'];
        $timestamps[] = $row['recorded_at'];
        foreach ($values as $key => $_) {
            $values[$key][] = $row[$key] === null ? null : (float) $row[$key];
        }
    }
    $stats = array_map('telemetry_stats', $values);
    // Sample across the entire requested period, including both endpoints.
    $count = count($labels);
    if ($limit >= 2 && $count > $limit) {
        $indices = [];
        for ($i = 0; $i < $limit; $i++) $indices[] = (int) round($i * ($count - 1) / ($limit - 1));
        $pick = function ($items) use ($indices) { return array_map(function ($i) use ($items) { return $items[$i]; }, $indices); };
        $labels = $pick($labels);
        $timestamps = $pick($timestamps);
        $values = array_map($pick, $values);
    }
    $series = [
        ['key' => 'temperature_c', 'label' => 'Temperature', 'unit' => '°C', 'color' => '#22D3EE', 'values' => $values['temperature_c']],
        ['key' => 'humidity_pct', 'label' => 'Humidity', 'unit' => '%', 'color' => '#34D399', 'values' => $values['humidity_pct']],
        ['key' => 'rain_detected', 'label' => 'Rain detection', 'unit' => '0=dry, 1=wet', 'color' => '#A78BFA', 'stepped' => true, 'fill' => false, 'decimals' => 0, 'values' => $values['rain_detected']],
    ];
    return ['ok' => true, 'device_uid' => $deviceUid, 'range' => $filters['range'], 'from' => $filters['from'], 'to' => $filters['to'], 'labels' => $labels, 'timestamps' => $timestamps, 'series' => $series, 'stats' => $stats, 'total' => $count];
}
