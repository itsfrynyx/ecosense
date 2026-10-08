<?php
/**
 * Live monitoring dashboard.
 * Server-renders the first frame, then assets/js/dashboard.js polls
 * api/latest.php every 3s. Renders an empty state when MySQL or the
 * ingest API is unreachable instead of failing.
 */

require_once __DIR__ . '/includes/auth.php';

require_login();

$ecoTitle    = 'Live dashboard';
$ecoNavKey   = 'dashboard';
$ecoCrumb    = 'Dashboard';
$ecoBodyClass = 'page--dashboard';
$ecoScripts  = ['assets/js/chart.js', 'assets/js/dashboard.js'];

$ecoDbUp   = db_available();
$ecoDbNote = $ecoDbUp ? '' : db_error();

$ecoWarnHigh = (float) setting('temp_warn_high', TEMP_WARN_HIGH);
$ecoCritHigh = (float) setting('temp_crit_high', TEMP_CRIT_HIGH);
$ecoWarnLow  = (float) setting('temp_warn_low', TEMP_WARN_LOW);
$ecoHumHigh  = (float) setting('humidity_warn_high', HUMIDITY_WARN);
$ecoPresLow  = (float) setting('pressure_warn_low', PRESSURE_LOW);
$ecoBatWarn  = (int) setting('battery_warn_pct', BATTERY_WARN);
$ecoPollMs   = (int) setting('dashboard_poll_ms', DASHBOARD_POLL_MS);
if ($ecoPollMs < 1000) { $ecoPollMs = 3000; }

/* ---- fleet ------------------------------------------------------------- */
$ecoDevices = [];
$ecoAlertRows = [];

/**
 * Newest reading per device.
 * latest_readings() is the shared helper, but it selects d.battery_pct and
 * that column does not exist on devices — battery lives on readings. When the
 * helper comes back empty we query here so the dashboard still renders.
 */
if (!function_exists('eco_latest_readings')) {
    function eco_latest_readings()
    {
        $rows = db_all(
            'SELECT d.id AS device_id, d.device_uid, d.name AS device_name, d.status, d.location,
                    d.last_seen_at, NULL AS device_battery,
                    r.recorded_at, r.temperature_c, r.humidity_pct, r.rain_raw, r.pressure_hpa,
                    r.heat_index_c, r.lux, r.battery_pct, r.signal_dbm
             FROM devices d
             LEFT JOIN readings r ON r.id = (
                 SELECT id FROM readings WHERE device_id = d.id ORDER BY recorded_at DESC, id DESC LIMIT 1
             )
             ORDER BY d.id'
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['device_id']] = $row;
        }
        return $out;
    }
}

if ($ecoDbUp) {
    $ecoDevices = latest_readings();
    if (!$ecoDevices) {
        $ecoDevices = eco_latest_readings();
    }
    try {
        $ecoAlertRows = db_all(
            'SELECT a.id, a.metric, a.value, a.severity, a.message, a.triggered_at, a.acknowledged_at,
                    d.device_uid, d.name AS device_name
             FROM alerts a
             LEFT JOIN devices d ON d.id = a.device_id
             ORDER BY a.triggered_at DESC, a.id DESC
             LIMIT 6'
        );
    } catch (Throwable $ecoAlertErr) {
        $ecoAlertRows = [];
    }
}

$ecoDeviceList = array_values($ecoDevices);
$ecoTotal      = count($ecoDeviceList);
$ecoOnline     = 0;
$ecoHasReading = false;

foreach ($ecoDeviceList as $ecoD) {
    $ecoD['status_effective'] = device_status_from_last_seen($ecoD['last_seen_at'], DEVICE_ONLINE_WINDOW);
    if ($ecoD['status_effective'] === 'online') { $ecoOnline++; }
    if ($ecoD['temperature_c'] !== null) { $ecoHasReading = true; }
}

