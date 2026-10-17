<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$ecoTitle = 'Settings';
$ecoNavKey = 'settings';
$ecoCrumb = 'System settings';
$ecoStyles = ['assets/css/app.css', 'assets/css/pages.css'];
$ecoScripts = ['assets/js/pages.js'];

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

    if ($action === 'profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');

        if ($name === '') $errors[] = 'Name required';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';

        if ($newPass !== '') {
            if ($currentPass === '') $errors[] = 'Current password required';
            $u = db_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
            if (!$u || !password_verify($currentPass, $u['password_hash'])) {
                $errors[] = 'Current password incorrect';
            } elseif (strlen($newPass) < 8) {
                $errors[] = 'New password must be at least 8 chars';
            }
        }

        if (empty($errors)) {
            if ($newPass !== '') {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                db_query('UPDATE users SET name=?, email=?, password_hash=? WHERE id=?', [$name, $email, $hash, $user['id']]);
            } else {
                db_query('UPDATE users SET name=?, email=? WHERE id=?', [$name, $email, $user['id']]);
            }
            flash('success', 'Profile updated');
            redirect('settings.php');
        }
    } elseif ($action === 'preferences') {
        $unit = strtolower(trim((string)($_POST['unit'] ?? 'c')));
        if ($unit !== 'f') $unit = 'c';
        setcookie('eco_unit', $unit, [
            'expires' => time() + 31536000,
            'path' => auth_url_path(),
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        db_query('INSERT INTO settings (`key`, `value`) VALUES ("unit", ?) ON DUPLICATE KEY UPDATE `value` = ?', [$unit, $unit]);
        flash('success', 'Preferences saved');
        redirect('settings.php');
    } elseif ($action === 'thresholds') {
        require_role('admin');
        $keys = ['temp_warn_high','temp_crit_high','temp_warn_low','humidity_warn_high','humidity_warn_low'];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) {
                $v = is_numeric($_POST[$k]) ? (string)$_POST[$k] : '0';
                db_query('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?', [$k, $v, $v]);
            }
        }
        flash('success', 'Thresholds updated');
        redirect('settings.php');
    } elseif ($action === 'retention') {
        require_role('admin');
        $days = (int)($_POST['retention_days'] ?? 30);
        if ($days < 0) $days = 0;
        db_query('INSERT INTO settings (`key`, `value`) VALUES ("retention_days", ?) ON DUPLICATE KEY UPDATE `value` = ?', [(string)$days, (string)$days]);
        flash('success', 'Retention updated');
        redirect('settings.php');
    } elseif ($action === 'delete_account') {
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        if ($confirm === 'DELETE MY ACCOUNT') {
            $uid = $user['id'];
            db_query('DELETE FROM users WHERE id = ?', [$uid]);
            auth_destroy_session();
            session_start();
            flash('info', 'Account deleted');
            redirect('login.php');
        } else {
            $errors[] = 'Confirmation text did not match';
        }
    }
}

foreach ($errors as $e) flash('error', $e);

$unitPref = preferred_unit();
$tempWarnHigh = (float)setting('temp_warn_high', TEMP_WARN_HIGH);
$tempCritHigh = (float)setting('temp_crit_high', TEMP_CRIT_HIGH);
$tempWarnLow = (float)setting('temp_warn_low', TEMP_WARN_LOW);
$humWarnHigh = (float)setting('humidity_warn_high', defined('HUMIDITY_WARN')?HUMIDITY_WARN:80);
$humWarnLow = (float)setting('humidity_warn_low', defined('HUMIDITY_LOW')?HUMIDITY_LOW:20);
$retDays = (int)setting('retention_days', defined('RETENTION_DAYS')?RETENTION_DAYS:30);

