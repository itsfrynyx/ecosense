<?php
require_once __DIR__ . '/includes/auth.php';

require_login();
require_once __DIR__ . '/includes/telemetry.php';

$ecoTitle = 'History';
$ecoNavKey = 'history';
$ecoCrumb = 'Historical data';
$ecoScripts = ['assets/js/chart.js', 'assets/js/pages.js'];
$ecoStyles = ['assets/css/app.css', 'assets/css/pages.css'];

$deviceUid = isset($_GET['device_uid']) ? trim((string)$_GET['device_uid']) : '';
$range = isset($_GET['range']) ? trim((string)$_GET['range']) : '24h';
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$pageSize = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));

$devices = db_all('SELECT id, device_uid, name FROM devices ORDER BY device_uid ASC');
$device = null;
if ($deviceUid !== '') {
    $device = db_one('SELECT id, device_uid FROM devices WHERE device_uid = ?', [$deviceUid]);
}

$historyData = null;
try {
    $historyFilters = telemetry_filters($_GET);
    $range = $historyFilters['range'];
    if ($device) {
        $historyData = telemetry_history((int) $device['id'], $deviceUid, $historyFilters);
        if (db_error() !== '') flash('error', 'History query failed. Check the database setup.');
    }
} catch (InvalidArgumentException $error) {
    flash('error', $error->getMessage());
}

$totalRows = 0;
$totalPages = 1;
$rowStart = 0;
$rowEnd = 0;
if ($historyData && ($historyData['ok'] ?? false)) {
    $historyData['series'] = array_values(array_filter($historyData['series'] ?? [], function ($series) {
        return in_array($series['key'], ['temperature_c', 'humidity_pct', 'rain_detected'], true);
    }));
    $totalRows = count($historyData['labels'] ?? []);
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $rowStart = ($page - 1) * $pageSize;
    $rowEnd = min($rowStart + $pageSize, $totalRows);
}
$pageUrl = function ($number) use ($deviceUid, $range, $from, $to) {
    return url('history.php?' . http_build_query([
        'device_uid' => $deviceUid, 'range' => $range, 'from' => $from, 'to' => $to, 'page' => $number,
    ]));
};

