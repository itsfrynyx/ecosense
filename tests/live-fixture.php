<?php
/** CLI-only temporary records for tests/live.cjs. Never uses existing devices. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/functions.php';
$pdo = db();
if (!$pdo) { fwrite(STDERR, db_error()); exit(1); }
$mode = $argv[1] ?? '';
$input = json_decode(stream_get_contents(STDIN), true) ?: [];
if ($mode === 'create') {
    $suffix = bin2hex(random_bytes(6));
    $uid = 'TEST-' . $suffix;
    $password = bin2hex(random_bytes(12));
    $key = bin2hex(random_bytes(32));
    $emails = ['admin' => 'test-admin-' . $suffix . '@example.invalid', 'viewer' => 'test-viewer-' . $suffix . '@example.invalid'];
    $pdo->beginTransaction();
    try {
        foreach ($emails as $role => $email) {
            $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute(['Test user', $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        }
        $pdo->prepare('INSERT INTO devices (device_uid, name, location, api_key, firmware, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$uid, "O'Brien test node", 'Temporary test', $key, 'test/1', 'offline']);
        $id = (int) $pdo->lastInsertId();
        $early = date('Y-m-d H:i:s', time() - 7200);
        $recent = date('Y-m-d H:i:s', time() - 600);
        $stmt = $pdo->prepare('INSERT INTO readings (device_id, recorded_at, temperature_c, humidity_pct, rain_detected) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$id, $early, 22, 44, 0]);
        $stmt->execute([$id, $recent, 27, 60, 1]);
        $pdo->prepare('INSERT INTO alert_rules (device_id, metric, operator, threshold, severity) VALUES (?, ?, ?, ?, ?)')->execute([$id, 'rain_detected', '>=', 1, 'warning']);
        $pdo->commit();
        echo json_encode(compact('uid', 'key', 'password', 'emails', 'early', 'recent'));
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
} else {
    $uid = $input['uid'] ?? '';
    if (!preg_match('/^TEST-([a-f0-9]{12})$/', $uid, $matches)) throw new RuntimeException('Invalid test fixture');
    if ($mode === 'cleanup') {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM devices WHERE device_uid = ?')->execute([$uid]);
        foreach (['admin', 'viewer'] as $role) {
            $pdo->prepare('DELETE FROM users WHERE email = ?')->execute(['test-' . $role . '-' . $matches[1] . '@example.invalid']);
        }
        $pdo->commit();
        echo 'Temporary records removed';
    } elseif ($mode === 'inspect') {
        echo json_encode(db_one('SELECT r.* FROM readings r JOIN devices d ON d.id = r.device_id WHERE d.device_uid = ? ORDER BY r.id DESC LIMIT 1', [$uid]));
    }
}