$ecoPageHasH1 = true;
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="main">
  <div class="page-head">
    <div class="page-head__title">
      <h1 class="h1">Settings</h1>
      <p class="lede">Manage profile, units, thresholds, and retention.</p>
    </div>
  </div>

  <div class="grid grid--2">
    <div class="card">
      <div class="card__head"><h3>Profile</h3></div>
      <form method="post" class="card__body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="field"><label for="profile_name">Name</label><input name="name" id="profile_name" value="<?= e($user['name'] ?? '') ?>" required></div>
        <div class="field"><label for="profile_email">Email</label><input type="email" name="email" id="profile_email" value="<?= e($user['email'] ?? '') ?>" required></div>
        <div class="field"><label for="profile_current_password">Current password</label><input type="password" name="current_password" id="profile_current_password"></div>
        <div class="field"><label for="profile_new_password">New password</label><input type="password" name="new_password" id="profile_new_password" placeholder="Leave blank to keep"></div>
        <div class="card__foot"><button class="btn btn--primary" type="submit">Save profile</button></div>
      </form>
    </div>

    <div class="card">
      <div class="card__head"><h3>Preferences</h3></div>
      <form method="post" class="card__body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="preferences">
        <div class="field">
          <label for="pref_unit">Units</label>
          <select name="unit" id="pref_unit">
            <option value="c" <?= $unitPref==='c'?'selected':'' ?>>°C</option>
            <option value="f" <?= $unitPref==='f'?'selected':'' ?>>°F</option>
          </select>
        </div>
        <div class="card__foot"><button class="btn btn--primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>

  <div class="grid grid--2">
    <div class="card">
      <div class="card__head"><h3>Global thresholds</h3></div>
      <form method="post" class="card__body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="thresholds">
        <div class="field__row">
          <div class="field"><label for="thr_temp_warn_high">Temp warn high (°C)</label><input type="number" step="0.1" name="temp_warn_high" id="thr_temp_warn_high" value="<?= e((string)$tempWarnHigh) ?>"></div>
          <div class="field"><label for="thr_temp_crit_high">Temp crit high (°C)</label><input type="number" step="0.1" name="temp_crit_high" id="thr_temp_crit_high" value="<?= e((string)$tempCritHigh) ?>"></div>
        </div>
        <div class="field__row">
          <div class="field"><label for="thr_temp_warn_low">Temp warn low (°C)</label><input type="number" step="0.1" name="temp_warn_low" id="thr_temp_warn_low" value="<?= e((string)$tempWarnLow) ?>"></div>
          <div class="field"><label for="thr_humidity_warn_high">Humidity warn high (%)</label><input type="number" step="0.1" name="humidity_warn_high" id="thr_humidity_warn_high" value="<?= e((string)$humWarnHigh) ?>"></div>
        </div>
        <div class="field__row">
          <div class="field"><label for="thr_humidity_warn_low">Humidity warn low (%)</label><input type="number" step="0.1" name="humidity_warn_low" id="thr_humidity_warn_low" value="<?= e((string)$humWarnLow) ?>"></div>
        </div>
        <div class="card__foot"><button class="btn btn--primary" type="submit">Save thresholds</button></div>
      </form>
    </div>

    <div class="card">
      <div class="card__head"><h3>Retention</h3></div>
      <form method="post" class="card__body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="retention">
        <div class="field"><label for="ret_days">Retention days</label><input type="number" name="retention_days" id="ret_days" value="<?= e((string)$retDays) ?>" min="0"></div>
        <div class="card__foot"><button class="btn btn--primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>

  <div class="card" style="border-color:rgba(248,113,113,0.3)">
    <div class="card__head"><h3>Danger zone</h3></div>
    <form method="post" class="card__body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_account">
      <p class="lede">Permanently delete your account. This cannot be undone.</p>
      <div class="field">
        <label for="delete_confirm">Type <strong>DELETE MY ACCOUNT</strong> to confirm</label>
        <input name="confirm" id="delete_confirm" placeholder="DELETE MY ACCOUNT">
      </div>
      <div class="card__foot"><button class="btn btn--danger" type="submit">Delete account</button></div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