$ecoPrimaryRow = null;
if ($ecoDeviceList) {
    /* Prefer an online node with data; otherwise whatever exists. */
    usort($ecoDeviceList, function ($a, $b) {
        $rank = function ($r) {
            $s = 0;
            if ($r['status_effective'] === 'online') { $s += 4; }
            if ($r['temperature_c'] !== null) { $s += 2; }
            return $s;
        };
        return $rank($b) <=> $rank($a);
    });
    $ecoPrimaryRow = $ecoDeviceList[0];
}

$ecoUid = $ecoPrimaryRow ? (string) $ecoPrimaryRow['device_uid'] : '';
$ecoDeviceList = array_values($ecoDeviceList); /* keep the sorted order */

/* ---- fleet means + 1h delta ------------------------------------------- */
$ecoMeanTemp = $ecoMeanTempPast = null;
$ecoMeanHum  = $ecoMeanPres = null;
$ecoLastTemp = null;

if ($ecoDbUp) {
    $sumT = $sumH = $sumP = 0;
    $n = 0;
    $newest = null;
    foreach ($ecoDeviceList as $ecoD) {
        if ($ecoD['temperature_c'] === null) { continue; }
        $sumT += (float) $ecoD['temperature_c'];
        $sumH += (float) $ecoD['humidity_pct'];
        $sumP += (float) $ecoD['pressure_hpa'];
        $n++;
        $ts = $ecoD['recorded_at'] ? strtotime((string) $ecoD['recorded_at']) : 0;
        if ($ts && ($newest === null || $ts > $newest['ts'])) {
            $newest = ['ts' => $ts, 'v' => (float) $ecoD['temperature_c']];
        }
    }
    if ($n > 0) {
        $ecoMeanTemp = $sumT / $n;
        $ecoMeanHum  = $sumH / $n;
        $ecoMeanPres = $sumP / $n;
        $ecoLastTemp = $newest['v'];
    }

    if ($n > 0) {
        $past = db_all(
            'SELECT AVG(temperature_c) AS avg_t, MAX(recorded_at) AS newest
             FROM readings
             WHERE recorded_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)
               AND recorded_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)'
        );
        if ($past && is_numeric($past[0]['avg_t'])) {
            $ecoMeanTempPast = (float) $past[0]['avg_t'];
        }
    }
}

$ecoDelta = ($ecoMeanTemp !== null && $ecoMeanTempPast !== null) ? ($ecoMeanTemp - $ecoMeanTempPast) : null;

/* ---- 24h window for the primary node ---------------------------------- */
$ecoSeries = $ecoPrimaryRow ? reading_series((int) $ecoPrimaryRow['device_id'], 24, 600) : [];

$ecoSeed = [
    'devices'    => [],
    'primary'    => $ecoUid,
    'labels'     => [],
    'temperature_c' => [],
    'humidity_pct'  => [],
    'pressure_hpa'  => [],
];

foreach ($ecoDeviceList as $ecoD) {
    $ecoSeed['devices'][] = [
        'uid'        => (string) $ecoD['device_uid'],
        'name'       => (string) $ecoD['name'],
        'location'   => (string) ($ecoD['location'] ?? ''),
        'status'     => (string) $ecoD['status_effective'],
        'last_seen'  => (string) ($ecoD['last_seen_at'] ?? ''),
        'last_ago'   => (int) (is_numeric($ecoD['recorded_at'] ?? null) ? $ecoD['recorded_at'] : (strtotime((string) ($ecoD['last_seen_at'] ?? '')) ?: 0)),
        'battery'    => $ecoD['battery_pct'] === null ? null : (int) $ecoD['battery_pct'],
        'signal'     => $ecoD['signal_dbm'] === null ? null : (int) $ecoD['signal_dbm'],
        'temperature_c' => $ecoD['temperature_c'] === null ? null : (float) $ecoD['temperature_c'],
        'humidity_pct'  => $ecoD['humidity_pct'] === null ? null : (float) $ecoD['humidity_pct'],
        'rain_raw' => $ecoD['rain_raw'] === null ? null : (int) $ecoD['rain_raw'],
        'pressure_hpa'  => $ecoD['pressure_hpa'] === null ? null : (float) $ecoD['pressure_hpa'],
        'heat_index_c'  => $ecoD['heat_index_c'] === null ? null : (float) $ecoD['heat_index_c'],
        'lux'        => $ecoD['lux'] === null ? null : (float) $ecoD['lux'],
        'spark_temp' => [],
        'spark_hum'  => [],
        'spark_pres' => [],
    ];
}

