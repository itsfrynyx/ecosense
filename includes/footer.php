<?php
/**
 * App footer: closes <main>, adds the console footer, shared shell behaviour.
 */

$ecoPollMs = (int) setting('dashboard_poll_ms', defined('DASHBOARD_POLL_MS') ? DASHBOARD_POLL_MS : 3000);
if ($ecoPollMs < 1000) {
    $ecoPollMs = 3000;
}
$ecoInterval = (int) setting('telemetry_interval_s', defined('TELEMETRY_INTERVAL') ? TELEMETRY_INTERVAL : 2);
$ecoWindow   = (int) setting('device_online_window_s', defined('DEVICE_ONLINE_WINDOW') ? DEVICE_ONLINE_WINDOW : 60);
?>
    <footer class="appfoot">
      <p class="appfoot__brand">
        <span class="brand__mark brand__mark--sm" aria-hidden="true"><?= icon('sensor-rings') ?></span>
        <strong><?= e(APP_NAME) ?></strong>
        <span class="mono">v<?= e(APP_VERSION) ?></span>
      </p>
      <p class="appfoot__meta mono">
        poll <span data-poll-label><?= e((string) ($ecoPollMs / 1000)) ?>s</span>
        &middot; node interval <span data-interval-label><?= e((string) $ecoInterval) ?>s</span>
        &middot; online window <span data-window-label><?= e((string) $ecoWindow) ?>s</span>
        &middot; <span class="mono" data-retention-label><?= e((string) RETENTION_DAYS) ?></span>d retention
      </p>
      <p class="appfoot__note">
        Telemetry from ESP32 nodes over HTTP &rarr; PHP ingest &rarr; MySQL.
        <a href="<?= e(url('index.php')) ?>">Project overview</a>
      </p>
    </footer>

  </div><!-- /.shell__main -->
</div><!-- /.shell -->

<?php if (!empty($ecoScripts) && is_array($ecoScripts)): ?>
<?php foreach ($ecoScripts as $ecoScript): ?>
<script src="<?= e(asset($ecoScript)) ?>" defer></script>
<?php endforeach; ?>
<?php endif; ?>

