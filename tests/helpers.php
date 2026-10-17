<?php
define('OFFLINE_MODE', true);
require_once __DIR__ . '/../includes/telemetry.php';
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$now = strtotime('2026-10-17 12:00:00');
check(time_ago($now - 120, $now) === '2m ago', 'Last seen must use the reading timestamp');
check(time_ago($now - 7200, $now) === '2h ago', 'Last seen must show elapsed hours');
check(compute_heat_index(28, null) === null, 'Missing humidity must not produce a heat index');
check(abs(compute_heat_index(30, 70) - 35.04) < 0.05, 'Rothfusz heat index must not apply a second conversion');
$filters = telemetry_filters(['range' => '6h', 'to' => '2026-10-17 12:00:00']);
check($filters['from'] === '2026-10-17 06:00:00', 'Range must be anchored to To');
foreach ([['from' => '2026-02-30 12:00:00'], ['range' => 'invalid'], ['from' => '2026-10-18 00:00:00', 'to' => '2026-10-17 00:00:00']] as $bad) {
    $rejected = false;
    try { telemetry_filters($bad); } catch (InvalidArgumentException $error) { $rejected = true; }
    check($rejected, 'Invalid date range must be rejected');
}
$_COOKIE['eco_unit'] = 'f';
check(preferred_unit() === 'f', 'Browser temperature preference must survive polling');
echo "PASS: timestamps, heat index, missing humidity, date validation and unit preferences.\n";