foreach ($ecoSeries as $ecoPoint) {
    $ecoSeed['labels'][]        = $ecoPoint['label'];
    $ecoSeed['temperature_c'][] = $ecoPoint['temperature'];
    $ecoSeed['humidity_pct'][]  = $ecoPoint['humidity'];
    $ecoSeed['pressure_hpa'][]  = $ecoPoint['pressure'];
}

/* Per-node 40-sample sparkline tails, filled in the loop below. */
$ecoTails = [];
if ($ecoDbUp && $ecoTotal > 0) {
    foreach ($ecoDeviceList as $ecoD) {
        $tail = reading_series((int) $ecoD['device_id'], 2, 40);
        $t = $h = $p = [];
        foreach ($tail as $ecoP) {
            $t[] = $ecoP['temperature'];
            $h[] = $ecoP['humidity'];
            $p[] = $ecoP['pressure'];
        }
        $ecoTails[(string) $ecoD['device_uid']] = ['t' => $t, 'h' => $h, 'p' => $p];
    }
}
foreach ($ecoSeed['devices'] as $ecoIdx => $ecoRow) {
    $uid = $ecoRow['uid'];
    $ecoSeed['devices'][$ecoIdx]['spark_temp'] = $ecoTails[$uid]['t'] ?? [];
    $ecoSeed['devices'][$ecoIdx]['spark_hum']  = $ecoTails[$uid]['h'] ?? [];
    $ecoSeed['devices'][$ecoIdx]['spark_pres'] = $ecoTails[$uid]['p'] ?? [];
}

$ecoConfig = [
    'poll'       => $ecoPollMs,
    'latest'     => url('api/latest.php'),
    'history'    => url('api/history.php'),
    'unit'       => $ecoUnit,
    'primary'    => $ecoUid,
    'thresholds' => [
        'tempWarnHigh' => $ecoWarnHigh,
        'tempCritHigh' => $ecoCritHigh,
        'tempWarnLow'  => $ecoWarnLow,
        'humidityHigh' => $ecoHumHigh,
        'pressureLow'  => $ecoPresLow,
        'batteryWarn'  => $ecoBatWarn,
    ],
    'ranges' => ['1h' => '1H', '6h' => '6H', '24h' => '24H', '7d' => '7D'],
    'dbUp'   => $ecoDbUp,
];

$ecoReady = $ecoDbUp && $ecoTotal > 0 && $ecoHasReading;

include __DIR__ . '/includes/header.php';
?>
<main id="main" class="main">

<?php if (!$ecoDbUp): ?>
<div class="notice notice--crit" role="alert">
  <span class="notice__ico" aria-hidden="true"><?= icon('alert-triangle') ?></span>
  <div>
    <p class="notice__title">Database offline</p>
    <p class="notice__body">
      EcoSense could not reach MySQL, so no telemetry can be read. Start the service in
      the XAMPP control panel and reload. The panels below show their empty states until then.
      <?php if ($ecoDbNote !== ''): ?><code><?= e($ecoDbNote) ?></code><?php endif; ?>
    </p>
  </div>
</div>
<?php endif; ?>

