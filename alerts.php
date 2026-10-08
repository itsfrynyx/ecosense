<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$ecoTitle = 'Alerts';
$ecoNavKey = 'alerts';
$ecoCrumb = 'Alerting';
$ecoScripts = ['assets/js/pages.js'];
$ecoStyles = ['assets/css/app.css', 'assets/css/pages.css'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
    $ruleId = isset($_POST['rule_id']) ? (int)$_POST['rule_id'] : 0;
    $alertId = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;
    $deviceId = isset($_POST['device_id']) ? (int)$_POST['device_id'] : 0;
    $deviceAll = isset($_POST['device_all']) ? (int)$_POST['device_all'] : 0;
    $metric = trim((string)($_POST['metric'] ?? ''));
    $operator = trim((string)($_POST['operator'] ?? '>'));
    $threshold = (float)($_POST['threshold'] ?? 0);
    $severity = trim((string)($_POST['severity'] ?? 'warning'));
    $enabled = isset($_POST['enabled']) ? 1 : 0;

    if ($action === 'create' || $action === 'edit') {
        if ($metric === '') $errors[] = 'Metric required';
        if (!in_array($operator, ['>','<','>=','<='], true)) $operator = '>';
        if ($severity !== 'critical') $severity = 'warning';
        $devFk = ($deviceAll || $deviceId <= 0) ? null : $deviceId;
        if (empty($errors)) {
            if ($action === 'create') {
                db_query('INSERT INTO alert_rules (device_id, metric, operator, threshold, severity, enabled) VALUES (?, ?, ?, ?, ?, ?)', [$devFk, $metric, $operator, $threshold, $severity, $enabled]);
                flash('success', 'Rule created');
            } else {
                db_query('UPDATE alert_rules SET device_id=?, metric=?, operator=?, threshold=?, severity=?, enabled=? WHERE id=?', [$devFk, $metric, $operator, $threshold, $severity, $enabled, $ruleId]);
                flash('success', 'Rule updated');
            }
            redirect('alerts.php');
        }
    } elseif ($action === 'delete_rule' && $ruleId > 0) {
        db_query('DELETE FROM alert_rules WHERE id=?', [$ruleId]);
        flash('success', 'Rule deleted');
        redirect('alerts.php');
    } elseif ($action === 'ack' && $alertId > 0) {
        db_query('UPDATE alerts SET acknowledged_at = NOW() WHERE id=? AND acknowledged_at IS NULL', [$alertId]);
        flash('success', 'Alert acknowledged');
        redirect('alerts.php');
    }
}

foreach ($errors as $e) flash('error', $e);

$devices = db_all('SELECT id, device_uid, name FROM devices ORDER BY device_uid ASC');
$rules = db_all('SELECT ar.*, d.device_uid, d.name FROM alert_rules ar LEFT JOIN devices d ON d.id = ar.device_id ORDER BY ar.id DESC');
$alerts = db_all('SELECT a.*, d.device_uid, d.name AS device_name FROM alerts a LEFT JOIN devices d ON d.id = a.device_id ORDER BY a.triggered_at DESC, a.id DESC LIMIT 100');

