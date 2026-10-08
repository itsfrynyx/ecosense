<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$ecoTitle = 'History';
$ecoNavKey = 'history';
$ecoCrumb = 'Historical data';
$ecoScripts = ['assets/js/chart.js', 'assets/js/pages.js'];
$ecoStyles = ['assets/css/app.css', 'assets/css/pages.css'];

$deviceUid = isset($_GET['device_uid']) ? trim((string)$_GET['device_uid']) : '';
$range = isset($_GET['range']) ? trim((string)$_GET['range']) : '24h';
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$metrics = isset($_GET['metrics']) && is_array($_GET['metrics']) ? $_GET['metrics'] : ['temperature_c','humidity_pct','pressure_hpa'];

$devices = db_all('SELECT id, device_uid, name FROM devices ORDER BY device_uid ASC');
$device = null;
if ($deviceUid !== '') {
    $device = db_one('SELECT id, device_uid FROM devices WHERE device_uid = ?', [$deviceUid]);
}

$historyData = null;
if ($device) {
    $qs = http_build_query(['device_uid'=>$deviceUid, 'range'=>$range, 'from'=>$from, 'to'=>$to]);
    $ctx = stream_context_create(['http'=>['timeout'=>5]]);
    $url = BASE_URL . 'api/history.php?' . $qs;
    $json = @file_get_contents($url, false, $ctx);
    if ($json) {
        $historyData = json_decode($json, true);
    }
}

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
      <a class="btn btn--primary" href="<?= e(BASE_URL . 'api/export.php?' . http_build_query(['device_uid'=>$deviceUid,'from'=>$from,'to'=>$to])) ?>"><?= icon('download') ?> CSV export</a>
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
  <div class="card">
    <div class="card__head">
      <h3>Chart</h3>
    </div>
    <div class="card__body">
      <canvas id="historyChart" height="320"></canvas>
    </div>
  </div>

  <?php if (!empty($historyData['stats'])): ?>
  <div class="grid grid--3">
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
        <?php $n = count($historyData['labels'] ?? []); for ($i=0; $i<$n; $i++): ?>
          <tr>
            <td class="mono"><?= e($historyData['labels'][$i]) ?></td>
            <?php foreach ($historyData['series'] as $s): $v = $s['values'][$i] ?? null; ?>
            <td class="mono num"><?= $v!==null ? e(number_format((float)$v,2,'.','')) : '—' ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function(){
    const ctx = document.getElementById('historyChart');
    if(!ctx || !window.EcoChart) return;
    const series = <?= json_encode($historyData['series']) ?>;
    const labels = <?= json_encode($historyData['labels']) ?>;
    new EcoChart.line(ctx, {series, labels});
  });
  </script>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>