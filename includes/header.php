<?php
/**
 * App shell: sidebar navigation + topbar.
 *
 * Pages may set before including:
 *   $ecoTitle, $ecoStyles[], $ecoScripts[], $ecoBodyClass, $ecoMeta[],
 *   $ecoNavKey (current page key), $ecoCrumb (breadcrumb leaf label)
 */

require_once __DIR__ . '/auth.php';

auth_start_session();
require_login();

$ecoTitle   = isset($ecoTitle) && $ecoTitle !== '' ? (string) $ecoTitle : 'Dashboard';
$ecoCrumb   = isset($ecoCrumb) ? (string) $ecoCrumb : '';
$ecoNavKey  = isset($ecoNavKey) && $ecoNavKey !== '' ? (string) $ecoNavKey : (string) ($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
$ecoNavKey  = pathinfo($ecoNavKey, PATHINFO_FILENAME);
if ($ecoCrumb === '') {
    $ecoCrumb = ucfirst(str_replace(['_', '-'], ' ', $ecoNavKey));
}
if (!isset($ecoStyles) || !is_array($ecoStyles) || !$ecoStyles) {
    $ecoStyles = ['assets/css/app.css'];
}

$ecoUser = current_user();

/* Cookie wins over the stored preference so the toggle is instant. */
$ecoUnitCookie = strtolower((string) ($_COOKIE['eco_unit'] ?? ''));
$ecoUnit = in_array($ecoUnitCookie, ['c', 'f'], true) ? $ecoUnitCookie : preferred_unit();

/* --- Shell data --------------------------------------------------------- */
$ecoDbUp     = db_available();
$ecoUnread   = 0;
$ecoActiveNb = 0;

if ($ecoDbUp) {
    try {
        $ecoUnread   = (int) db_val('SELECT COUNT(*) FROM alerts WHERE acknowledged_at IS NULL', [], 0);
        $ecoActiveNb = (int) db_val('SELECT COUNT(*) FROM devices WHERE status <> "offline"', [], 0);
    } catch (Throwable $ecoDbErr) {
        $ecoDbUp = false;
    }
}

$ecoNav = [
    ['key' => 'dashboard', 'href' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'dashboard',           'count' => 0],
    ['key' => 'devices',   'href' => 'devices.php',   'label' => 'Devices',   'icon' => 'devices',             'count' => $ecoActiveNb],
    ['key' => 'history',   'href' => 'history.php',   'label' => 'History',   'icon' => 'history',             'count' => 0],
    ['key' => 'alerts',    'href' => 'alerts.php',    'label' => 'Alerts',    'icon' => 'alert-triangle',     'count' => $ecoUnread, 'alert' => true],
    ['key' => 'settings',  'href' => 'settings.php',  'label' => 'Settings',  'icon' => 'settings',           'count' => 0],
];

/* Drop sections whose page has not been built yet, so the rail never 404s. */
$ecoNav = array_values(array_filter($ecoNav, function ($ecoItem) {
    return is_file(BASE_PATH . $ecoItem['href']);
}));

$ecoInitial = user_initial($ecoUser['name'] ?? '');
$ecoRole    = ucfirst((string) ($ecoUser['role'] ?? 'viewer'));

$ecoBodyClass = trim((string) (isset($ecoBodyClass) ? $ecoBodyClass : '') . ' is-app');

require __DIR__ . '/head.php';
?>
<script>window.ECOSENSE_BASE = <?= json_encode(rtrim((string) BASE_URL, '/') . '/') ?>;window.ECOSENSE_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<a class="skip-link" href="#main">Skip to main content</a>

<div class="shell" data-shell>

  <!-- ═══ Sidebar ═══════════════════════════════════════════════════ -->
  <aside class="sidebar" id="sidebar" data-sidebar aria-label="Primary">
    <div class="sidebar__head">
      <a class="brand" href="<?= e(url('dashboard.php')) ?>">
        <span class="brand__mark" aria-hidden="true"><?= icon('sensor-rings') ?></span>
        <span class="brand__text">
          <strong><?= e(APP_NAME) ?></strong>
          <small><?= e(APP_SHORT) ?></small>
        </span>
      </a>
      <button type="button" class="icon-btn sidebar__close" data-nav-close aria-label="Close navigation">
        <?= icon('close') ?>
      </button>
    </div>

    <nav class="nav-list" aria-label="Sections">
      <p class="nav-list__label">Monitor</p>
      <ul>
      <?php foreach ($ecoNav as $ecoItem):
          $ecoOn = active_nav($ecoItem['key'], $ecoNavKey) === 'active';
      ?>
        <li>
          <a class="nav-item<?= $ecoOn ? ' is-active' : '' ?>" href="<?= e(url($ecoItem['href'])) ?>"
             <?= $ecoOn ? ' aria-current="page"' : '' ?>
             <?= $ecoOn ? 'title="' . e($ecoItem['label']) . '"' : '' ?>>
            <span class="nav-item__icon" aria-hidden="true"><?= icon($ecoItem['icon']) ?></span>
            <span class="nav-item__label"><?= e($ecoItem['label']) ?></span>
            <?php if ((int) $ecoItem['count'] > 0): ?>
              <span class="nav-item__count<?= !empty($ecoItem['alert']) ? ' is-alert' : '' ?>"><?= e((string) (int) $ecoItem['count']) ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
      </ul>

      <p class="nav-list__label">System</p>
      <ul>
        <li>
          <a class="nav-item" href="<?= e(url('index.php')) ?>">
            <span class="nav-item__icon" aria-hidden="true"><?= icon('external') ?></span>
            <span class="nav-item__label">Project site</span>
          </a>
        </li>
      </ul>
    </nav>

    <div class="sidebar__foot">
      <div class="user-chip">
        <span class="avatar" aria-hidden="true"><?= e($ecoInitial) ?></span>
        <span class="user-chip__meta">
          <strong><?= e($ecoUser['name'] ?? 'Guest') ?></strong>
          <small><?= e($ecoRole) ?></small>
        </span>
      </div>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="user-chip__out">
        <?= csrf_field() ?>
        <button type="submit" class="icon-btn" aria-label="Sign out of EcoSense">
          <?= icon('logout') ?>
        </button>
      </form>
    </div>
  </aside>

  <div class="scrim" data-scrim hidden></div>

  <!-- ═══ Main column ═════════════════════════════════════════════════ -->
  <div class="shell__main">

    <header class="topbar">
      <button type="button" class="icon-btn topbar__burger" data-nav-open aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
        <?= icon('menu') ?>
      </button>

      <div class="topbar__title">
        <nav class="crumbs" aria-label="Breadcrumb">
          <ol>
            <li><a href="<?= e(url('dashboard.php')) ?>">Console</a></li>
            <li class="crumbs__sep" aria-hidden="true"><?= icon('chevron-right', 'ico--xs') ?></li>
            <li><span aria-current="page"><?= e($ecoCrumb) ?></span></li>
          </ol>
        </nav>
        <?php if (empty($ecoPageHasH1)): ?>
        <h1><?= e($ecoTitle) ?></h1>
        <?php endif; ?>
      </div>

      <div class="topbar__tools">
        <p class="clock">
          <span class="clock__time mono" data-clock>--:--:--</span>
          <span class="clock__tz"><?= e(date('T')) ?></span>
        </p>

        <span class="live-chip<?= $ecoDbUp ? '' : ' is-off' ?>" data-live-state role="status">
          <span class="live-dot" aria-hidden="true"></span>
          <span class="live-chip__text" data-live-text><?= $ecoDbUp ? 'LIVE' : 'OFFLINE' ?></span>
        </span>

        <div class="unit-toggle" data-unit-toggle>
          <span class="visually-hidden" id="unitToggleLabel">Temperature unit</span>
          <button type="button" class="unit-toggle__btn<?= $ecoUnit === 'c' ? ' is-on' : '' ?>" data-unit-set="c" aria-pressed="<?= $ecoUnit === 'c' ? 'true' : 'false' ?>">°C</button>
          <button type="button" class="unit-toggle__btn<?= $ecoUnit === 'f' ? ' is-on' : '' ?>" data-unit-set="f" aria-pressed="<?= $ecoUnit === 'f' ? 'true' : 'false' ?>">°F</button>
        </div>

        <?php $ecoAlertsPage = is_file(BASE_PATH . 'alerts.php') ? url('alerts.php') : url('dashboard.php') . '#alerts'; ?>
        <a class="icon-btn icon-btn--badged" href="<?= e($ecoAlertsPage) ?>"
           aria-label="Notifications<?= $ecoUnread > 0 ? ', ' . (int) $ecoUnread . ' unread' : ', none unread' ?>">
          <?= icon('bell') ?>
          <?php if ($ecoUnread > 0): ?>
            <span class="badge-dot" aria-hidden="true"><?= $ecoUnread > 99 ? '99+' : e((string) $ecoUnread) ?></span>
          <?php endif; ?>
        </a>
      </div>
    </header>

    <?php require __DIR__ . '/flash.php'; ?>