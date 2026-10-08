<?php
/**
 * EcoSense — public landing page.
 * Renders completely with MySQL stopped: every read is guarded and falls
 * back to the designed demo defaults.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$ecoNodesTotal = 4;
$ecoNodesOnline = 4;
$ecoReadings = 1920;
$ecoAvgTemp = 28.4;
$ecoFleetStatus = 'online';
$ecoDbUp = db_available();

if ($ecoDbUp) {
    try {
        $ecoFleet = db_one('SELECT COUNT(*) AS total,
                                   SUM(status = \'online\') AS online,
                                   MAX(last_seen_at) AS last_seen
                            FROM devices');
        if ($ecoFleet && (int) $ecoFleet['total'] > 0) {
            $ecoNodesTotal = (int) $ecoFleet['total'];
            $ecoNodesOnline = (int) $ecoFleet['online'];
            $ecoFleetStatus = $ecoNodesOnline > 0 ? 'online' : 'offline';
        }

        $ecoCount = db_val('SELECT COUNT(*) FROM readings', [], null);
        if ($ecoCount) {
            $ecoReadings = (int) $ecoCount;
        }

        $ecoMean = db_val(
            'SELECT AVG(temperature_c) FROM readings WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
            [],
            null
        );
        if ($ecoMean !== null && is_numeric($ecoMean)) {
            $ecoAvgTemp = round((float) $ecoMean, 1);
        }
    } catch (Throwable $ecoThrowable) {
        $ecoDbUp = false;
    }
}

if (!$ecoDbUp) {
    $ecoNodesTotal = 4;
    $ecoNodesOnline = 4;
    $ecoFleetStatus = 'unknown';
}

$ecoNavItems = [
    '#architecture' => 'Architecture',
    '#hardware'     => 'Hardware',
    '#features'     => 'Features',
    '#stack'        => 'Stack',
    '#faq'          => 'FAQ',
];

include __DIR__ . '/includes/head.php';
?>
<a class="skip-link" href="#main"><?= icon('arrow-down', 'ico--sm') ?> Skip to content</a>
<div class="progress" aria-hidden="true"><div class="progress__bar" data-progress></div></div>

<header class="nav" data-nav>
  <div class="shell nav__inner">
    <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="EcoSense — home">
      <span class="brand__mark" aria-hidden="true"><?= icon('sensor-rings') ?></span>
      <span><?= e(APP_NAME) ?></span>
      <span class="brand__sub">IoT Telemetry</span>
    </a>

    <nav class="nav__links" aria-label="Primary">
      <?php foreach ($ecoNavItems as $ecoHref => $ecoLabel): ?>
        <a class="nav__link" href="<?= e($ecoHref) ?>"><?= e($ecoLabel) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="nav__cta">
      <a class="btn btn--ghost" href="<?= e(url('login.php')) ?>">Sign in</a>
      <a class="btn btn--primary" href="<?= e(url('dashboard.php')) ?>">
        Launch dashboard <?= icon('arrow-right', 'ico--sm ico--arrow') ?>
      </a>
    </div>

    <button type="button" class="nav__toggle" data-nav-toggle aria-expanded="false" aria-controls="navPanel" aria-label="Toggle navigation menu">
      <?= icon('menu', 'ico--menu') ?>
      <?= icon('close', 'ico--close') ?>
    </button>
  </div>

  <div class="nav__panel" id="navPanel" data-nav-panel aria-hidden="true">
    <div class="nav__panel-list">
      <?php foreach ($ecoNavItems as $ecoHref => $ecoLabel): ?>
        <a href="<?= e($ecoHref) ?>"><?= e($ecoLabel) ?> <?= icon('chevron-right', 'ico--sm') ?></a>
      <?php endforeach; ?>
    </div>
    <div class="nav__panel-cta">
      <a class="btn btn--ghost btn--block" href="<?= e(url('login.php')) ?>">Sign in</a>
      <a class="btn btn--primary btn--block" href="<?= e(url('dashboard.php')) ?>">Launch dashboard</a>
    </div>
  </div>
</header>

<main id="main">

  <!-- 2. Hero -->
  <section class="hero" aria-labelledby="heroTitle">
    <span class="glow glow--cyan" aria-hidden="true"></span>
    <span class="glow glow--violet" aria-hidden="true"></span>
    <span class="orb orb--a" aria-hidden="true"></span>
    <span class="orb orb--b" aria-hidden="true"></span>
    <span class="orb orb--c" aria-hidden="true"></span>
    <span class="scan" aria-hidden="true"><span class="scan__line"></span></span>

    <div class="shell hero__grid">
      <div>
        <p class="eyebrow">
          <span class="live-dot" aria-hidden="true"></span>
          System online &mdash; <?= e(h($ecoNodesOnline)) ?> of <?= e(h($ecoNodesTotal)) ?> nodes reporting
        </p>

        <h1 class="hero__title" id="heroTitle">Feel the weather <em>before</em> it arrives.</h1>

        <p class="hero__sub lede">
          Four ESP32 sensor nodes sample temperature, humidity, pressure and lux every two seconds,
          push them over Wi&#8209;Fi to a PHP ingest API, and land in MySQL &mdash; where the dashboard
          renders them live, with alerting and history you can actually export.
        </p>

        <div class="hero__cta">
          <a class="btn btn--primary btn--lg" href="<?= e(url('dashboard.php')) ?>">
            Launch live dashboard <?= icon('arrow-right', 'ico--sm ico--arrow') ?>
          </a>
          <a class="btn btn--ghost btn--lg" href="#architecture">
            Read the architecture <?= icon('arrow-down', 'ico--sm') ?>
          </a>
        </div>

        <ul class="hero__meta">
          <li><?= icon('chip', 'ico--sm') ?> ESP32-WROOM-32 &times;4</li>
          <li><?= icon('clock', 'ico--sm') ?> 2 s publish interval</li>
          <li><?= icon('database', 'ico--sm') ?> PDO / MySQL, prepared statements</li>
          <li><?= icon('shield', 'ico--sm') ?> Per-device 64-char API keys</li>
        </ul>

        <div class="trust">
          <div class="trust__item">
            <p class="trust__val">4</p>
            <p class="trust__label">Sensor nodes</p>
          </div>
          <div class="trust__item">
            <p class="trust__val">2 s</p>
            <p class="trust__label">Publish interval</p>
          </div>
          <div class="trust__item">
            <p class="trust__val">99.4%</p>
            <p class="trust__label">Fleet uptime</p>
          </div>
          <div class="trust__item">
            <p class="trust__val">&plusmn;0.2 &deg;C</p>
            <p class="trust__label">Node accuracy</p>
          </div>
        </div>
      </div>

      <!-- live instrument panel -->
      <div class="instrument" aria-label="Simulated live telemetry from node ECO-TH-001">
        <div class="instrument__bar">
          <p class="instrument__id"><?= icon('activity', 'ico--sm') ?> Scope &middot; ECO-TH-001</p>
          <p class="instrument__state">
            <span class="live-dot<?= $ecoDbUp && $ecoFleetStatus === 'online' ? '' : ' live-dot--warn' ?>" aria-hidden="true"></span>
            <?= $ecoDbUp ? 'Live' : 'Simulated' ?>
          </p>
        </div>

        <div class="scope">
          <svg viewBox="0 0 560 240" role="img" aria-label="Oscilloscope trace of temperature samples over the last 20 seconds">
            <defs>
              <linearGradient id="ecoAreaFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.26"></stop>
                <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"></stop>
              </linearGradient>
            </defs>

            <path class="scope__grid" d="M100 16V196 M164 16V196 M228 16V196 M292 16V196 M356 16V196 M420 16V196 M484 16V196 M36 46H548 M36 76H548 M36 106H548 M36 136H548 M36 166H548"></path>

            <g class="scope__axis" text-anchor="end">
              <text x="28" y="19">40.0</text>
              <text x="28" y="79">30.0</text>
              <text x="28" y="139">20.0</text>
              <text x="28" y="199">10.0</text>
            </g>
            <g class="scope__axis">
              <text x="36" y="214">&minus;20s</text>
              <text x="228" y="214">&minus;10s</text>
              <text x="420" y="214">&minus;5s</text>
              <text x="530" y="214">now</text>
            </g>
            <text class="scope__axis" x="36" y="232">&deg;C &middot; 10 &deg;C/div</text>

            <path class="scope__area" data-scope-area d=""></path>
            <path class="scope__ref" data-scope-ref d=""></path>
            <line class="scope__cursor" data-scope-cursor x1="548" x2="548" y1="16" y2="196"></line>
            <path class="scope__line" data-scope-line d=""></path>
            <circle class="scope__dot" data-scope-dot cx="548" cy="106" r="3.4"></circle>
          </svg>
          <p class="scope__readout">CH1 &middot; 2.0 s/div &middot; auto</p>
        </div>

        <div data-telemetry>
          <div class="tele">
            <div class="tele__grid">
              <span class="tele__ico"><?= icon('thermometer') ?></span>
              <span class="tele__label">Temperature</span>
              <span class="tele__val" data-tele="temp" data-seed="<?= e(number_format($ecoAvgTemp, 1, '.', '')) ?>"><?= e(number_format($ecoAvgTemp, 1, '.', '')) ?></span>
              <span class="tele__unit">&deg;C</span>
              <span class="tele__delta" data-tele-delta="temp" data-dir="flat">+0.1 vs last</span>
            </div>
          </div>
          <div class="tele">
            <div class="tele__grid">
              <span class="tele__ico"><?= icon('droplet') ?></span>
              <span class="tele__label">Humidity</span>
              <span class="tele__val" data-tele="hum" data-seed="63.2">63.2</span>
              <span class="tele__unit">%</span>
              <span class="tele__delta" data-tele-delta="hum" data-dir="flat">&minus;0.8 vs last</span>
            </div>
          </div>
          <div class="tele">
            <div class="tele__grid">
              <span class="tele__ico"><?= icon('gauge') ?></span>
              <span class="tele__label">Pressure</span>
              <span class="tele__val" data-tele="pres" data-seed="1008.4">1008.4</span>
              <span class="tele__unit">hPa</span>
              <span class="tele__delta" data-tele-delta="pres" data-dir="flat">+0.2 vs last</span>
            </div>
          </div>
        </div>

        <div class="instrument__foot">
          <span>Last packet <b data-stamp><?= e(date('H:i:s')) ?></b></span>
          <span>Pkt <b data-packets>000000</b></span>
          <span>Interval <b><?= e(h(TELEMETRY_INTERVAL)) ?> s</b></span>
          <?= status_badge($ecoFleetStatus) ?>
        </div>
      </div>
    </div>

    <?php if (!$ecoDbUp): ?>
      <div class="shell hero__notice">
        <div class="notice reveal" data-reveal="1" role="status">
          <span class="notice__ico"><?= icon('database', 'ico--lg') ?></span>
          <div>
            <p class="notice__title">Demo telemetry &mdash; database not connected</p>
            <p class="notice__body">
              This page renders fully without MySQL, so every figure above is a simulation.
              To read live values, start MySQL from the XAMPP control panel and import the schema once:
              <code>database/schema.sql</code>
            </p>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <!-- 3. Live stat band -->
  <section class="band" aria-label="System statistics">
    <div class="shell stats">
      <div class="stat">
        <p class="stat__label">Readings stored</p>
        <p class="stat__num"><span data-count="<?= e((int) $ecoReadings) ?>" data-group="true">0</span></p>
        <p class="stat__note">48 h backfill &middot; 6 min resolution</p>
        <svg class="stat__spark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true">
          <path class="spark-fill" d="M0 26 L10 22 L20 24 L30 17 L40 19 L50 12 L60 15 L70 9 L80 12 L90 7 L100 10 L110 5 L120 8 L120 34 L0 34 Z"></path>
          <path d="M0 26 L10 22 L20 24 L30 17 L40 19 L50 12 L60 15 L70 9 L80 12 L90 7 L100 10 L110 5 L120 8"></path>
        </svg>
      </div>

      <div class="stat stat--violet">
        <p class="stat__label">Avg temperature &middot; 24 h</p>
        <p class="stat__num"><span data-count="<?= e(h($ecoAvgTemp, 1)) ?>" data-decimals="1">0</span><span class="stat__unit">&deg;C</span></p>
        <p class="stat__note">Fleet mean of all reporting nodes</p>
        <svg class="stat__spark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true">
          <path class="spark-fill" d="M0 22 L10 24 L20 18 L30 20 L40 13 L50 16 L60 10 L70 14 L80 8 L90 12 L100 7 L110 11 L120 6 L120 34 L0 34 Z"></path>
          <path d="M0 22 L10 24 L20 18 L30 20 L40 13 L50 16 L60 10 L70 14 L80 8 L90 12 L100 7 L110 11 L120 6"></path>
        </svg>
      </div>

      <div class="stat stat--good">
        <p class="stat__label">Nodes reporting</p>
        <p class="stat__num"><span data-count="<?= e(h($ecoNodesOnline)) ?>">0</span><span class="stat__unit">/ <?= e(h($ecoNodesTotal)) ?></span></p>
        <p class="stat__note">Offline window 60 s</p>
        <svg class="stat__spark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true">
          <path class="spark-fill" d="M0 20 L10 12 L20 16 L30 10 L40 14 L50 8 L60 12 L70 6 L80 11 L90 7 L100 12 L110 8 L120 10 L120 34 L0 34 Z"></path>
          <path d="M0 20 L10 12 L20 16 L30 10 L40 14 L50 8 L60 12 L70 6 L80 11 L90 7 L100 12 L110 8 L120 10"></path>
        </svg>
      </div>

      <div class="stat stat--lime">
        <p class="stat__label">Fleet uptime</p>
        <p class="stat__num"><span data-count="99.4" data-decimals="1">0</span><span class="stat__unit">%</span></p>
        <p class="stat__note">Rolling 30-day availability target</p>
        <svg class="stat__spark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true">
          <path class="spark-fill" d="M0 16 L10 11 L20 13 L30 9 L40 12 L50 8 L60 10 L70 7 L80 9 L90 6 L100 8 L110 5 L120 7 L120 34 L0 34 Z"></path>
          <path d="M0 16 L10 11 L20 13 L30 9 L40 12 L50 8 L60 10 L70 7 L80 9 L90 6 L100 8 L110 5 L120 7"></path>
        </svg>
      </div>
    </div>
  </section>

  <!-- 4. How it works -->
  <section class="section" aria-labelledby="howTitle">
    <div class="shell">
      <div class="section__head reveal" data-reveal="1">
        <p class="eyebrow">Pipeline</p>
        <h2 class="h2" id="howTitle">Four stages, one loop.</h2>
        <p>From the air on a rooftop to a chart on a laptop in under two seconds &mdash; every stage is a
          separate, inspectable piece of hardware or code.</p>
      </div>

      <ol class="steps">
        <li class="step reveal" data-reveal="1">
          <span class="step__node" aria-hidden="true">01</span>
          <span class="step__ico" aria-hidden="true"><?= icon('thermometer') ?></span>
          <h3>Sense</h3>
          <p>The node samples temperature, humidity, barometric pressure and ambient light, then applies a
            moving average to reject spikes.</p>
          <span class="step__spec">DHT22 &middot; &plusmn;0.5 &deg;C</span>
        </li>
        <li class="step reveal" data-reveal="2">
          <span class="step__node" aria-hidden="true">02</span>
          <span class="step__ico" aria-hidden="true"><?= icon('wifi') ?></span>
          <h3>Transmit</h3>
          <p>The ESP32 wakes from deep sleep every two seconds and POSTs a compact JSON packet over the
            local Wi&#8209;Fi network.</p>
          <span class="step__spec">HTTP POST &middot; 2 s</span>
        </li>
        <li class="step reveal" data-reveal="3">
          <span class="step__node" aria-hidden="true">03</span>
          <span class="step__ico" aria-hidden="true"><?= icon('server') ?></span>
          <h3>Ingest</h3>
          <p>The PHP endpoint authenticates the node, validates every range, stores the reading and
            evaluates the alert rules before answering.</p>
          <span class="step__spec">api/ingest.php &middot; PDO</span>
        </li>
        <li class="step reveal" data-reveal="4">
          <span class="step__node" aria-hidden="true">04</span>
          <span class="step__ico" aria-hidden="true"><?= icon('activity') ?></span>
          <h3>Visualise</h3>
          <p>The dashboard polls the JSON endpoint every three seconds and pushes new points straight into
            the charts without a refresh.</p>
          <span class="step__spec">poll 3000 ms &middot; canvas</span>
        </li>
      </ol>
    </div>
  </section>

  <!-- 5. Architecture -->
  <section class="section band" id="architecture" aria-labelledby="archTitle">
    <div class="shell">
      <div class="section__head reveal" data-reveal="1">
        <p class="eyebrow">Architecture</p>
        <h2 class="h2" id="archTitle">Sensor node to screen, end to end.</h2>
        <p>Nothing here needs a cloud account or a build step. A node pushes a packet, MySQL keeps it,
          and a browser reads it back as JSON.</p>
      </div>

      <div class="arch">
        <div class="arch__stage reveal" data-reveal="1">
          <svg class="arch__svg" viewBox="0 0 1180 340" role="img" aria-label="Data flow from a sensor node through Wi-Fi to an ESP32, then an HTTP POST to the PHP ingest API, into MySQL, and finally to the live dashboard">
            <defs>
              <marker id="archHead" class="arch__head" viewBox="0 0 10 10" refX="8.4" refY="5" markerWidth="5.5" markerHeight="5.5" orient="auto-start-reverse">
                <path d="M0 1 L9 5 L0 9 z" fill="currentColor"></path>
              </marker>
              <marker id="archHeadViolet" class="arch__head--violet" viewBox="0 0 10 10" refX="8.4" refY="5" markerWidth="5.5" markerHeight="5.5" orient="auto-start-reverse">
                <path d="M0 1 L9 5 L0 9 z" fill="currentColor"></path>
              </marker>
            </defs>

            <g>
              <rect class="arch__box" x="24" y="30" width="240" height="86" rx="10"></rect>
              <g transform="translate(44, 60)"><?= icon('thermometer', 'arch__ico') ?></g>
              <text class="arch__ttl" x="76" y="70">SENSOR NODE</text>
              <text class="arch__sub" x="76" y="90">DHT22 &middot; BMP280 &middot; BH1750</text>
            </g>

            <g>
              <rect class="arch__box" x="310" y="30" width="240" height="86" rx="10"></rect>
              <g transform="translate(330, 60)"><?= icon('wifi', 'arch__ico') ?></g>
              <text class="arch__ttl" x="362" y="70">WI-FI AP</text>
              <text class="arch__sub" x="362" y="90">2.4 GHz &middot; WPA2 &middot; 11 dBm</text>
            </g>

            <g>
              <rect class="arch__box arch__box--live" x="596" y="30" width="240" height="86" rx="10"></rect>
              <g transform="translate(616, 60)"><?= icon('chip', 'arch__ico') ?></g>
              <text class="arch__ttl" x="648" y="70">ESP32-WROOM-32</text>
              <text class="arch__sub" x="648" y="90">JSON payload &middot; deep sleep</text>
            </g>

            <g>
              <rect class="arch__box" x="882" y="30" width="240" height="86" rx="10"></rect>
              <g transform="translate(902, 60)"><?= icon('route', 'arch__ico') ?></g>
              <text class="arch__ttl" x="934" y="70">HTTP POST</text>
              <text class="arch__sub" x="934" y="90">X-Device-Key &middot; every 2 s</text>
            </g>

            <g>
              <rect class="arch__box arch__box--live" x="882" y="222" width="240" height="86" rx="10"></rect>
              <g transform="translate(902, 252)"><?= icon('server', 'arch__ico') ?></g>
              <text class="arch__ttl" x="934" y="262">PHP INGEST API</text>
              <text class="arch__sub" x="934" y="282">validate &middot; store &middot; alert</text>
            </g>

            <g>
              <rect class="arch__box" x="596" y="222" width="240" height="86" rx="10"></rect>
              <g transform="translate(616, 252)"><?= icon('database', 'arch__ico') ?></g>
              <text class="arch__ttl" x="648" y="262">MYSQL</text>
              <text class="arch__sub" x="648" y="282">readings &middot; indexed</text>
            </g>

            <g>
              <rect class="arch__box" x="150" y="222" width="400" height="86" rx="10"></rect>
              <g transform="translate(170, 252)"><?= icon('dashboard', 'arch__ico') ?></g>
              <text class="arch__ttl" x="202" y="262">LIVE DASHBOARD</text>
              <text class="arch__sub" x="202" y="282">GET api/latest.php &middot; charts &middot; CSV</text>
            </g>

            <path class="arch__flow" d="M264 73 H304" marker-end="url(#archHead)"></path>
            <path class="arch__flow" d="M550 73 H590" marker-end="url(#archHead)"></path>
            <path class="arch__flow" d="M836 73 H876" marker-end="url(#archHead)"></path>
            <path class="arch__flow" d="M1002 116 V216" marker-end="url(#archHead)"></path>
            <path class="arch__flow" d="M876 265 H842" marker-end="url(#archHead)"></path>
            <path class="arch__flow arch__flow--violet" d="M590 265 H556" marker-end="url(#archHeadViolet)"></path>
          </svg>

          <p class="arch__cap">
            <span><span class="live-dot" aria-hidden="true"></span> telemetry write path</span>
            <span><span class="live-dot live-dot--warn" aria-hidden="true"></span> read path &middot; JSON every 3 s</span>
            <span>figure 1 &middot; data flow</span>
          </p>
        </div>

        <div class="arch__notes">
          <article class="arch__note reveal" data-reveal="2">
            <h3><?= icon('key', 'ico--sm') ?> Device identity</h3>
            <p>Every node owns a <code>device_uid</code> and a 64-character key sent as
              <code>X-Device-Key</code>. A mismatch returns <strong>401</strong> and writes nothing.</p>
          </article>
          <article class="arch__note reveal" data-reveal="3">
            <h3><?= icon('shield', 'ico--sm') ?> Validation at the API</h3>
            <p>Ranges are enforced server-side &mdash; &minus;60&hellip;85 &deg;C, 0&hellip;100 % RH,
              300&hellip;1200 hPa. Anything outside returns <strong>422</strong>.</p>
          </article>
          <article class="arch__note reveal" data-reveal="4">
            <h3><?= icon('bell', 'ico--sm') ?> Alerts in the same pass</h3>
            <p>After the insert, the matching <code>alert_rules</code> are evaluated and any breach is
              written before the endpoint answers <strong>200</strong>.</p>
          </article>
        </div>
      </div>
    </div>
  </section>

  <!-- 6. Features -->
  <section class="section" id="features" aria-labelledby="featTitle">
    <div class="shell">
      <div class="section__head reveal" data-reveal="1">
        <p class="eyebrow">Capabilities</p>
        <h2 class="h2" id="featTitle">Everything an instrument panel should do.</h2>
        <p>Built for a lab bench and a viva panel: each capability is visible on screen, auditable in the
          database, and exportable as CSV.</p>
      </div>

      <div class="features__grid" data-spotlight>
        <article class="feat feat--wide reveal" data-reveal="1">
          <span class="feat__ico"><?= icon('activity', 'ico--lg') ?></span>
          <h3>Real-time streaming</h3>
          <p>Readings arrive every two seconds and are pushed into the live charts on a three-second poll.
            No page reloads, no lost samples, and a clear "connection lost" state when the API stops answering.</p>
          <span class="feat__spec">poll 3000 ms &middot; draw-in 240 ms</span>
        </article>

        <article class="feat feat--wide feat--violet reveal" data-reveal="2">
          <span class="feat__ico"><?= icon('bell', 'ico--lg') ?></span>
          <h3>Threshold alerting</h3>
          <p>Per-metric rules (<code>&gt;</code>, <code>&lt;</code>, <code>&ge;</code>, <code>&le;</code>) with warning and critical
            severity are evaluated at ingest time, logged with the value that triggered them, and acknowledged from the app.</p>
          <span class="feat__spec">severity: warning &middot; critical</span>
        </article>

        <article class="feat reveal" data-reveal="3">
          <span class="feat__ico"><?= icon('wifi', 'ico--lg') ?></span>
          <h3>Multi-node fleet</h3>
          <p>Register nodes from the dashboard; each gets a UID, a location and a fresh API key.</p>
          <span class="feat__spec">ECO-TH-001 &hellip; ECO-TH-004</span>
        </article>

        <article class="feat feat--violet reveal" data-reveal="4">
          <span class="feat__ico"><?= icon('trending-up', 'ico--lg') ?></span>
          <h3>History &amp; CSV export</h3>
          <p>48 hours to 30 days of history with min/avg/max summaries and a real <code>text/csv</code> download.</p>
          <span class="feat__spec">30-day retention &middot; csv</span>
        </article>

        <article class="feat feat--good reveal" data-reveal="5">
          <span class="feat__ico"><?= icon('server', 'ico--lg') ?></span>
          <h3>Offline detection</h3>
          <p>No heartbeat for 60 seconds flips a node to Offline and raises an alert automatically.</p>
          <span class="feat__spec">offline window 60 s</span>
        </article>

        <article class="feat reveal" data-reveal="6">
          <span class="feat__ico"><?= icon('user', 'ico--lg') ?></span>
          <h3>Role-based access</h3>
          <p>Admin, operator and viewer roles gate every mutating action; passwords are bcrypt hashes.</p>
          <span class="feat__spec">admin &middot; operator &middot; viewer</span>
        </article>
      </div>
    </div>
  </section>

  <!-- 7. Hardware -->
  <section class="section band" id="hardware" aria-labelledby="hwTitle">
    <div class="shell">
      <div class="section__head reveal" data-reveal="1">
        <p class="eyebrow">Hardware</p>
        <h2 class="h2" id="hwTitle">Inside a node.</h2>
        <p>One ESP32 board, five sensors and a protected cell. Everything is off-the-shelf, so a lab can
          rebuild a node for a few thousand pesos.</p>
      </div>

      <div class="hw">
        <div class="reveal" data-reveal="1">
          <div class="exploded" role="img" aria-label="Exploded view of a sensor node: antenna, sealed lid, main PCB, sensor daughterboard and battery cell">
            <div class="exploded__stage">
              <div class="plate plate--antenna">
                <div class="plate__dots"><i></i><i></i><i></i><i></i><i></i><i></i></div>
              </div>
              <div class="plate plate--lid">
                <div class="plate__dots"><i></i><i></i><i></i><i></i></div>
              </div>
              <div class="plate plate--pcb">
                <div class="plate__dots"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
              </div>
              <div class="plate plate--sensor">
                <div class="plate__dots"><i></i><i></i><i></i></div>
              </div>
              <div class="plate plate--battery">
                <div class="plate__dots"><i></i><i></i><i></i><i></i><i></i></div>
              </div>
              <span class="core" aria-hidden="true"></span>
            </div>
            <span class="exploded__tag exploded__tag--a">2.4 GHz antenna</span>
            <span class="exploded__tag exploded__tag--b">ESP32 + sensor stack</span>
            <span class="exploded__tag exploded__tag--c">18650 cell</span>
          </div>
        </div>

        <div class="reveal" data-reveal="2">
          <table class="bom">
            <caption>Bill of materials &middot; per node</caption>
            <thead>
              <tr>
                <th scope="col">Component</th>
                <th scope="col">Spec</th>
                <th scope="col">Role</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td data-label="Component">ESP32-WROOM-32</td>
                <td data-label="Spec">Dual-core 240 MHz</td>
                <td data-label="Role">Host MCU &middot; Wi-Fi client &middot; deep sleep</td>
              </tr>
              <tr>
                <td data-label="Component">DHT22</td>
                <td data-label="Spec">&plusmn;0.5 &deg;C &middot; &plusmn;2 % RH</td>
                <td data-label="Role">Air temperature and relative humidity</td>
              </tr>
              <tr>
                <td data-label="Component">BMP280</td>
                <td data-label="Spec">&plusmn;1 hPa &middot; 300&ndash;1100 hPa</td>
                <td data-label="Role">Barometric pressure and altitude trend</td>
              </tr>
              <tr>
                <td data-label="Component">BH1750</td>
                <td data-label="Spec">1&ndash;65535 lx</td>
                <td data-label="Role">Ambient illuminance, day/night tracking</td>
              </tr>
              <tr>
                <td data-label="Component">DS18B20 probe</td>
                <td data-label="Spec">&plusmn;0.5 &deg;C &middot; &minus;55&hellip;125 &deg;C</td>
                <td data-label="Role">Sealed soil / enclosure temperature</td>
              </tr>
              <tr>
                <td data-label="Component">18650 + TP4056</td>
                <td data-label="Spec">3.7 V 2600 mAh</td>
                <td data-label="Role">Power bank with USB-C charge and protection</td>
              </tr>
              <tr>
                <td data-label="Component">Ceramic antenna</td>
                <td data-label="Spec">2.4 GHz &middot; +2 dBi</td>
                <td data-label="Role">Range extension for indoor corridors</td>
              </tr>
              <tr>
                <td data-label="Component">IP65 enclosure</td>
                <td data-label="Spec">ABS &middot; silicone gasket</td>
                <td data-label="Role">Weather-sealed housing for rooftop mounting</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- 8. Stack -->
  <section class="section" id="stack" aria-labelledby="stackTitle">
    <div class="shell">
      <div class="section__head reveal" data-reveal="1">
        <p class="eyebrow">Tech stack</p>
        <h2 class="h2" id="stackTitle">Four layers, all deliberately boring.</h2>
        <p>No frameworks, no bundler, no CDN at runtime. If it breaks, you can read the whole stack in an
          afternoon &mdash; which is exactly what a panel wants.</p>
      </div>

      <div class="stackgrid">
        <article class="stackgrp reveal" data-reveal="1">
          <div class="stackgrp__head">
            <?= icon('chip') ?>
            <h3>Device</h3>
          </div>
          <div class="stackgrp__list">
            <span class="chip chip--accent">ESP32-WROOM-32</span>
            <span class="chip">Arduino Core</span>
            <span class="chip">DHT22</span>
            <span class="chip">BMP280</span>
            <span class="chip">BH1750</span>
            <span class="chip">DS18B20</span>
          </div>
        </article>

        <article class="stackgrp reveal" data-reveal="2">
          <div class="stackgrp__head">
            <?= icon('wifi') ?>
            <h3>Transport</h3>
          </div>
          <div class="stackgrp__list">
            <span class="chip chip--accent">Wi-Fi 802.11</span>
            <span class="chip">HTTP POST</span>
            <span class="chip">JSON</span>
            <span class="chip">X-Device-Key</span>
            <span class="chip chip--lo">deep sleep 2 s</span>
          </div>
        </article>

        <article class="stackgrp reveal" data-reveal="3">
          <div class="stackgrp__head">
            <?= icon('server') ?>
            <h3>Backend</h3>
          </div>
          <div class="stackgrp__list">
            <span class="chip chip--accent">PHP <?= e(PHP_MAJOR_VERSION) ?>.<?= e(PHP_MINOR_VERSION) ?></span>
            <span class="chip">PDO / MySQL</span>
            <span class="chip">Apache</span>
            <span class="chip">password_hash</span>
            <span class="chip chip--lo">prepared statements</span>
          </div>
        </article>

        <article class="stackgrp reveal" data-reveal="4">
          <div class="stackgrp__head">
            <?= icon('dashboard') ?>
            <h3>Frontend</h3>
          </div>
          <div class="stackgrp__list">
            <span class="chip chip--accent">HTML5</span>
            <span class="chip">CSS custom properties</span>
            <span class="chip">Vanilla ES2020</span>
            <span class="chip">Canvas 2D charts</span>
            <span class="chip chip--lo">no jQuery &middot; no build</span>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- 9. Specs -->
  <section class="section band" aria-labelledby="specTitle">
    <div class="shell">
      <div class="section__head reveal" data-reveal="1">
        <p class="eyebrow">Measured behaviour</p>
        <h2 class="h2" id="specTitle">Three numbers worth quoting.</h2>
      </div>

      <div class="specpanel reveal" data-reveal="1">
        <div class="specpanel__grid">
          <div class="spec">
            <p class="spec__num">2<small>s</small></p>
            <p class="spec__label">Telemetry cadence</p>
            <p class="spec__note">ESP32 deep-sleep wake &rarr; sample &rarr; POST</p>
          </div>
          <div class="spec">
            <p class="spec__num">&lt;120<small>ms</small></p>
            <p class="spec__label">Ingest to first pixel</p>
            <p class="spec__note">PDO insert plus chart point push</p>
          </div>
          <div class="spec">
            <p class="spec__num">30<small>days</small></p>
            <p class="spec__label">Retention window</p>
            <p class="spec__note">Configurable; older rows are pruned by cron</p>
          </div>
        </div>

        <div class="specpanel__foot">
          <p><strong>Methodology.</strong> Cadence is the firmware
            <code>DEEP_SLEEP</code> timer; latency is the median of 100 local ingest requests measured from
            POST start to first chart paint; retention is enforced by a scheduled prune job rather than a
            database event, so the same numbers can be reproduced on a fresh import.</p>
          <span class="specpanel__tag">n = 4 nodes &middot; 48 h window</span>
        </div>
      </div>
    </div>
  </section>

  <!-- 10. FAQ -->
  <section class="section" id="faq" aria-labelledby="faqTitle">
    <div class="shell">
      <div class="section__head reveal" data-reveal="1">
        <p class="eyebrow">Questions</p>
        <h2 class="h2" id="faqTitle">Asked at every viva.</h2>
        <p>Short answers to the five things a panel always wants to know.</p>
      </div>

      <div class="faq reveal" data-reveal="1">
        <div class="faq__item" data-faq data-open="true">
          <h3>
            <button type="button" class="faq__q" data-faq-btn aria-expanded="true" aria-controls="faqPanel1">
              <span class="faq__n">01</span>
              <span class="faq__t">How is the data actually captured?</span>
              <span class="faq__sign" aria-hidden="true"><?= icon('chevron-down', 'ico--sm') ?></span>
            </button>
          </h3>
          <div class="faq__panel" id="faqPanel1" data-faq-panel>
            <div>
              <p class="faq__a">Each ESP32 wakes from deep sleep every two seconds, reads the DHT22, BMP280
                and BH1750 over I<sup>2</sup>C, applies a five-sample moving average, then POSTs a small JSON
                object to <code>api/ingest.php</code> with its <code>device_uid</code> and
                <code>X-Device-Key</code>. The endpoint validates, inserts one row, evaluates the alert rules and
                answers with the new <code>reading_id</code>.</p>
            </div>
          </div>
        </div>

        <div class="faq__item" data-faq>
          <h3>
            <button type="button" class="faq__q" data-faq-btn aria-expanded="false" aria-controls="faqPanel2">
              <span class="faq__n">02</span>
              <span class="faq__t">What happens when a node goes offline?</span>
              <span class="faq__sign" aria-hidden="true"><?= icon('chevron-down', 'ico--sm') ?></span>
            </button>
          </h3>
          <div class="faq__panel" id="faqPanel2" data-faq-panel>
            <div>
              <p class="faq__a">Nothing is lost and nothing crashes. If no packet arrives within
                <strong>60 seconds</strong> the node is marked <em>offline</em> from its
                <code>last_seen_at</code> timestamp, the fleet counter drops, and a critical alert is raised.
                When the node returns, the very next POST flips it back to <em>online</em> &mdash; the ingest
                endpoint updates <code>status</code> and <code>last_seen_at</code> on every successful write.</p>
            </div>
          </div>
        </div>

        <div class="faq__item" data-faq>
          <h3>
            <button type="button" class="faq__q" data-faq-btn aria-expanded="false" aria-controls="faqPanel3">
              <span class="faq__n">03</span>
              <span class="faq__t">Is it secure?</span>
              <span class="faq__sign" aria-hidden="true"><?= icon('chevron-down', 'ico--sm') ?></span>
            </button>
          </h3>
          <div class="faq__panel" id="faqPanel3" data-faq-panel>
            <div>
              <p class="faq__a">Three layers. Device traffic is authenticated per node with a unique 64-character
                key compared in constant time, so a wrong key gets <code>401</code> and no write. Every SQL
                statement is prepared with emulation turned off, so parameters are never interpolated. The web app
                stores bcrypt hashes only, gates actions behind admin / operator / viewer roles, and checks a CSRF
                token on every POST.</p>
            </div>
          </div>
        </div>

        <div class="faq__item" data-faq>
          <h3>
            <button type="button" class="faq__q" data-faq-btn aria-expanded="false" aria-controls="faqPanel4">
              <span class="faq__n">04</span>
              <span class="faq__t">What does a node cost?</span>
              <span class="faq__sign" aria-hidden="true"><?= icon('chevron-down', 'ico--sm') ?></span>
            </button>
          </h3>
          <div class="faq__panel" id="faqPanel4" data-faq-panel>
            <div>
              <p class="faq__a">Around PHP 1,400 per node at typical module prices: roughly a third of that is the
                ESP32, with the sensor set, the 18650 cell, the TP4056 board and the IP65 case making up the rest.
                There is no recurring fee &mdash; the stack is XAMPP on your own machine, and the dashboard needs
                no external service or API key.</p>
            </div>
          </div>
        </div>

        <div class="faq__item" data-faq>
          <h3>
            <button type="button" class="faq__q" data-faq-btn aria-expanded="false" aria-controls="faqPanel5">
              <span class="faq__n">05</span>
              <span class="faq__t">Can I add more nodes later?</span>
              <span class="faq__sign" aria-hidden="true"><?= icon('chevron-down', 'ico--sm') ?></span>
            </button>
          </h3>
          <div class="faq__panel" id="faqPanel5" data-faq-panel>
            <div>
              <p class="faq__a">Yes &mdash; that is the point of the design. Add a row in
                <strong>Devices</strong>, copy the ingest URL and key into the sketch, flash it, and the node
                appears in the fleet within one cadence. Alert rules can be attached to one device or left global,
                so a new node is covered by the same thresholds immediately.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 11. Final CTA -->
  <section class="section" aria-labelledby="ctaTitle">
    <div class="shell">
      <div class="cta reveal" data-reveal="1">
        <p class="eyebrow">Ready when you are</p>
        <h2 id="ctaTitle" style="margin-top: 16px">Start monitoring your environment.</h2>
        <p>Sign in to the live dashboard, watch the charts move in real time, then export the day as CSV.
          Everything runs locally on your own XAMPP install.</p>
        <div class="cta__btns">
          <a class="btn btn--primary btn--lg" href="<?= e(url('dashboard.php')) ?>">
            Launch live dashboard <?= icon('arrow-right', 'ico--sm ico--arrow') ?>
          </a>
          <a class="btn btn--ghost btn--lg" href="<?= e(url('register.php')) ?>">
            <?= icon('user', 'ico--sm') ?> Create an account
          </a>
        </div>
        <p class="cta__note">Demo account &mdash; <code><?= e(url('login.php')) ?></code> with
          <code>demo@ecosense.local</code> / <code>Demo@12345</code></p>
      </div>
    </div>
  </section>

</main>

<!-- 12. Footer -->
<footer class="foot">
  <div class="shell">
    <div class="foot__grid">
      <div>
        <a class="brand" href="<?= e(url('index.php')) ?>">
          <span class="brand__mark" aria-hidden="true"><?= icon('sensor-rings') ?></span>
          <span><?= e(APP_NAME) ?></span>
        </a>
        <p class="foot__blurb">An IoT-based system for real-time weather detection and temperature
          monitoring &mdash; ESP32 sensor nodes, a PHP/MySQL ingest API and a live dashboard, built as a
          working engineering project.</p>
      </div>

      <div class="foot__col">
        <h3>Product</h3>
        <ul class="foot__list">
          <li><a href="<?= e(url('dashboard.php')) ?>">Dashboard</a></li>
          <li><a href="<?= e(url('devices.php')) ?>">Devices</a></li>
          <li><a href="<?= e(url('history.php')) ?>">History</a></li>
          <li><a href="<?= e(url('alerts.php')) ?>">Alerts</a></li>
          <li><a href="<?= e(url('settings.php')) ?>">Settings</a></li>
        </ul>
      </div>

      <div class="foot__col">
        <h3>Resources</h3>
        <ul class="foot__list">
          <li><a href="#architecture">Architecture</a></li>
          <li><a href="#hardware">Bill of materials</a></li>
          <li><a href="#stack">Tech stack</a></li>
          <li><a href="#faq">FAQ</a></li>
          <li><a href="<?= e(url('api/ingest.php')) ?>" class="wrap-any">Ingest API</a></li>
        </ul>
      </div>

      <div class="foot__col">
        <h3>Connect</h3>
        <ul class="foot__list">
          <li><a href="<?= e(url('register.php')) ?>">Create an account</a></li>
          <li><a href="<?= e(url('login.php')) ?>">Sign in</a></li>
          <li><a href="<?= e(url('database/schema.sql')) ?>" class="wrap-any">Database schema</a></li>
          <li><a href="<?= e(url('README.md')) ?>" class="wrap-any">Setup guide</a></li>
        </ul>
      </div>
    </div>

    <div class="foot__bottom">
      <p>&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?> &middot; <?= e(APP_SHORT) ?></p>
      <p class="foot__built"><?= icon('chip', 'ico--xs') ?> Built with ESP32 &middot; PHP <?= e(PHP_MAJOR_VERSION) ?>.<?= e(PHP_MINOR_VERSION) ?> &middot; MySQL</p>
      <p class="foot__status">
        <span class="live-dot<?= $ecoDbUp && $ecoFleetStatus === 'online' ? ' live-dot--good' : ' live-dot--warn' ?>" aria-hidden="true"></span>
        <?= $ecoDbUp && $ecoFleetStatus === 'online' ? 'All systems nominal' : 'Running on simulated telemetry' ?>
      </p>
    </div>
  </div>
</footer>

<?php include __DIR__ . '/includes/flash.php'; ?>
<script src="<?= e(asset('assets/js/landing.js')) ?>" defer></script>
</body>
</html>

