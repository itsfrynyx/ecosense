<?php
/**
 * Split-screen sign in: animated brand panel with a live waveform
 * on the left, credentials form on the right.
 */

require_once __DIR__ . '/includes/auth.php';

auth_start_session();

if (is_logged_in()) {
    redirect('dashboard.php');
}

$ecoErrors = [];
$ecoEmail  = '';
$ecoReturn = '';
$ecoLocked = false;

/* Accept a staged destination from the query string or the session. */
if (isset($_GET['return']) && is_scalar($_GET['return'])) {
    $ecoReturn = (string) $_GET['return'];
}
if ($ecoReturn === '' && !empty($_SESSION['eco_return']) && is_string($_SESSION['eco_return'])) {
    $ecoReturn = (string) $_SESSION['eco_return'];
}
if (!is_safe_return($ecoReturn)) {
    $ecoReturn = '';
}
unset($_SESSION['eco_intended'], $_SESSION['eco_return']);

if (is_post()) {
    if (!verify_csrf()) {
        $ecoErrors['form'] = 'Your session expired before the form was submitted. Please try again.';
    } else {
        $ecoEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
        $ecoPass  = (string) ($_POST['password'] ?? '');

        $posted = (string) ($_POST['return_to'] ?? '');
        if ($posted !== '' && is_safe_return($posted)) {
            $ecoReturn = $posted;
        }

        if ($ecoEmail === '') {
            $ecoErrors['email'] = 'Enter the email address on your account.';
        } elseif (!filter_var($ecoEmail, FILTER_VALIDATE_EMAIL)) {
            $ecoErrors['email'] = 'That does not look like a valid email address.';
        } elseif ($ecoPass === '') {
            $ecoErrors['password'] = 'Enter your password.';
        } else {
            $ecoResult = attempt_login($ecoEmail, $ecoPass);
            if (!empty($ecoResult['ok'])) {
                flash('success', 'Signed in as ' . (string) $ecoResult['user']['name'] . '.');
                redirect($ecoReturn !== '' ? $ecoReturn : 'dashboard.php');
            }
            $ecoLocked = !empty($ecoResult['locked']);
            $ecoErrors['form'] = (string) $ecoResult['message'];
        }
    }
}