$ecoPageHasH1 = true;
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="main">
  <div class="page-head">
    <div class="page-head__title">
      <h1 class="h1">History</h1>
      <p class="lede">Browse time-series data, view stats, and export to CSV.</p>
    </div>
    <div class="page-head__actions">
      <a class="btn btn--primary" href="<?= e(BASE_URL . 'api/export.php?' . http_build_query(['device_uid'=>$deviceUid,'range'=>$range,'from'=>$historyFilters['from'] ?? $from,'to'=>$historyFilters['to'] ?? $to])) ?>"><?= icon('download') ?> CSV export</a>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <form method="get" class="field__row">
        <div class="field">
          <label for="hist_device">Device</label>
          <select name="device_uid" id="hist_device">
            <option value="">-- Select device --</option>
            <?php foreach ($devices as $d): ?>
            <option value="<?= e($d['device_uid']) ?>" <?= $d['device_uid']===$deviceUid?'selected':'' ?>><?= e($d['device_uid'] . ' - ' . $d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="hist_range">Range</label>
          <select name="range" id="hist_range">
            <?php foreach (['1h'=>'1H','6h'=>'6H','24h'=>'24H','7d'=>'7D'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $k===$range?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="hist_from">From (YYYY-MM-DD HH:MM:SS)</label>
          <input name="from" id="hist_from" value="<?= e($from) ?>">
        </div>
        <div class="field">
          <label for="hist_to">To</label>
          <input name="to" id="hist_to" value="<?= e($to) ?>">
        </div>
        <div class="field" style="flex:1 1 100%">
          <button type="submit" class="btn btn--primary">Apply</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($historyData && ($historyData['ok'] ?? false)): ?>
  <?php if (!empty($historyData['stats'])): ?>
  <div class="grid grid--2">
    <?php foreach ($historyData['series'] as $s): $st = $historyData['stats'][$s['key']] ?? null; ?>
    <div class="card">
      <div class="card__head"><h3><?= e($s['label']) ?></h3></div>
      <div class="card__body">
        <dl class="kv">
          <dt>Min</dt><dd class="mono"><?= $st && $st['min']!==null ? e(number_format((float)$st['min'],2,'.','')) : '—' ?></dd>
          <dt>Avg</dt><dd class="mono"><?= $st && $st['avg']!==null ? e(number_format((float)$st['avg'],2,'.','')) : '—' ?></dd>
          <dt>Max</dt><dd class="mono"><?= $st && $st['max']!==null ? e(number_format((float)$st['max'],2,'.','')) : '—' ?></dd>
        </dl>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card__head"><h3>Data</h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <?php foreach ($historyData['series'] as $s): ?>
            <th class="num"><?= e($s['label']) ?> (<?= e($s['unit']) ?>)</th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php for ($i=$rowStart; $i<$rowEnd; $i++): ?>
          <tr>
            <td class="mono"><?= e($historyData['timestamps'][$i] ?? $historyData['labels'][$i]) ?></td>
            <?php foreach ($historyData['series'] as $s): $v = $s['values'][$i] ?? null; ?>
            <td class="mono num"><?= $v !== null ? ($s['key'] === 'rain_detected' ? ((int) $v === 1 ? 'Wet' : 'Dry') : e(number_format((float)$v,2,'.',''))) : '—' ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endfor; ?>
        <?php if ($totalRows === 0): ?>
          <tr><td colspan="<?= count($historyData['series']) + 1 ?>">No readings in this period.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card__body">
      <nav aria-label="History pagination" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <span class="mono">Showing <?= $totalRows ? $rowStart + 1 : 0 ?>–<?= $rowEnd ?> of <?= $totalRows ?> readings · Page <?= $page ?> of <?= $totalPages ?></span>
        <div style="display:flex;gap:8px">
          <?php if ($page > 1): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e($pageUrl($page - 1)) ?>">Previous</a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e($pageUrl($page + 1)) ?>">Next</a>
          <?php endif; ?>
        </div>
      </nav>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h3>Temperature &amp; humidity over time</h3></div>
    <div class="card__body">
      <div class="legend" aria-label="Chart color legend">
        <?php foreach ($historyData['series'] as $series): if ($series['key'] === 'rain_detected') continue; ?>
          <span class="legend__item">
            <span class="legend__swatch" style="background:<?= e($series['color']) ?>" aria-hidden="true"></span>
            <?= e($series['label']) ?> (<?= e($series['unit']) ?>)
          </span>
        <?php endforeach; ?>
      </div>
      <p class="history-axis-label">Reading value · temperature in °C / humidity in %</p>
      <div id="historyChart" class="chart chart--lg" style="height:320px" role="img" aria-label="Temperature and humidity history" aria-describedby="historyChartGuide"></div>
      <p class="history-axis-label history-axis-label--time">Time recorded (Asia/Manila)</p>
      <p class="history-chart-guide" id="historyChartGuide">Cyan shows temperature; green shows humidity. Read time along the bottom and values on the left. Hover or touch the chart for the exact time and readings. Both lines share a numeric scale but use different units. The chart shows the full selected period across all table pages.</p>
    </div>
  </div>

<div class="card"><div class="card__head"><h3>Rain detection history</h3></div><div class="card__body"><p>0 = dry plate; 1 = wet plate. This sensor does not measure rainfall amount.</p><div id="rainHistoryChart" class="chart chart--lg" style="height:220px"></div></div></div>
  <script>
  document.addEventListener('DOMContentLoaded', function(){
    const ctx = document.getElementById('historyChart');
    if(!ctx || !window.EcoChart) return;
    const allSeries = <?= json_encode($historyData['series']) ?>;
    const series = allSeries.filter(item => item.key !== 'rain_detected');
    const rain = allSeries.filter(item => item.key === 'rain_detected');
    series.forEach(function (item) { item.decimals = 2; });
    const labels = <?= json_encode($historyData['labels']) ?>;
    const tooltipLabels = <?= json_encode($historyData['timestamps'] ?? $historyData['labels']) ?>;
    if (rain.length) EcoChart.line(document.getElementById('rainHistoryChart'), {series: rain, labels, tooltipLabels, yMin: 0, yMax: 1, window: Math.max(2, labels.length), yLabel: 'Rain: 0=dry, 1=wet'});
    EcoChart.line(ctx, {series, labels, tooltipLabels, window: Math.max(2, labels.length), endpointLabels: true, time12Hour: true, xLabel: 'Time recorded (Asia/Manila)', yLabel: 'Value (°C / %)'});
  });
  </script>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
