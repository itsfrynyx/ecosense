const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const path = require('node:path');
const php = process.env.PHP_BINARY || 'C:/xamppni/php/php.exe';
const base = process.env.ECOSENSE_TEST_URL || 'http://localhost/ecosenseV.2.1/';
function fixture(mode, data={}) {
  const p = spawnSync(php, [path.join(__dirname, 'live-fixture.php'), mode], {input:JSON.stringify(data), encoding:'utf8'});
  assert.equal(p.status, 0, p.stderr);
  return mode === 'cleanup' ? p.stdout : JSON.parse(p.stdout);
}
const f = fixture('create');
async function client() {
  let cookie = '';
  return async (route, options={}) => {
    const r = await fetch(new URL(route, base), {...options, redirect:'manual', headers:{Cookie:cookie, ...options.headers}});
    for (const c of r.headers.getSetCookie()) if (c.startsWith('ECOSESSID=')) cookie = c.split(';')[0];
    return r;
  };
}
async function login(role) {
  const req = await client();
  const page = await (await req('login.php')).text();
  const csrf = page.match(/name="csrf_token" value="([a-f0-9]+)"/)[1];
  const response = await req('login.php', {method:'POST', body:new URLSearchParams({csrf_token:csrf, email:f.emails[role], password:f.password})});
  assert.equal(response.status, 302, 'Login must succeed');
  return req;
}
(async () => {
  try {
    const anon = await client();
    assert.equal((await anon('api/latest.php')).status, 401);
    assert.equal((await anon('api/history.php?device_uid='+f.uid)).status, 401);
    assert.equal((await anon('api/ingest.php')).status, 405);
    const admin = await login('admin');
    const post = payload => anon('api/ingest.php', {method:'POST', headers:{'Content-Type':'application/json', 'X-Device-Key':f.key}, body:JSON.stringify({device_uid:f.uid, ...payload})});
    assert.equal((await post({rain_detected:2})).status, 422);
    assert.equal((await post({temperature_c:28, humidity_pct:60, rain_detected:1, signal_dbm:-58})).status, 200);
    let stored = fixture('inspect', f);
    assert.equal(Number(stored.rain_detected), 1);
    assert.ok(Number(stored.heat_index_c) > 28 && Number(stored.heat_index_c) < 32, 'Heat index must not be inflated');
    assert.equal((await post({temperature_c:null, humidity_pct:null, rain_detected:0})).status, 200);
    stored = fixture('inspect', f);
    assert.equal(stored.temperature_c, null);
    assert.equal(stored.heat_index_c, null);
    const query = new URLSearchParams({device_uid:f.uid, range:'6h', from:f.early, to:f.early});
    const history = await (await admin('api/history.php?'+query)).json();
    assert.equal(history.ok, true);
    assert.equal(history.labels.length, 1);
    assert.deepEqual(history.series[0].values, [22]);
    const csv = await (await admin('api/export.php?'+query)).text();
    assert.equal(csv.trim().split(/\r?\n/).length, 2);
    assert.match(csv, new RegExp(f.uid));
    const empty = await (await admin('api/history.php?'+new URLSearchParams({device_uid:f.uid, from:'1999-01-01 00:00:00', to:'1999-01-02 00:00:00'}))).json();
    assert.equal(empty.labels.length, 0, 'Empty periods must not return unrelated old data');
    assert.equal((await admin('api/history.php?device_uid='+f.uid+'&from=invalid')).status, 422);
    assert.equal((await admin('api/export.php?device_uid=UNKNOWN-TEST-NODE')).status, 404);
    for (const page of ['dashboard.php', 'devices.php', 'history.php?'+query, 'alerts.php', 'settings.php']) {
      const response = await admin(page);
      assert.equal(response.status, 200, page);
      const html = await response.text();
      assert.doesNotMatch(html, /Fatal error|Warning:|Parse error/);
      if (page === 'devices.php') assert.match(html, /O&#039;Brien test node/);
      if (page.startsWith('history.php')) assert.match(html, /22\.00/);
    }
    const viewer = await login('viewer');
    assert.equal((await viewer('api/simulate.php', {method:'POST'})).status, 403);
    assert.equal((await viewer('api/ack_alert.php', {method:'POST'})).status, 403);
    const viewerDevices = await (await viewer('api/devices.php')).json();
    assert.equal(viewerDevices.devices.some(d=>'api_key' in d), false);
    const html = await (await viewer('devices.php')).text();
    assert.equal(html.includes(f.key), false);
    console.log('PASS: live MySQL/Apache login, ingest, failed-sensor fields, heat index, history dates, CSV, page rendering and viewer permissions.');
  } finally {
    console.log(fixture('cleanup', f));
  }
})().catch(error=>{ console.error(error); process.exitCode=1; });
