<?php
/**
 * Split-screen registration. Creates an operator account, then signs in.
 */

require_once __DIR__ . '/includes/auth.php';

auth_start_session();

if (is_logged_in()) {
    redirect('dashboard.php');
}

$ecoErrors = [];
$ecoName   = '';
$ecoEmail  = '';
$ecoDbDown = !db_available();

if (is_post()) {
    $ecoName  = trim((string) ($_POST['name'] ?? ''));
    $ecoEmail = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!verify_csrf()) {
        $ecoErrors['form'] = 'Your session expired before the form was submitted. Please try again.';
    } elseif ($ecoDbDown) {
        $ecoErrors['form'] = 'The database is unreachable, so accounts cannot be created right now.';
    } else {
        $pass  = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password_confirm'] ?? '');

        if ($ecoName === '') {
            $ecoErrors['name'] = 'Tell us what to call you.';
        } elseif (mb_strlen($ecoName) < 2 || mb_strlen($ecoName) > 120) {
            $ecoErrors['name'] = 'Use between 2 and 120 characters.';
        }

        if ($ecoEmail === '') {
            $ecoErrors['email'] = 'An email address is required.';
        } elseif (!filter_var($ecoEmail, FILTER_VALIDATE_EMAIL)) {
            $ecoErrors['email'] = 'That does not look like a valid email address.';
        } elseif (mb_strlen($ecoEmail) > 190) {
            $ecoErrors['email'] = 'That address is too long.';
        }

        if ($pass === '') {
            $ecoErrors['password'] = 'Choose a password.';
        } elseif (mb_strlen($pass) < 8) {
            $ecoErrors['password'] = 'Use at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
            $ecoErrors['password'] = 'Mix letters and numbers.';
        }

        if ($pass2 === '') {
            $ecoErrors['password_confirm'] = 'Repeat the password.';
        } elseif ($pass !== '' && $pass !== $pass2) {
            $ecoErrors['password_confirm'] = 'The two passwords do not match.';
        }

        if (empty($_POST['terms'])) {
            $ecoErrors['terms'] = 'Please accept the operating terms to continue.';
        }

        if (!$ecoErrors) {
            $exists = db_val('SELECT id FROM users WHERE email = ? LIMIT 1', [$ecoEmail]);
            if ($exists !== null) {
                $ecoErrors['email'] = 'An account already uses that email address.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $made = db_exec(
                    'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
                    [$ecoName, $ecoEmail, $hash, 'operator']
                );

                if ($made === false) {
                    $ecoErrors['form'] = 'The account could not be created. Please try again.';
                } else {
                    attempt_login($ecoEmail, $pass);
                    flash('success', 'Welcome aboard, ' . $ecoName . '. Your operator account is ready.');
                    redirect('dashboard.php');
                }
            }
        }
    }
}

