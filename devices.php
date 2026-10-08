<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$ecoTitle = 'Devices';
$ecoNavKey = 'devices';
$ecoCrumb = 'Fleet management';
$ecoScripts = ['assets/js/pages.js'];
$ecoStyles = ['assets/css/app.css', 'assets/css/pages.css'];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
    $deviceId = isset($_POST['device_id']) ? (int)$_POST['device_id'] : 0;
    $name = trim((string)($_POST['name'] ?? ''));
    $location = trim((string)($_POST['location'] ?? ''));
    $deviceUid = trim((string)($_POST['device_uid'] ?? ''));
    $firmware = trim((string)($_POST['firmware'] ?? 'ecosense-node/1.0.2'));
    $apiKey = trim((string)($_POST['api_key'] ?? ''));

    if ($action === 'create' || $action === 'edit') {
        if ($name === '') $errors[] = 'Device name is required';
        if ($deviceUid === '') $errors[] = 'Device UID is required';
        if ($action === 'create' && $apiKey === '') {
            $apiKey = bin2hex(random_bytes(32));
        }
        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = db_query('INSERT INTO devices (device_uid, name, location, api_key, firmware, status) VALUES (?, ?, ?, ?, ?, ?)', [$deviceUid, $name, $location, $apiKey, $firmware, 'offline']);
                if ($stmt !== false) {
                    flash('success', 'Device registered successfully');
                    redirect('devices.php');
                } else {
                    $errors[] = 'Failed to create device';
                }
            } else {
                if ($deviceId > 0) {
                    $params = [$deviceUid, $name, $location, $firmware, $deviceId];
                    $sql = 'UPDATE devices SET device_uid=?, name=?, location=?, firmware=? WHERE id=?';
                    if ($apiKey !== '') {
                        $sql = 'UPDATE devices SET device_uid=?, name=?, location=?, firmware=?, api_key=? WHERE id=?';
                        $params = [$deviceUid, $name, $location, $firmware, $apiKey, $deviceId];
                    }
                    $stmt = db_query($sql, $params);
                    if ($stmt !== false) {
                        flash('success', 'Device updated');
                        redirect('devices.php');
                    } else {
                        $errors[] = 'Failed to update device';
                    }
                }
            }
        }
    } elseif ($action === 'delete' && $deviceId > 0) {
        db_query('DELETE FROM devices WHERE id = ?', [$deviceId]);
        flash('success', 'Device deleted');
        redirect('devices.php');
    } elseif ($action === 'rotate' && $deviceId > 0) {
        $newKey = bin2hex(random_bytes(32));
        db_query('UPDATE devices SET api_key = ? WHERE id = ?', [$newKey, $deviceId]);
        flash('success', 'API key rotated');
        redirect('devices.php');
    }
}

$devices = db_all('SELECT * FROM devices ORDER BY device_uid ASC');
$hasErrors = !empty($errors);
foreach ($errors as $e) {
    flash('error', $e);
}

