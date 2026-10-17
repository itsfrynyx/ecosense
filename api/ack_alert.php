<?php
define('ECOSENSE_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

ensure_session();
require_login();
require_role(['admin', 'operator']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
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

$alertId = isset($data['alert_id']) ? (int) $data['alert_id'] : 0;
if ($alertId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'alert_id required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();
if ($pdo === null) {
    echo json_encode(['ok' => false, 'error' => 'Database not available'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE alerts SET acknowledged_at = NOW() WHERE id = ? AND acknowledged_at IS NULL');
    $stmt->execute([$alertId]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