<script>
/* EcoSense app shell: drawer nav, ticking clock, °C/°F toggle. */
(function () {
  'use strict';

  var doc  = document;
  var body = doc.body;
  if (!body || body.dataset.shellBound === '1') { return; }
  body.dataset.shellBound = '1';

  /* ---- slide-over drawer (mobile) ---------------------------------- */
  var sidebar  = doc.querySelector('[data-sidebar]');
  var scrim    = doc.querySelector('[data-scrim]');
  var openBtn  = doc.querySelector('[data-nav-open]');
  var closeBtn = doc.querySelector('[data-nav-close]');

  function setNav(open) {
    if (!sidebar) { return; }
    body.classList.toggle('nav-open', open);
    if (scrim) { scrim.hidden = !open; }
    if (openBtn) { openBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    var first = open ? sidebar.querySelector('a, button') : null;
    if (first) { first.focus(); }
  }

  if (openBtn)  { openBtn.addEventListener('click', function () { setNav(true); }); }
  if (closeBtn) { closeBtn.addEventListener('click', function () { setNav(false); }); }
  if (scrim)    { scrim.addEventListener('click', function () { setNav(false); }); }

  doc.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && body.classList.contains('nav-open')) { setNav(false); }
  });

  /* Collapse the sidebar to an icon rail with a persisted preference. */
  var railBtn = doc.querySelector('[data-rail-toggle]');
  if (railBtn) {
    if (doc.cookie.indexOf('eco_rail=1') !== -1) { body.classList.add('is-railed'); }
    railBtn.addEventListener('click', function () {
      var on = body.classList.toggle('is-railed');
      doc.cookie = 'eco_rail=' + (on ? '1' : '0') + ';path=' + (window.ECOSENSE_BASE || '/') + ';max-age=31536000;samesite=Lax';
      railBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  /* ---- ticking clock ------------------------------------------------- */
  var clock = doc.querySelector('[data-clock]');
  if (clock) {
    var pad = function (n) { return n < 10 ? '0' + n : String(n); };
    var tick = function () {
      var d = new Date();
      clock.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    };
    tick();
    window.setInterval(tick, 1000);
  }

  /* ---- °C / °F toggle ------------------------------------------------ */
  var unitButtons = doc.querySelectorAll('[data-unit-set]');
  var toF = function (c) { return (c * 9 / 5) + 32; };
  var fromF = function (f) { return (f - 32) * 5 / 9; };

  function applyUnit(unit, persist) {
    unit = unit === 'f' ? 'f' : 'c';

    Array.prototype.forEach.call(unitButtons, function (btn) {
      var on = btn.getAttribute('data-unit-set') === unit;
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });

    /* Every server-rendered temperature carries its °C value on the node. */
    Array.prototype.forEach.call(doc.querySelectorAll('[data-temp-c]'), function (node) {
      var c = parseFloat(node.getAttribute('data-temp-c'));
      if (isNaN(c)) { return; }
      var dp = parseInt(node.getAttribute('data-decimals') || '1', 10);
      var out = (unit === 'f' ? toF(c) : c).toFixed(dp);
      node.textContent = out;
      var slot = node.parentNode ? node.parentNode.querySelector('[data-temp-unit]') : null;
      if (slot) { slot.textContent = unit === 'f' ? '°F' : '°C'; }
    });

    Array.prototype.forEach.call(doc.querySelectorAll('[data-temp-delta-c]'), function (node) {
      var c = parseFloat(node.getAttribute('data-temp-delta-c'));
      if (isNaN(c)) { return; }
      node.textContent = (unit === 'f' ? toF(c) : c).toFixed(1);
    });

    Array.prototype.forEach.call(doc.querySelectorAll('[data-temp-range-c]'), function (node) {
      var c = parseFloat(node.getAttribute('data-temp-range-c'));
      if (isNaN(c)) { return; }
      node.textContent = (unit === 'f' ? toF(c) : c).toFixed(0);
    });

    doc.querySelectorAll('[data-unit-label]').forEach(function (node) {
      node.textContent = unit === 'f' ? '°F' : '°C';
    });

    body.setAttribute('data-unit', unit);

    /* Charts and gauges keep their own scale. */
    doc.dispatchEvent(new CustomEvent('eco:unit', { detail: { unit: unit } }));

    if (persist) {
      doc.cookie = 'eco_unit=' + unit + ';path=' + (window.ECOSENSE_BASE || '/') + ';max-age=31536000;samesite=Lax';
    }
  }

  Array.prototype.forEach.call(unitButtons, function (btn) {
    btn.addEventListener('click', function () {
      applyUnit(btn.getAttribute('data-unit-set'), true);
    });
  });

  applyUnit(body.getAttribute('data-unit') === 'f' ? 'f' : 'c', false);

  /* Expose for page modules that need to re-scale a canvas. */
  window.EcoUnits = { apply: applyUnit, toF: toF, fromF: fromF };

  /* Minimal transient toast, reusing the flash stack styles. */
  window.EcoToast = function (message, kind) {
    if (!message) { return; }
    var type = ['success', 'error', 'danger', 'warning', 'info'].indexOf(kind) === -1 ? 'info' : kind;
    var host = doc.querySelector('.flash-stack');
    if (!host) {
      host = doc.createElement('div');
      host.className = 'flash-stack';
      host.setAttribute('aria-live', 'polite');
      doc.body.appendChild(host);
    }
    var el = doc.createElement('div');
    el.className = 'flash flash--' + type;
    el.setAttribute('role', type === 'error' || type === 'danger' ? 'alert' : 'status');
    el.setAttribute('data-flash', '');

    var msg = doc.createElement('p');
    msg.className = 'flash__msg';
    msg.textContent = String(message);
    el.appendChild(msg);

    var close = doc.createElement('button');
    close.type = 'button';
    close.className = 'flash__close';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = '\u00D7';
    el.appendChild(close);

    var drop = function () {
      el.style.transition = 'opacity .3s ease, transform .3s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-8px)';
      window.setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 320);
    };
    close.addEventListener('click', drop);

    host.appendChild(el);
    window.setTimeout(drop, 4200);
  };
}());
</script>
</body>
</html>