<!-- ═══ KPI row ═══════════════════════════════════════════════════════ -->
<section class="grid grid--kpi" aria-label="Key telemetry">
  <!-- 1. Current temperature -->
  <article class="kpi">
    <div class="kpi__main">
      <h2 class="kpi__label"><?= icon('thermometer') ?> Current temperature</h2>
      <p class="kpi__value">
        <span data-kpi="temp" data-temp-c="<?= e((string) ($ecoMeanTemp ?? '')) ?>" data-decimals="1"><?= e(format_temp($ecoMeanTemp, $ecoUnit, 1)) ?></span>
        <span class="kpi__unit" data-temp-unit><?= e(unit_symbol($ecoUnit)) ?></span>
      </p>
      <p class="kpi__foot">
        <?php if ($ecoDelta !== null): ?>
          <span class="delta <?= $ecoDelta > 0.05 ? 'delta--up' : ($ecoDelta < -0.05 ? 'delta--down' : 'delta--flat') ?>">
            <?= icon($ecoDelta > 0.05 ? 'trending-up' : ($ecoDelta < -0.05 ? 'trending-down' : 'minus'), 'ico--xs') ?>
            <span data-temp-delta-c="<?= e((string) round($ecoDelta, 2)) ?>"><?= e(format_temp(abs($ecoDelta), $ecoUnit, 1)) ?></span>
          </span>
          <span>vs 1h ago &middot; fleet mean</span>
        <?php else: ?>
          <span>no 1h baseline</span>
        <?php endif; ?>
      </p>
    </div>
    <div class="kpi__spark" data-spark="temp" data-spark-color="accent" aria-hidden="true"></div>
  </article>

  
  <article class="kpi eco-rain-panel" aria-label="Rain sensor digital wet or dry reading">
    <div class="kpi__main">
      <h2 class="kpi__label"><?= icon('rain') ?> Rain sensor</h2>
      <p class="kpi__value">
        <span id="eco-rain-value">—</span>
      </p>
      <p class="kpi__foot" title="Adjust the sensor module potentiometer to set the wet detection threshold. This detects plate moisture, not a rain forecast.">Digital input · Wet / Dry</p>
      <p class="kpi__foot" id="eco-rain-device" role="status">Waiting for sensor data</p>
    </div>
  </article>
<script>
(function(){
  async function refreshRain(){
    try {
      const r = await fetch('api/latest.php', {cache:'no-store'});
      if (!r.ok) throw new Error('HTTP '+r.status);
      const json = await r.json();
      if (!json.ok) throw new Error('Telemetry unavailable');
      const devices = json.devices || [];
      const d = devices.find(x => x.device_uid === 'ECO-ESP8266-01' && x.rain_detected != null)
        || devices.find(x => x.rain_detected != null)
        || devices.find(x => x.device_uid === 'ECO-ESP8266-01');
      document.getElementById('eco-rain-value').textContent = d && d.rain_detected != null ? (Number(d.rain_detected) === 1 ? 'Wet' : 'Dry') : '—';
      document.getElementById('eco-rain-device').textContent = d
        ? (d.rain_detected == null ? 'Waiting for digital sensor data' : d.name)
        : 'Register a rain sensor node';
    } catch(e) {
      document.getElementById('eco-rain-value').textContent = '—';
      document.getElementById('eco-rain-device').textContent = 'Connection unavailable';
    }
  }
  refreshRain(); setInterval(refreshRain, 5000);
})();
</script>

