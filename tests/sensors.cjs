// Runs the real PHP endpoints with an isolated database adapter; no live records changed.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const php = process.env.PHP_BINARY || 'C:/xamppni/php/php.exe';
const root = path.resolve(__dirname, '..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'ecosense-sensors-'));
try {
  for (const dir of ['api', 'includes', 'config']) fs.mkdirSync(path.join(tmp, dir));
  for (const file of ['api/ingest.php', 'api/latest.php', 'api/history.php', 'includes/functions.php', 'includes/icons.php', 'includes/auth.php', 'includes/telemetry.php', 'config/config.php']) {
    fs.copyFileSync(path.join(root, file), path.join(tmp, file));
  }
  fs.writeFileSync(path.join(tmp, 'includes/db.php'), `<?php
function fixture() { return json_decode(file_get_contents(__DIR__.'/../fixture.json'), true); }
class SensorStatement {
  private $sql;
  function __construct($sql) { $this->sql = $sql; }
  function execute($args) {
    file_put_contents(__DIR__.'/../writes.jsonl', json_encode([$this->sql, $args])."\\n", FILE_APPEND);
    return true;
  }
}
class SensorDatabase {
  function beginTransaction() { return true; }
  function commit() { return true; }
  function inTransaction() { return false; }
  function rollBack() { return true; }
  function prepare($sql) { return new SensorStatement($sql); }
  function lastInsertId() { return 123; }
}
function db() { return new SensorDatabase(); }
function db_available() { return true; }
function db_one($sql, $args=[]) {
  $f = fixture();
  if (strpos($sql, 'FROM users') !== false) return ['id'=>1, 'name'=>'Test', 'email'=>'test@example.invalid', 'role'=>'admin'];
  if (strpos($sql, 'COUNT(*)') !== false) return ['c'=>0];
  if (strpos($sql, 'FROM devices') !== false) return $f['device'];
  if (strpos($sql, 'FROM readings') !== false) return $f['reading'];
  return null;
}
function db_all($sql, $args=[]) {
  $f = fixture();
  if (strpos($sql, 'FROM alert_rules') !== false) return [['id'=>1, 'metric'=>'rain_detected', 'operator'=>'>=', 'threshold'=>1, 'severity'=>'warning']];
  if (strpos($sql, 'FROM devices') !== false) return [$f['device']];
  if (strpos($sql, 'FROM readings') !== false) return [$f['reading']];
  return [];
}
function db_val($sql, $args=[], $default=null) { return $default; }
function setting($key, $default=null) { return $default; }
function db_error() { return ''; }
`);
  fs.writeFileSync(path.join(tmp, 'run.php'), `<?php
$_SERVER['HTTP_X_DEVICE_KEY'] = 'test-key';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = json_decode($argv[2], true);
$_GET = ['device_uid'=>'TEST-SENSOR'];
require __DIR__.'/includes/auth.php';
auth_start_session();
$_SESSION['user_id'] = 1;
register_shutdown_function(function () { file_put_contents(__DIR__.'/status', (string)(http_response_code() ?: 200)); });
require __DIR__.'/api/'.$argv[1].'.php';
`);
  const fixture = {
    device: {id: 1, device_uid: 'TEST-SENSOR', name: 'Test sensor', api_key: 'test-key', status: 'online', location: 'Test', last_seen_at: '2000-01-01 00:00:00'},
    reading: {recorded_at: '2000-01-01 00:00:00', temperature_c: null, humidity_pct: null, rain_detected: 1, rain_raw: null, heat_index_c: null, signal_dbm: -60}
  };
  fs.writeFileSync(path.join(tmp, 'fixture.json'), JSON.stringify(fixture));
  function run(endpoint, payload={}) {
    fs.writeFileSync(path.join(tmp, 'writes.jsonl'), '');
    const result = spawnSync(php, [path.join(tmp, 'run.php'), endpoint, JSON.stringify(payload)], {encoding:'utf8'});
    assert.equal(result.status, 0, result.stderr);
    const json = JSON.parse(result.stdout);
    const writes = fs.readFileSync(path.join(tmp, 'writes.jsonl'), 'utf8').trim().split('\n').filter(Boolean).map(JSON.parse);
    return {json, writes, status: Number(fs.readFileSync(path.join(tmp, 'status'), 'utf8'))};
  }
  for (const rain of [0, 1, null]) {
    const result = run('ingest', {device_uid:'TEST-SENSOR', temperature_c:null, humidity_pct:null, rain_detected:rain, signal_dbm:-60});
    assert.equal(result.json.ok, true);
    assert.equal(result.json.alerts_triggered, rain === 1 ? 1 : 0);
    const [sql, args] = result.writes[0];
    assert.match(sql, /rain_detected/);
    assert.doesNotMatch(sql, /pressure_hpa|lux|battery_pct/);
    assert.deepEqual(args.slice(2, 6), [null, null, null, rain]);
  }
  for (const rain of [-1, 2, 0.5, 'wet', []]) {
    const result = run('ingest', {device_uid:'TEST-SENSOR', rain_detected:rain});
    assert.equal(result.status, 422);
    assert.equal(result.writes.length, 0);
  }
  const valid = run('ingest', {device_uid:'TEST-SENSOR', temperature_c:28.4, humidity_pct:65, rain_detected:0});
  assert.equal(valid.json.ok, true);
  assert.equal(valid.writes[0][1][2], 28.4);
  assert.equal(valid.writes[0][1][3], 65);
  const invalid = run('ingest', {device_uid:'TEST-SENSOR', temperature_c:81});
  assert.equal(invalid.status, 422);
  const latest = run('latest').json.devices[0];
  assert.equal(latest.status, 'offline');
  assert.equal(latest.rain_detected, 1);
  assert.equal(latest.temperature_c, null);
  assert.deepEqual(latest.series.rain_detected, [1]);
  assert.equal('pressure_hpa' in latest, false);
  const history = run('history').json;
  assert.deepEqual(history.series.map(s=>s.key), ['temperature_c', 'humidity_pct', 'rain_detected']);
  assert.deepEqual(history.series[2].values, [1]);
  assert.equal(history.series[2].stepped, true);
  console.log('PASS: wet/dry/null ingestion, rain alerts, invalid inputs, DHT failure, offline status and rain history. Database adapter mocked.');
} finally {
  if (path.dirname(tmp) === path.resolve(os.tmpdir()) && path.basename(tmp).startsWith('ecosense-sensors-')) fs.rmSync(tmp, {recursive:true});
}