$ecoFeatures = [
    ['icon' => 'activity',       'title' => '5 second telemetry',      'copy' => 'DHT22 temperature and humidity plus digital rain status are sent to MySQL every five seconds.'],
    ['icon' => 'shield',         'title' => 'Role-based access',      'copy' => 'Admin, operator and viewer roles keep fleet edits behind an admin check.'],
    ['icon' => 'alert-triangle', 'title' => 'Threshold alerting',     'copy' => 'Warning and critical rules watch temperature, humidity and rain detection.'],
    ['icon' => 'download',       'title' => 'History and CSV export', 'copy' => 'Thirty days of readings, aggregatable by minute, hour or day.'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0B0E13">
<title><?= e(page_title('Sign in')) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230B0E13'/%3E%3Cg fill='none' stroke='%2322D3EE' stroke-width='1.8'%3E%3Ccircle cx='16' cy='16' r='11'/%3E%3Ccircle cx='16' cy='16' r='5.4'/%3E%3C/g%3E%3Ccircle cx='16' cy='16' r='1.8' fill='%2322D3EE'/%3E%3C/svg%3E" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@500;700&display=swap" media="print" onload="this.media='all'">
<noscript>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@500;700&display=swap">
</noscript>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="auth auth--login">

<a class="skip-link" href="#authForm">Skip to the sign-in form</a>
<a class="auth-back" href="<?= e(url('index.php')) ?>"><?= icon('arrow-left', 'ico--sm') ?> Project site</a>

<div class="auth-split">

  <!-- ═══ Brand panel ═══════════════════════════════════════════════ -->
  <section class="auth-brand">
    <div>
      <a class="brand" href="<?= e(url('index.php')) ?>">
        <span class="brand__mark" aria-hidden="true"><?= icon('sensor-rings') ?></span>
        <span><?= e(APP_NAME) ?></span>
      </a>
      <p class="eyebrow" style="margin-top:26px">
        <span class="live-dot" aria-hidden="true"></span> Telemetry console
      </p>
      <h1 class="auth-brand__title">Sign in to the<br><em>instrument panel</em>.</h1>
      <p class="auth-brand__sub">
        ESP8266 nodes stream temperature, humidity and rain detection over Wi-Fi into a
        PHP ingest API. This console turns that stream into live traces, threshold alerts
        and fleet health.
      </p>
    </div>

    <div class="wave" data-wave>
      <canvas data-wave-canvas role="img" aria-label="Animated live sensor waveform"></canvas>
      <dl class="wave__stats">
        <div class="wave__stat">
          <dt>Cadence</dt>
          <dd><?= e((string) TELEMETRY_INTERVAL) ?>s</dd>
        </div>
        <div class="wave__stat">
          <dt>Metrics</dt>
          <dd>6</dd>
        </div>
        <div class="wave__stat">
          <dt>Retention</dt>
          <dd><?= e((string) RETENTION_DAYS) ?>d</dd>
        </div>
      </dl>
    </div>

    <ul class="feat-list">
      <?php foreach ($ecoFeatures as $ecoF): ?>
        <li>
          <span class="feat-list__ico" aria-hidden="true"><?= icon($ecoF['icon']) ?></span>
          <div>
            <b><?= e($ecoF['title']) ?></b>
            <span><?= e($ecoF['copy']) ?></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

    <p class="auth-foot">
      <span class="auth-foot__status"><span class="live-dot" aria-hidden="true"></span>All systems nominal</span>
      <span>ESP8266 &rarr; PHP &rarr; MySQL</span>
      <span class="mono">v<?= e(APP_VERSION) ?></span>
    </p>
  </section>

  <!-- ═══ Form panel ════════════════════════════════════════════════ -->
  <section class="auth-panel">
    <div class="auth-card">
      <div class="auth-card__head">
        <h2 class="auth-card__title">Sign in</h2>
        <p class="auth-card__sub">Access the live monitoring console for your sensor fleet.</p>
      </div>

      <?php foreach (get_flashes() as $ecoF): ?>
        <div class="notice notice--info" role="status" style="margin-bottom:18px">
          <span class="notice__ico" aria-hidden="true"><?= icon('info') ?></span>
          <div><p class="notice__body"><?= e((string) ($ecoF['message'] ?? '')) ?></p></div>
        </div>
      <?php endforeach; ?>

      <?php if (isset($ecoErrors['form'])): ?>
        <div class="notice notice--<?= $ecoLocked ? 'crit' : 'info' ?>" role="alert" style="margin-bottom:18px">
          <span class="notice__ico" aria-hidden="true"><?= icon($ecoLocked ? 'lock' : 'alert-triangle') ?></span>
          <div>
            <p class="notice__title"><?= $ecoLocked ? 'Locked out' : 'Sign in failed' ?></p>
            <p class="notice__body"><?= e($ecoErrors['form']) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <form class="auth-form" id="authForm" method="post" action="<?= e(url('login.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= e($ecoReturn) ?>">

        <div class="field">
          <label class="field__label" for="email"><?= icon('user') ?> Email address</label>
          <input class="input" type="email" id="email" name="email" autocomplete="username"
                 inputmode="email" spellcheck="false" placeholder="you@example.com"
                 value="<?= e($ecoEmail) ?>" <?= isset($ecoErrors['email']) ? 'aria-invalid="true"' : '' ?>
                 required>
          <?php if (isset($ecoErrors['email'])): ?>
            <p class="field__err"><?= icon('alert-triangle', 'ico--sm') ?><?= e($ecoErrors['email']) ?></p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="field__label" for="password"><?= icon('lock') ?> Password</label>
          <div class="pass-field">
            <input class="input" type="password" id="password" name="password"
                   autocomplete="current-password" placeholder="••••••••"
                   <?= isset($ecoErrors['password']) ? 'aria-invalid="true"' : '' ?> required>
            <button type="button" class="pass-field__toggle" data-show-pass aria-label="Show password" aria-pressed="false">
              <?= icon('eye') ?>
            </button>
          </div>
          <?php if (isset($ecoErrors['password'])): ?>
            <p class="field__err"><?= icon('alert-triangle', 'ico--sm') ?><?= e($ecoErrors['password']) ?></p>
          <?php endif; ?>
        </div>

        <div class="auth-row">
          <label class="check" for="remember">
            <input type="checkbox" id="remember" name="remember" value="1" checked>
            <span>Keep me signed in</span>
          </label>
        </div>

        <button class="btn btn--primary btn--block" type="submit">
          <?= icon('login', 'ico--sm') ?> Sign in
        </button>
      </form>

      <div class="demo-card">
        <p class="demo-card__title"><?= icon('key', 'ico--sm') ?> Demo account</p>
        <div class="demo-card__body">
          <p>Seeded for evaluation — the admin role reaches every page.</p>
          <dl>
            <dt>Email</dt>
            <dd id="demoEmail">demo@ecosense.local</dd>
            <dt>Password</dt>
            <dd id="demoPass">Demo@12345</dd>
          </dl>
          <button type="button" class="demo-card__use" data-demo-fill>Fill demo credentials</button>
        </div>
      </div>

      <p class="auth-alt">
        No account yet? <a href="<?= e(url('register.php')) ?>">Create one</a>
      </p>
    </div>
  </section>
</div>

<script>
/* Brand waveform + password reveal + demo fill. Frozen under reduced motion. */
(function () {
  'use strict';

  var toggle = document.querySelector('[data-show-pass]');
  var input = document.getElementById('password');
  if (toggle && input) {
    toggle.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
      toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      input.focus();
    });
  }

  var fill = document.querySelector('[data-demo-fill]');
  var emailField = document.getElementById('email');
  if (fill && emailField && input) {
    fill.addEventListener('click', function () {
      emailField.value = document.getElementById('demoEmail').textContent;
      input.value = document.getElementById('demoPass').textContent;
      input.focus();
    });
  }

  var host = document.querySelector('[data-wave]');
  if (!host) { return; }
  var canvas = host.querySelector('[data-wave-canvas]');
  if (!canvas || !canvas.getContext) { return; }

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var ctx = canvas.getContext('2d');
  var w = 0, h = 0, dpr = 1, raf = 0;

  function size() {
    var rect = canvas.getBoundingClientRect();
    w = Math.max(1, Math.round(rect.width));
    h = Math.max(1, Math.round(rect.height - 42));
    dpr = Math.min(window.devicePixelRatio || 1, 2.5);
    canvas.width = Math.round(w * dpr);
    canvas.height = Math.round(h * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function draw(t) {
    ctx.clearRect(0, 0, w, h);
    var mid = h / 2;
    var amp = h * 0.30;
    ctx.beginPath();
    for (var x = 0; x <= w; x += 2) {
      var p = x / w;
      var y = mid
        + Math.sin(p * 12 + t / 320) * amp
        + Math.sin(p * 29 - t / 190) * amp * 0.32
        + Math.sin(p * 3.1 + t / 700) * amp * 0.5;
      if (x === 0) { ctx.moveTo(x, y); } else { ctx.lineTo(x, y); }
    }
    ctx.strokeStyle = 'rgba(34,211,238,0.85)';
    ctx.lineWidth = 1.6;
    ctx.lineJoin = 'round';
    ctx.stroke();

    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.closePath();
    var grad = ctx.createLinearGradient(0, 0, 0, h);
    grad.addColorStop(0, 'rgba(34,211,238,0.22)');
    grad.addColorStop(1, 'rgba(34,211,238,0)');
    ctx.fillStyle = grad;
    ctx.fill();
  }

  function paint() { draw(reduce ? 1400 : 0); }
  function loop(ts) { draw(ts || 0); raf = requestAnimationFrame(loop); }

  size();
  if (reduce) { draw(1400); } else { raf = requestAnimationFrame(loop); }

  if (window.ResizeObserver) {
    new ResizeObserver(function () { size(); if (reduce) { draw(1400); } }).observe(host);
  } else {
    window.addEventListener('resize', function () { size(); if (reduce) { draw(1400); } });
  }
}());
</script></body>
</html>