<!-- 2. Humidity -->
  <article class="kpi kpi--violet">
    <div class="kpi__main">
      <h2 class="kpi__label"><?= icon('droplet') ?> Humidity</h2>
      <p class="kpi__value">
        <span data-kpi="humidity"><?= e(h($ecoMeanHum, 1)) ?></span>
        <span class="kpi__unit">%RH</span>
      </p>
      <p class="kpi__foot">
        <?php if ($ecoMeanHum !== null && $ecoMeanHum > $ecoHumHigh): ?>
          <span class="badge badge--warn"><span class="badge__dot" aria-hidden="true"></span>Above <?= e(h($ecoHumHigh, 0)) ?>%</span>
        <?php else: ?>
          <span>in range &middot; warn &gt;<?= e(h($ecoHumHigh, 0)) ?>%</span>
        <?php endif; ?>
      </p>
    </div>
    <div class="kpi__spark" data-spark="humidity" data-spark-color="violet" aria-hidden="true"></div>
  </article>

  <!-- 3. Pressure -->
  <article class="kpi kpi--good">
    <div class="kpi__main">
      <h2 class="kpi__label"><?= icon('gauge') ?> Pressure</h2>
      <p class="kpi__value">
        <span data-kpi="pressure"><?= e(h($ecoMeanPres, 1)) ?></span>
        <span class="kpi__unit">hPa</span>
      </p>
      <p class="kpi__foot"><span>floor <?= e(h($ecoPresLow, 0)) ?> hPa</span></p>
    </div>
    <div class="kpi__spark" data-spark="pressure" data-spark-color="good" aria-hidden="true"></div>
  </article>

  <!-- 4. Active nodes -->
  <article class="kpi <?= $ecoOnline > 0 ? 'kpi--good' : 'kpi--crit' ?>">
    <div class="kpi__main">
      <h2 class="kpi__label"><?= icon('chip') ?> Active nodes</h2>
      <p class="kpi__value">
        <span data-kpi="nodes"><?= e((string) $ecoOnline) ?></span>
        <span class="kpi__unit">/ <?= e((string) $ecoTotal) ?></span>
      </p>
      <p class="kpi__foot">
        <span data-kpi="nodes-note">reporting within <?= e((string) DEVICE_ONLINE_WINDOW) ?>s</span>
      </p>
    </div>
    <div class="kpi__spark" data-spark="nodes" data-spark-color="<?= $ecoOnline > 0 ? 'good' : 'crit' ?>" aria-hidden="true"></div>
  </article>
</section>