$ecoPageHasH1 = true;
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="main">
  <div class="page-head">
    <div class="page-head__title">
      <h1 class="h1">Alerts</h1>
      <p class="lede">Create threshold rules and manage triggered alerts.</p>
    </div>
    <div class="page-head__actions">
      <button class="btn btn--primary" data-modal-open="modalRuleCreate"><?= icon('plus') ?> Add rule</button>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h3>Active rules</h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Device</th><th>Metric</th><th>Condition</th><th>Severity</th><th>Enabled</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rules as $r): ?>
          <tr>
            <td><?= $r['device_id'] ? e($r['device_uid'] . ' - ' . $r['name']) : 'All devices' ?></td>
            <td class="mono"><?= e($r['metric']) ?></td>
            <td class="mono"><?= e($r['operator']) ?> <?= e((string)$r['threshold']) ?></td>
            <td><?= status_badge($r['severity']) ?></td>
            <td><?= $r['enabled'] ? 'Yes' : 'No' ?></td>
            <td class="actions">
              <button class="btn btn--ghost btn--sm" data-edit-rule='<?= json_encode($r) ?>'>Edit</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete rule?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_rule">
                <input type="hidden" name="rule_id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn--ghost">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h3>Alert log</h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Time</th><th>Device</th><th>Metric</th><th>Value</th><th>Severity</th><th>Message</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($alerts as $a): ?>
          <tr>
            <td class="mono"><?= e($a['triggered_at']) ?></td>
            <td><?= $a['device_id'] ? e($a['device_uid'] . ' - ' . $a['device_name']) : '—' ?></td>
            <td class="mono"><?= e($a['metric']) ?></td>
            <td class="mono num"><?= $a['value']!==null ? e(number_format((float)$a['value'],2,'.','')) : '—' ?></td>
            <td><?= status_badge($a['severity']) ?></td>
            <td><?= e($a['message']) ?></td>
            <td><?= $a['acknowledged_at'] ? 'Acknowledged' : '<span class="badge badge--crit">Unread</span>' ?></td>
            <td>
              <?php if (!$a['acknowledged_at']): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="ack">
                <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn--primary btn--sm">Acknowledge</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<div class="modal" id="modalRuleCreate" hidden>
  <div class="modal__dialog">
    <div class="modal__head"><h3>Add rule</h3><button type="button" class="icon-btn" data-modal-close aria-label="Close dialog"><?= icon('close') ?></button></div>
    <form method="post" class="modal__body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label for="create_device_id">Device</label>
        <select name="device_id" id="create_device_id">
          <option value="0">All devices</option>
          <?php foreach ($devices as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['device_uid'].' - '.$d['name']) ?></option><?php endforeach; ?>
        </select>
        <label class="checkbox"><input type="checkbox" name="device_all" value="1"> Apply to all devices</label>
      </div>
      <div class="field__row">
        <div class="field"><label for="create_metric">Metric</label><select name="metric" id="create_metric"><option value="temperature_c">temperature_c</option><option value="humidity_pct">humidity_pct</option><option value="pressure_hpa">pressure_hpa</option><option value="lux">lux</option><option value="battery_pct">battery_pct</option></select></div>
        <div class="field"><label for="create_operator">Operator</label><select name="operator" id="create_operator"><option value=">">></option><option value="<"><</option><option value=">=">>=</option><option value="<="><=</option></select></div>
        <div class="field"><label for="create_threshold">Threshold</label><input type="number" step="0.01" name="threshold" id="create_threshold" required></div>
      </div>
      <div class="field__row">
        <div class="field"><label for="create_severity">Severity</label><select name="severity" id="create_severity"><option value="warning">warning</option><option value="critical">critical</option></select></div>
        <div class="field"><label class="checkbox"><input type="checkbox" name="enabled" value="1" checked> Enabled</label></div>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary">Create</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="modalRuleEdit" hidden>
  <div class="modal__dialog">
    <div class="modal__head"><h3>Edit rule</h3><button type="button" class="icon-btn" data-modal-close aria-label="Close dialog"><?= icon('close') ?></button></div>
    <form method="post" class="modal__body" id="formRuleEdit">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="rule_id" id="edit_rule_id">
      <div class="field">
        <label for="edit_r_device_id">Device</label>
        <select name="device_id" id="edit_r_device_id">
          <option value="0">All devices</option>
          <?php foreach ($devices as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['device_uid'].' - '.$d['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field__row">
        <div class="field"><label for="edit_r_metric">Metric</label><select name="metric" id="edit_r_metric"><option value="temperature_c">temperature_c</option><option value="humidity_pct">humidity_pct</option><option value="pressure_hpa">pressure_hpa</option><option value="lux">lux</option><option value="battery_pct">battery_pct</option></select></div>
        <div class="field"><label for="edit_r_operator">Operator</label><select name="operator" id="edit_r_operator"><option value=">">></option><option value="<"><</option><option value=">=">>=</option><option value="<="><=</option></select></div>
        <div class="field"><label for="edit_r_threshold">Threshold</label><input type="number" step="0.01" name="threshold" id="edit_r_threshold" required></div>
      </div>
      <div class="field__row">
        <div class="field"><label for="edit_r_severity">Severity</label><select name="severity" id="edit_r_severity"><option value="warning">warning</option><option value="critical">critical</option></select></div>
        <div class="field"><label class="checkbox"><input type="checkbox" name="enabled" id="edit_r_enabled" value="1"> Enabled</label></div>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary">Save</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>