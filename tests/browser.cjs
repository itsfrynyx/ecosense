// Uses the installed Edge browser and built-in Node APIs; no npm dependencies.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const php = process.env.PHP_BINARY || 'C:/xamppni/php/php.exe';
const base = process.env.ECOSENSE_TEST_URL || 'http://localhost/ecosenseV.2.1/';
const edge = process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const pause = ms=>new Promise(r=>setTimeout(r, ms));
function fixture(mode, data={}) {
  const p = spawnSync(php, [path.join(__dirname, 'live-fixture.php'), mode], {input:JSON.stringify(data), encoding:'utf8'});
  assert.equal(p.status, 0, p.stderr);
  return mode === 'cleanup' ? p.stdout : JSON.parse(p.stdout);
}
let f, child, ws, profile, session, nextId=0;
const pending = new Map();
const errors = [];
function call(method, params={}, sessionId=session) {
  const id = ++nextId;
  return new Promise((resolve, reject)=>{
    const timer = setTimeout(()=>{pending.delete(id); reject(new Error('CDP timeout: '+method));}, 10000);
    pending.set(id, {resolve:v=>{clearTimeout(timer); resolve(v);}, reject});
    ws.send(JSON.stringify({id, method, params, ...(sessionId ? {sessionId} : {})}));
  });
}
async function evaluate(expression) {
  const result = await call('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
  if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
  return result.result.value;
}
async function until(expression, message) {
  for (let i=0;i<60;i++) { if (await evaluate(expression)) return; await pause(150); }
  throw new Error('Browser wait failed: '+message);
}
(async()=>{
  try {
    f = fixture('create');
    let cookie = '';
    async function req(route, options={}) {
      const r = await fetch(new URL(route, base), {...options, redirect:'manual', headers:{Cookie:cookie, ...options.headers}});
      for (const c of r.headers.getSetCookie()) if(c.startsWith('ECOSESSID=')) cookie=c.split(';')[0];
      return r;
    }
    const login = await (await req('login.php')).text();
    const csrf = login.match(/name="csrf_token" value="([a-f0-9]+)"/)[1];
    assert.equal((await req('login.php', {method:'POST', body:new URLSearchParams({csrf_token:csrf, email:f.emails.admin, password:f.password})})).status, 302);
    assert.equal((await req('api/ingest.php', {method:'POST', headers:{'Content-Type':'application/json', 'X-Device-Key':f.key}, body:JSON.stringify({device_uid:f.uid, temperature_c:28, humidity_pct:60, rain_detected:1})})).status, 200);
    profile = fs.mkdtempSync(path.join(os.tmpdir(), 'ecosense-edge-'));
    child = spawn(edge, ['--headless=new', '--no-first-run', '--disable-gpu', '--remote-debugging-port=0', '--user-data-dir='+profile, 'about:blank'], {windowsHide:true, stdio:'ignore'});
    const portFile = path.join(profile, 'DevToolsActivePort');
    for (let i=0;i<100 && !fs.existsSync(portFile);i++) await pause(100);
    assert.ok(fs.existsSync(portFile), 'Edge debugger must start');
    const [port, socketPath] = fs.readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
    ws = new WebSocket('ws://127.0.0.1:'+port+socketPath);
    ws.addEventListener('message', event=>{
      const m=JSON.parse(event.data);
      if (m.id && pending.has(m.id)) {
        const p=pending.get(m.id); pending.delete(m.id);
        if(m.error) p.reject(new Error(JSON.stringify(m.error))); else p.resolve(m.result);
      }
      if(m.method==='Runtime.exceptionThrown') errors.push(m.params.exceptionDetails);
    });
    await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true}); ws.addEventListener('error',reject,{once:true});});
    const target = await call('Target.createTarget', {url:'about:blank'}, null);
    session = (await call('Target.attachToTarget',{targetId:target.targetId, flatten:true},null)).sessionId;
    await call('Page.enable'); await call('Runtime.enable'); await call('Network.enable');
    await call('Network.setCookie', {name:'ECOSESSID', value:cookie.split('=')[1], url:base, path:new URL(base).pathname});
    await call('Page.addScriptToEvaluateOnNewDocument', {source:`
      window.__testCharts = [];
      let charts;
      Object.defineProperty(window, 'EcoChart', {configurable:true, get(){return charts;}, set(value){
        charts=value;
        for (const key of ['line','gauge']) {
          const original=value[key];
          value[key]=function(...args){ const chart=original.apply(this,args); window.__testCharts.push({key,chart,kind:args[0].getAttribute('data-chart')}); return chart; };
        }
      }});
    `});
    await call('Page.navigate',{url:new URL('dashboard.php',base).href});
    await until('window.__testCharts && window.__testCharts.length >= 4', 'charts initialize');
    await evaluate(`document.querySelector('[data-node-select]').value=${JSON.stringify(f.uid)}; document.querySelector('[data-node-select]').dispatchEvent(new Event('change'));`);
    await until(`document.querySelector('[data-chart-sub]').textContent.includes(${JSON.stringify(f.uid)}) && window.__testCharts.find(x=>x.kind==='temperature').chart.opts.series[0].values.length===3`, 'fixture history loaded');
    await evaluate(`document.querySelector('[data-range="6h"]').click()`);
    await until(`document.querySelector('[data-chart-sub]').textContent.includes('6h')`, '6h selection');
    await pause(4200);
    const afterPoll = await evaluate(`window.__testCharts.find(x=>x.kind==='temperature').chart.opts.series[0].values`);
    assert.equal(afterPoll.length,3,'Polling must retain the selected 6-hour range');
    assert.equal(afterPoll[0],22,'The 2-hour-old sample must remain visible');
    await evaluate(`document.querySelector('[data-unit-set="f"]').click()`);
    let value=await evaluate(`window.__testCharts.find(x=>x.kind==='temperature').chart.opts.series[0].values.at(-1)`);
    assert.ok(Math.abs(value-82.4)<0.001, 'Chart must convert Celsius values to Fahrenheit');
    value=await evaluate(`window.__testCharts.find(x=>x.key==='gauge').chart.opts.value`);
    assert.ok(Math.abs(value-82.4)<0.001, 'Gauge must use the same unit');
    await pause(4200);
    assert.equal(await evaluate(`document.body.getAttribute('data-unit')`),'f','Polling must preserve the chosen unit');
    await call('Page.reload');
    await until(`document.readyState === 'complete' && document.body.getAttribute('data-unit') === 'f' && window.__testCharts && window.__testCharts.length >= 4`, 'Fahrenheit preference survives reload');
    await evaluate(`document.querySelector('[data-node-select]').value=${JSON.stringify(f.uid)}; document.querySelector('[data-node-select]').dispatchEvent(new Event('change'));`);
    await until(`window.__testCharts.find(x=>x.kind==='temperature').chart.opts.series[0].values.length===3`, 'reloaded fixture history');
    await evaluate(`document.querySelector('[data-unit-set="c"]').click()`);
    assert.equal(await evaluate(`window.__testCharts.find(x=>x.key==='gauge').chart.opts.value`),28);
    assert.equal(await evaluate(`document.getElementById('eco-rain-value').textContent`),'Rain detected');
    await call('Page.navigate',{url:new URL('alerts.php',base).href});
    await until('document.readyState === "complete" && document.querySelector("[data-edit-rule]")', 'alerts page');
    await evaluate(`[...document.querySelectorAll('[data-edit-rule]')].find(btn=>JSON.parse(btn.getAttribute('data-edit-rule')).device_uid===${JSON.stringify(f.uid)}).click()`);
    assert.equal(await evaluate(`document.getElementById('modalRuleEdit').hidden`),false);
    assert.equal(await evaluate(`document.querySelector('#modalRuleEdit [name="metric"]').value`),'rain_detected');
    assert.deepEqual(errors,[], 'Browser must have no uncaught JavaScript errors');
    console.log('PASS: Edge dashboard rendering, selected range after polling, Fahrenheit chart/gauge values, rain status and alert edit modal.');
  } finally {
    if(ws && ws.readyState===WebSocket.OPEN) { try {await call('Browser.close',{},null);} catch{} ws.close(); }
    if(child) child.kill();
    if(f) console.log(fixture('cleanup',f));
    if(profile && path.dirname(profile)===path.resolve(os.tmpdir()) && path.basename(profile).startsWith('ecosense-edge-')) {
      try {fs.rmSync(profile,{recursive:true,maxRetries:10,retryDelay:200});} catch{}
    }
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