$ecoPageHasH1 = true;
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="main">
  <div class="page-head">
    <div class="page-head__title">
      <h1 class="h1">Devices</h1>
      <p class="lede">Register and manage sensor nodes. Copy ingest URL and curl snippet to test.</p>
    </div>
    <div class="page-head__actions">
      <button type="button" class="btn btn--primary" data-modal-open="modalDeviceCreate"><?= icon('plus') ?> Register device</button>
      <button type="button" class="btn btn--ghost" data-simulate><?= icon('zap') ?> Send test reading</button>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>UID</th>
            <th>Name</th>
            <th>Location</th>
            <th>Status</th>
            <th>Last seen</th>
            <th>Battery</th>
            <th>Signal</th>
            <th>Temp (°C)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$devices): ?>
          <tr><td colspan="9">No devices registered. Use Register device to add your sensor node.</td></tr>
        <?php endif; ?>
        <?php foreach ($devices as $dev): 
          $devId = (int)$dev['id'];
          $lastSeen = $dev['last_seen_at'] ?? null;
          $status = device_status_from_last_seen($lastSeen);
          if ($dev['status'] === 'maintenance') $status = 'maintenance';
          $latest = db_one('SELECT temperature_c, battery_pct, signal_dbm, recorded_at FROM readings WHERE device_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1', [$devId]);
          $temp = $latest['temperature_c'] ?? null;
          $batt = $latest['battery_pct'] ?? null;
          $sig = $latest['signal_dbm'] ?? null;
          $ls = $latest['recorded_at'] ?? $lastSeen;
        ?>
          <tr>
            <td class="mono wrap"><?= e($dev['device_uid']) ?></td>
            <td><?= e($dev['name']) ?></td>
            <td><?= e($dev['location'] ?? '') ?></td>
            <td><?= status_badge($status) ?></td>
            <td class="mono"><?= e($ls ?? '') ?></td>
            <td class="mono num"><?= $batt !== null ? e((string)$batt) . '%' : '—' ?></td>
            <td class="mono num"><?= $sig !== null ? e((string)$sig) : '—' ?></td>
            <td class="mono num"><?= $temp !== null ? e(number_format((float)$temp,1,'.','')) : '—' ?></td>
            <td class="actions">
              <button class="btn btn--ghost btn--sm" data-edit-device='<?= json_encode(['id'=>$devId,'device_uid'=>$dev['device_uid'],'name'=>$dev['name'],'location'=>$dev['location'],'firmware'=>$dev['firmware'],'api_key'=>$dev['api_key']]) ?>'>Edit</button>
              <button class="btn btn--ghost btn--sm" data-rotate-device="<?= $devId ?>">Rotate key</button>
              <button class="btn btn--ghost btn--sm" data-copy-snippet='<?= e(json_encode(['device_uid'=>$dev['device_uid'],'api_key'=>$dev['api_key']])) ?>'>View API key / snippet</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete device?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="device_id" value="<?= $devId ?>">
                <button type="submit" class="btn btn--ghost">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<div class="modal" id="modalDeviceCreate" hidden>
  <div class="modal__dialog">
    <div class="modal__head">
      <h3>Register device</h3>
      <button type="button" class="icon-btn" data-modal-close aria-label="Close dialog"><?= icon('close') ?></button>
    </div>
    <form method="post" class="modal__body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label for="create_device_uid">Device UID</label>
        <input name="device_uid" id="create_device_uid" value="ECO-TH-<?= date('m') ?><?= rand(10,99) ?>" required>
      </div>
      <div class="field">
        <label for="create_name">Name</label>
        <input name="name" id="create_name" required>
      </div>
      <div class="field">
        <label for="create_location">Location</label>
        <input name="location" id="create_location">
      </div>
      <div class="field">
        <label for="create_firmware">Firmware</label>
        <input name="firmware" id="create_firmware" value="ecosense-node/1.0.2">
      </div>
      <div class="field">
        <label for="create_api_key">API Key (auto-generated if empty)</label>
        <input name="api_key" id="create_api_key" placeholder="Leave blank to auto-generate">
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary">Create</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="modalDeviceEdit" hidden>
  <div class="modal__dialog">
    <div class="modal__head">
      <h3>Edit device</h3>
      <button type="button" class="icon-btn" data-modal-close aria-label="Close dialog"><?= icon('close') ?></button>
    </div>
    <form method="post" class="modal__body" id="formEdit">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="device_id" id="edit_device_id">
      <div class="field">
        <label for="edit_device_uid">Device UID</label>
        <input name="device_uid" id="edit_device_uid" required>
      </div>
      <div class="field">
        <label for="edit_name">Name</label>
        <input name="name" id="edit_name" required>
      </div>
      <div class="field">
        <label for="edit_location">Location</label>
        <input name="location" id="edit_location">
      </div>
      <div class="field">
        <label for="edit_firmware">Firmware</label>
        <input name="firmware" id="edit_firmware">
      </div>
      <div class="field">
        <label for="edit_api_key">API Key (leave empty to keep current)</label>
        <input name="api_key" id="edit_api_key" placeholder="Keep existing">
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="modalSnippet" hidden>
  <div class="modal__dialog">
    <div class="modal__head">
      <h3>Ingest curl snippet</h3>
      <button type="button" class="icon-btn" data-modal-close aria-label="Close dialog"><?= icon('close') ?></button>
    </div>
    <div class="modal__body">
      <div class="field">
        <label>Ingest URL</label>
        <div class="copy-chip" id="snippetUrl"></div>
      </div>
      <div class="field">
        <label for="snippetApiKey">Device API key (copy into DEVICE_API_KEY)</label>
        <input id="snippetApiKey" class="input" readonly onclick="this.select()" autocomplete="off" spellcheck="false">
      </div>
      <div class="field">
        <label>curl command</label>
        <pre id="snippetCurl" style="white-space:pre-wrap;overflow-wrap:anywhere;background:var(--bg-inset);padding:10px;border:1px solid var(--line-hair);border-radius:8px;font-family:monospace"></pre>
      </div>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn--ghost" data-modal-close>Close</button>
      <button type="button" class="btn btn--primary" data-copy-curl>Copy curl</button>
    </div>
  </div>
</div>

<form id="rotateForm" method="post" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="rotate">
  <input type="hidden" name="device_id" id="rotate_device_id">
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
