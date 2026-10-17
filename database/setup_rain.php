<?php
/** Run with PHP CLI to enable rain telemetry without replacing existing data. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo = db();
    if (!$pdo) {
        throw new RuntimeException(db_error());
    }
    if (!$pdo->query("SHOW COLUMNS FROM readings LIKE 'rain_raw'")->fetch()) {
        $pdo->exec('ALTER TABLE readings ADD COLUMN rain_raw SMALLINT UNSIGNED NULL AFTER humidity_pct');
        echo "Added rain_raw column.\n";
    }
    if (!$pdo->query("SHOW COLUMNS FROM readings LIKE 'rain_detected'")->fetch()) {
        $pdo->exec('ALTER TABLE readings ADD COLUMN rain_detected TINYINT UNSIGNED NULL AFTER rain_raw');
        echo "Added rain_detected column.\n";
    }
    $stmt = $pdo->prepare('SELECT id FROM devices WHERE device_uid = ?');
    $stmt->execute(['ECO-ESP8266-01']);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare('INSERT INTO devices (device_uid, name, location, api_key, firmware, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute(['ECO-ESP8266-01', 'EcoSense NodeMCU', 'Sensor Station', bin2hex(random_bytes(32)), 'esp8266/1.0', 'offline']);
        echo "Registered ECO-ESP8266-01. Copy its API key from Devices into the ESP8266 sketch.\n";
    }
    $pdo->beginTransaction();
    $pdo->exec("UPDATE alert_rules SET enabled = 0 WHERE metric NOT IN ('temperature_c', 'humidity_pct', 'rain_detected')");
    $pdo->exec("INSERT INTO settings (`key`, `value`) VALUES ('telemetry_interval_s', '5') ON DUPLICATE KEY UPDATE `value` = '5'");
    $pdo->exec("INSERT INTO alert_rules (device_id, metric, operator, threshold, severity, enabled)
        SELECT NULL, 'rain_detected', '>=', 1, 'warning', 1
        WHERE NOT EXISTS (SELECT 1 FROM alert_rules WHERE metric = 'rain_detected')");
    $pdo->commit();
    echo "Rain telemetry setup ready.\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