<!-- ═══ Main chart + gauge ════════════════════════════════════════════ -->
<section class="grid grid--main">

  <!-- Live temperature -->
  <div class="card">
    <div class="card__head">
      <div>
        <h2 class="card__title"><?= icon('activity') ?> Live temperature trace</h2>
        <p class="card__sub" data-chart-sub><?= e($ecoUid !== '' ? $ecoUid : 'no node selected') ?></p>
      </div>
      <div class="segmented" role="group" aria-label="Time range">
        <?php foreach (['1h' => '1H', '6h' => '6H', '24h' => '24H', '7d' => '7D'] as $ecoKey => $ecoLabel): ?>
          <button type="button" class="segmented__btn<?= $ecoKey === '24h' ? ' is-on' : '' ?>" data-range="<?= e($ecoKey) ?>" aria-pressed="<?= $ecoKey === '24h' ? 'true' : 'false' ?>"><?= e($ecoLabel) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card__body card__body--flush" style="padding:18px 18px 4px">
      <?php if ($ecoReady): ?>
        <div class="chart chart--xl" data-chart="temperature" role="img" aria-label="Temperature over time for the selected node"></div>
      <?php else: ?>
        <div class="empty" data-empty="chart">
          <span class="empty__ico" aria-hidden="true"><?= icon('activity') ?></span>
          <h3 class="empty__title">No readings yet</h3>
          <p class="empty__body">
            Connect a node or POST a sample to <code>api/simulate.php</code> and the trace appears here within one poll cycle.
          </p>
        </div>
      <?php endif; ?>
    </div>
    <div class="legend" data-legend="temperature">
      <span class="legend__item"><span class="legend__swatch" style="background:var(--accent)"></span>Temperature</span>
    </div>
  </div>

  <!-- Gauge + node selector -->
  <div class="card">
    <div class="card__head">
      <h2 class="card__title"><?= icon('target') ?> Node temperature</h2>
      <p class="card__sub">arc gauge</p>
    </div>
    <div class="card__body">
      <div class="field">
        <label class="field__label" for="nodeSelect"><?= icon('chip') ?> Active node</label>
        <?php if ($ecoTotal > 0): ?>
          <select class="select" id="nodeSelect" data-node-select>
            <?php foreach ($ecoDeviceList as $ecoD): ?>
              <option value="<?= e((string) $ecoD['device_uid']) ?>"<?= (string) $ecoD['device_uid'] === $ecoUid ? ' selected' : '' ?>>
                <?= e($ecoD['name'] . ' — ' . $ecoD['device_uid']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <select class="select" id="nodeSelect" data-node-select disabled aria-describedby="nodeSelectHint">
            <option>No nodes registered</option>
          </select>
        <?php endif; ?>
        <p class="field__hint" id="nodeSelectHint">
          <?= $ecoPrimaryRow ? e((string) $ecoPrimaryRow['location']) : 'Register a node from the Devices page.' ?>
        </p>
      </div>

      <?php if ($ecoReady): ?>
        <div class="gauge-wrap" style="margin-top:14px">
          <div class="gauge" data-gauge role="img" aria-label="Selected node temperature gauge"></div>
        </div>
        <div class="gauge__legend" data-gauge-legend>
          <span>Critical high <b data-temp-c="<?= e((string) $ecoCritHigh) ?>" data-decimals="0"><?= e(format_temp($ecoCritHigh, $ecoUnit, 0)) ?> <?= e(unit_symbol($ecoUnit)) ?></b></span>
          <span>Warning high <b data-temp-c="<?= e((string) $ecoWarnHigh) ?>" data-decimals="0"><?= e(format_temp($ecoWarnHigh, $ecoUnit, 0)) ?> <?= e(unit_symbol($ecoUnit)) ?></b></span>
          <span>Warning low <b data-temp-c="<?= e((string) $ecoWarnLow) ?>" data-decimals="0"><?= e(format_temp($ecoWarnLow, $ecoUnit, 0)) ?> <?= e(unit_symbol($ecoUnit)) ?></b></span>
        </div>
      <?php else: ?>
        <div class="empty" data-empty="gauge" style="padding:30px 12px">
          <span class="empty__ico" aria-hidden="true"><?= icon('target') ?></span>
          <h3 class="empty__title">Gauge idle</h3>
          <p class="empty__body">Waiting for the first packet from a node.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ═══ Humidity + pressure ═══════════════════════════════════════════ -->
<section class="grid grid--split">
  <div class="card">
    <div class="card__head">
      <h2 class="card__title"><?= icon('droplet') ?> Relative humidity</h2>
      <p class="card__sub">% RH</p>
    </div>
    <div class="card__body card__body--flush" style="padding:18px 18px 4px">
      <?php if ($ecoReady): ?>
        <div class="chart chart--lg" data-chart="humidity" role="img" aria-label="Relative humidity over time"></div>
      <?php else: ?>
        <div class="empty" data-empty="humidity" style="padding:34px 12px">
          <span class="empty__ico" aria-hidden="true"><?= icon('droplet') ?></span>
          <p class="empty__body">No humidity samples recorded yet.</p>
        </div>
      <?php endif; ?>
    </div>
    <div class="legend" data-legend="humidity">
      <span class="legend__item"><span class="legend__swatch" style="background:var(--violet)"></span>Humidity</span>
    </div>
  </div>

  <div class="card">
    <div class="card__head">
      <h2 class="card__title"><?= icon('gauge') ?> Barometric pressure</h2>
      <p class="card__sub">hPa</p>
    </div>
    <div class="card__body card__body--flush" style="padding:18px 18px 4px">
      <?php if ($ecoReady): ?>
        <div class="chart chart--lg" data-chart="pressure" role="img" aria-label="Barometric pressure over time"></div>
      <?php else: ?>
        <div class="empty" data-empty="pressure" style="padding:34px 12px">
          <span class="empty__ico" aria-hidden="true"><?= icon('gauge') ?></span>
          <p class="empty__body">No barometric samples recorded yet.</p>
        </div>
      <?php endif; ?>
    </div>
    <div class="legend" data-legend="pressure">
      <span class="legend__item"><span class="legend__swatch" style="background:var(--lime)"></span>Pressure</span>
    </div>
  </div>
</section>

<!-- ═══ Fleet table + alerts ══════════════════════════════════════════ -->
<section class="grid grid--main">
  <div class="card">
    <div class="card__head">
      <h2 class="card__title"><?= icon('devices') ?> Device status</h2>
      <p class="card__sub"><span data-fleet-count><?= e((string) $ecoTotal) ?></span> registered</p>
    </div>
    <div class="card__body card__body--flush">
      <?php if ($ecoTotal > 0): ?>
        <div class="table-wrap">
          <table class="tbl">
            <caption class="visually-hidden">Sensor node status, last contact and latest readings</caption>
            <thead>
              <tr>
                <th scope="col">Node</th>
                <th scope="col">Location</th>
                <th scope="col">Status</th>
                <th scope="col">Last seen</th>
                <th scope="col" class="num">Battery</th>
                <th scope="col" class="num">Signal</th>
                <th scope="col" class="num">Temp</th>
                <th scope="col" class="num"><span class="visually-hidden">Actions</span></th>
              </tr>
            </thead>
            <tbody data-device-rows>
            <?php foreach ($ecoDeviceList as $ecoD):
                $ecoUidCur = (string) $ecoD['device_uid'];
                $ecoBat   = $ecoD['battery_pct'] === null ? null : (int) $ecoD['battery_pct'];
                $ecoBatMod = $ecoBat === null ? '' : ($ecoBat <= $ecoBatWarn ? 'crit' : ($ecoBat <= ($ecoBatWarn * 2) ? 'warn' : ''));
                $ecoSig   = $ecoD['signal_dbm'] === null ? null : (int) $ecoD['signal_dbm'];
                $ecoSigMod = $ecoSig === null ? '' : ($ecoSig < -80 ? 'crit' : ($ecoSig < -65 ? 'warn' : ''));
            ?>
              <tr data-row="<?= e($ecoUidCur) ?>">
                <td>
                  <span class="strong"><?= e($ecoD['name']) ?></span><br>
                  <span class="uid"><?= e($ecoUidCur) ?></span>
                </td>
                <td class="wrap-any"><?= e($ecoD['location'] ?: '—') ?></td>
                <td data-cell="status"><?= status_badge($ecoD['status_effective']) ?></td>
                <td class="num"><span data-cell="seen" data-ago="<?= e((string) ($ecoD['last_seen_at'] ? strtotime((string) $ecoD['last_seen_at']) : 0)) ?>"><?= e(time_ago($ecoD['last_seen_at'])) ?></span></td>
                <td class="num">
                  <?php if ($ecoBat === null): ?>
                    <span class="text-lo">—</span>
                  <?php else: ?>
                    <span class="meter<?= $ecoBatMod ? ' meter--' . $ecoBatMod : '' ?>" style="min-width:96px">
                      <span class="meter__track"><span class="meter__fill" style="width:<?= e((string) max(0, min(100, $ecoBat))) ?>%"></span></span>
                      <span class="meter__val"><?= e((string) $ecoBat) ?>%</span>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="num">
                  <?php if ($ecoSig === null): ?>
                    <span class="text-lo">—</span>
                  <?php else: ?>
                    <span class="badge badge--<?= $ecoSigMod !== '' ? $ecoSigMod : 'good' ?>"><span class="badge__dot" aria-hidden="true"></span><?= e((string) $ecoSig) ?> dBm</span>
                  <?php endif; ?>
                </td>
                <td class="num">
                  <span data-cell="temp" data-temp-c="<?= e((string) ($ecoD['temperature_c'] ?? '')) ?>" data-decimals="1"><?= e(format_temp($ecoD['temperature_c'], $ecoUnit, 1)) ?></span>
                  <span class="kpi__unit" data-temp-unit><?= e(unit_symbol($ecoUnit)) ?></span>
                </td>
                <td class="num">
                  <span class="row-actions">
                    <a class="icon-btn" href="<?= e(url('history.php?device_uid=' . rawurlencode($ecoUidCur))) ?>" aria-label="Open history for <?= e($ecoUidCur) ?>"><?= icon('history') ?></a>
                    <button type="button" class="icon-btn" data-focus-node="<?= e($ecoUidCur) ?>" aria-label="Focus <?= e($ecoUidCur) ?> on the charts"><?= icon('target') ?></button>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty">
          <span class="empty__ico" aria-hidden="true"><?= icon('chip') ?></span>
          <h3 class="empty__title">No nodes registered</h3>
          <p class="empty__body">
            Register an ESP32 node from the Devices page, then post its first sample to
            <code><?= e(url('api/simulate.php')) ?></code>.
          </p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" id="alerts">
    <div class="card__head">
      <h2 class="card__title"><?= icon('alert-triangle') ?> Recent alerts</h2>
      <?php if (is_file(BASE_PATH . 'alerts.php')): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('alerts.php')) ?>">All alerts</a>
      <?php endif; ?>
    </div>
    <div class="card__body card__body--flush">
      <?php if (!empty($ecoAlertRows)): ?>
        <div class="feed">
        <?php foreach ($ecoAlertRows as $ecoA):
            $ecoSev = strtolower((string) ($ecoA['severity'] ?: 'warning'));
            if (!in_array($ecoSev, ['critical', 'warning', 'info'], true)) { $ecoSev = 'warning'; }
            $ecoIco = $ecoSev === 'critical' ? 'alert-triangle' : ($ecoSev === 'info' ? 'info' : 'alert');
        ?>
          <div class="feed__item feed__item--<?= e($ecoSev) ?>">
            <span class="feed__ico" aria-hidden="true"><?= icon($ecoIco) ?></span>
            <div class="feed__main">
              <p class="feed__msg"><?= e($ecoA['message']) ?></p>
              <p class="feed__meta">
                <span class="sev sev--<?= e($ecoSev) ?>"><?= e($ecoSev) ?></span>
                <?php if ($ecoA['device_uid']): ?>
                  <span class="uid"><?= e($ecoA['device_uid']) ?></span>
                <?php elseif ($ecoA['device_name']): ?>
                  <span><?= e($ecoA['device_name']) ?></span>
                <?php endif; ?>
                <?php if ($ecoA['value'] !== null): ?>
                  <span class="mono">value <?= e(h($ecoA['value'], 1)) ?></span>
                <?php endif; ?>
                <?php if (empty($ecoA['acknowledged_at'])): ?>
                  <span class="badge badge--crit"><span class="badge__dot" aria-hidden="true"></span>unread</span>
                <?php endif; ?>
              </p>
            </div>
            <span class="feed__time"><?= e(time_ago($ecoA['triggered_at'])) ?></span>
          </div>
        <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">
          <span class="empty__ico" aria-hidden="true"><?= icon('check-circle') ?></span>
          <h3 class="empty__title">No alerts recorded</h3>
          <p class="empty__body">Threshold rules are quiet. Create one on the Alerts page to watch a metric.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php
$ecoSeedJson = json_encode(
    $ecoSeed,
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
$ecoCfgJson = json_encode(
    $ecoConfig,
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<script type="application/json" id="ecoSeed"><?= $ecoSeedJson ?></script>
<script type="application/json" id="ecoConfig"><?= $ecoCfgJson ?></script>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