$ecoFeatures = [
    ['icon' => 'gauge',   'title' => 'Live instrument view', 'copy' => 'Monotone-cubic traces, arc gauges and 3 second polling with an explicit reconnect state.'],
    ['icon' => 'layers',  'title' => 'Every node, one console', 'copy' => 'A left rail for the fleet, a device table for detail, and a role gate on edits.'],
    ['icon' => 'database', 'title' => 'Your readings stay local', 'copy' => 'Ingest, storage and rendering all run on your own XAMPP instance.'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0B0E13">
<title><?= e(page_title('Create account')) ?></title>
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
<body class="auth auth--register">

<a class="skip-link" href="#authForm">Skip to the registration form</a>
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
        <span class="live-dot" aria-hidden="true"></span> Operator access
      </p>
      <h1 class="auth-brand__title">Register a node<br><em>operator account</em>.</h1>
      <p class="auth-brand__sub">
        New accounts start with the operator role: full visibility into every node,
        with fleet administration reserved for administrators.
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
      <span>ESP32 &rarr; PHP &rarr; MySQL</span>
      <span class="mono">v<?= e(APP_VERSION) ?></span>
    </p>
  </section>

  <!-- ═══ Form panel ════════════════════════════════════════════════ -->
  <section class="auth-panel">
    <div class="auth-card">
      <div class="auth-card__head">
        <h2 class="auth-card__title">Create account</h2>
        <p class="auth-card__sub">Takes about twenty seconds. No card, no cloud.</p>
      </div>

      <?php foreach (get_flashes() as $ecoF): ?>
        <div class="notice notice--info" role="status" style="margin-bottom:18px">
          <span class="notice__ico" aria-hidden="true"><?= icon('info') ?></span>
          <div><p class="notice__body"><?= e((string) ($ecoF['message'] ?? '')) ?></p></div>
        </div>
      <?php endforeach; ?>

      <?php if (isset($ecoErrors['form'])): ?>
        <div class="notice notice--crit" role="alert" style="margin-bottom:18px">
          <span class="notice__ico" aria-hidden="true"><?= icon('alert-triangle') ?></span>
          <div>
            <p class="notice__title">Registration blocked</p>
            <p class="notice__body"><?= e($ecoErrors['form']) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <form class="auth-form" id="authForm" method="post" action="<?= e(url('register.php')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="field">
          <label class="field__label" for="name"><?= icon('user') ?> Full name</label>
          <input class="input" type="text" id="name" name="name" autocomplete="name"
                 maxlength="120" placeholder="Rosa Delgado"
                 value="<?= e($ecoName) ?>" <?= isset($ecoErrors['name']) ? 'aria-invalid="true"' : '' ?> required>
          <?php if (isset($ecoErrors['name'])): ?>
            <p class="field__err"><?= icon('alert-triangle', 'ico--sm') ?><?= e($ecoErrors['name']) ?></p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="field__label" for="email"><?= icon('user') ?> Email address</label>
          <input class="input" type="email" id="email" name="email" autocomplete="username"
                 inputmode="email" spellcheck="false" maxlength="190" placeholder="you@example.com"
                 value="<?= e($ecoEmail) ?>" <?= isset($ecoErrors['email']) ? 'aria-invalid="true"' : '' ?> required>
          <?php if (isset($ecoErrors['email'])): ?>
            <p class="field__err"><?= icon('alert-triangle', 'ico--sm') ?><?= e($ecoErrors['email']) ?></p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="field__label" for="password"><?= icon('lock') ?> Password</label>
          <div class="pass-field">
            <input class="input" type="password" id="password" name="password"
                   autocomplete="new-password" minlength="8" placeholder="At least 8 characters"
                   <?= isset($ecoErrors['password']) ? 'aria-invalid="true"' : '' ?> required>
            <button type="button" class="pass-field__toggle" data-show-pass aria-label="Show password" aria-pressed="false">
              <?= icon('eye') ?>
            </button>
          </div>
          <?php if (isset($ecoErrors['password'])): ?>
            <p class="field__err"><?= icon('alert-triangle', 'ico--sm') ?><?= e($ecoErrors['password']) ?></p>
          <?php else: ?>
            <p class="field__hint">Mix letters and numbers, 8 characters or more.</p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="field__label" for="password_confirm"><?= icon('shield') ?> Confirm password</label>
          <input class="input" type="password" id="password_confirm" name="password_confirm"
                 autocomplete="new-password" placeholder="Repeat the password"
                 <?= isset($ecoErrors['password_confirm']) ? 'aria-invalid="true"' : '' ?> required>
          <?php if (isset($ecoErrors['password_confirm'])): ?>
            <p class="field__err"><?= icon('alert-triangle', 'ico--sm') ?><?= e($ecoErrors['password_confirm']) ?></p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="check" for="terms">
            <input type="checkbox" id="terms" name="terms" value="1" <?= isset($ecoErrors['terms']) ? '' : 'checked' ?>>
            <span>I understand this console runs locally and I am authorised to monitor these nodes.</span>
          </label>
          <?php if (isset($ecoErrors['terms'])): ?>
            <p class="field__err"><?= icon('alert-triangle', 'ico--sm') ?><?= e($ecoErrors['terms']) ?></p>
          <?php endif; ?>
        </div>

        <button class="btn btn--primary btn--block" type="submit">
          <?= icon('plus', 'ico--sm') ?> Create operator account
        </button>
      </form>

      <p class="auth-alt">
        Already registered? <a href="<?= e(url('login.php')) ?>">Sign in instead</a>
      </p>
    </div>
  </section>
</div>

<script>
/* Brand waveform + password reveal. Frozen under prefers-reduced-motion. */
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
        + Math.sin(p * 9 + t / 300) * amp
        + Math.sin(p * 24 - t / 170) * amp * 0.30
        + Math.sin(p * 2.6 + t / 640) * amp * 0.52;
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

  function loop(ts) { draw(ts || 0); raf = requestAnimationFrame(loop); }

  size();
  if (reduce) { draw(900); } else { raf = requestAnimationFrame(loop); }

  if (window.ResizeObserver) {
    new ResizeObserver(function () { size(); if (reduce) { draw(900); } }).observe(host);
  } else {
    window.addEventListener('resize', function () { size(); if (reduce) { draw(900); } });
  }
}());
</script>
</body>
</html>
