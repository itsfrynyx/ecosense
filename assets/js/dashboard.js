/* ==========================================================================
   EcoSense dashboard runtime.
   Polls api/latest.php, appends to the live charts, updates KPI tiles and
   device rows, and flips the LIVE chip to amber RECONNECTING on failure.
   ========================================================================== */
(function (win, doc) {
  'use strict';

  var body = doc.body;
  if (!body || !win.EcoChart || body.dataset.dashBound === '1') { return; }
  body.dataset.dashBound = '1';

  function readJson(id, fallback) {
    var node = doc.getElementById(id);
    if (!node) { return fallback; }
    try { return JSON.parse(node.textContent); } catch (err) { return fallback; }
  }

  var SEED = readJson('ecoSeed', { devices: [], labels: [], temperature_c: [], humidity_pct: [], rain_detected: [] });
  var CFG = readJson('ecoConfig', { poll: 3000, ranges: {} });

  var POLL = Math.max(1000, parseInt(CFG.poll, 10) || 3000);
  var TH = CFG.thresholds || {};
  var PAL = win.EcoChart.palette;
  var CHARTS = win.EcoChart.tokens();

  var state = {
    unit: CFG.unit === 'f' ? 'f' : 'c',
    node: CFG.primary || '',
    range: '24h',
    series: {
      labels: (SEED.labels || []).slice(),
      temperature_c: (SEED.temperature_c || []).slice(),
      humidity_pct: (SEED.humidity_pct || []).slice(),
      rain_detected: (SEED.rain_detected || []).slice()
    },
    devices: {},
    connected: !!CFG.dbUp,
    failures: 0,
    timer: 0,
    historyRequest: 0,
    historyPending: null,
    historySample: null,
    inFlight: false
  };

  var RANGE_POINTS = { '1h': 60, '6h': 240, '24h': 360, '7d': 336 };

  (SEED.devices || []).forEach(function (d) { state.devices[d.uid] = d; });

  /* ---- DOM handles ----------------------------------------------------- */
  function q(sel) { return doc.querySelector(sel); }
  function qa(sel) { return Array.prototype.slice.call(doc.querySelectorAll(sel)); }

  var liveChip = q('[data-live-state]');
  var liveText = q('[data-live-text]');
  var chartSub = q('[data-chart-sub]');
  var nodeSelect = q('[data-node-select]');
  var fleetCount = q('[data-fleet-count]');
  var rows = q('[data-device-rows]');

  /* ---- unit math ------------------------------------------------------- */
  function toF(c) { return (c * 9 / 5) + 32; }
  function tempValue(c) { return c == null ? null : (state.unit === 'f' ? toF(c) : c); }
  function temperatureValues() { return state.series.temperature_c.map(tempValue); }
  function disp(c, decimals) {
    if (c === null || c === undefined || c === '') { return '--'; }
    var v = parseFloat(c);
    if (isNaN(v)) { return '--'; }
    return (state.unit === 'f' ? toF(v) : v).toFixed(decimals === undefined ? 1 : decimals);
  }
  function unitSymbol() { return state.unit === 'f' ? '°F' : '°C'; }

  /* ---- KPI ------------------------------------------------------------- */
  var SPARK_COLOR = {
    accent: CHARTS.accent, violet: CHARTS.violet,
    good: CHARTS.good, crit: CHARTS.crit, lime: CHARTS.lime
  };
  var sparks = {};

  function meanOf(field) {
    var sum = 0, n = 0;
    Object.keys(state.devices).forEach(function (uid) {
      var v = state.devices[uid][field];
      if (v === null || v === undefined) { return; }
      sum += parseFloat(v);
      n++;
    });
    return n ? sum / n : null;
  }

  function onlineCount() {
    var n = 0;
    Object.keys(state.devices).forEach(function (uid) {
      if (state.devices[uid].status === 'online') { n++; }
    });
    return n;
  }

  function paintRain() {
    var d = state.devices[state.node];
    var value = doc.getElementById('eco-rain-value');
    var note = doc.getElementById('eco-rain-device');
    if (value) value.textContent = !d || d.rain_detected == null ? 'Unavailable' : (Number(d.rain_detected) === 1 ? 'Rain detected' : 'No rain detected');
    if (note) note.textContent = !state.connected ? 'Connection unavailable; last reading' : d ? d.name + (d.status === 'online' ? '' : ' · offline; last reading') : 'Select a sensor node';
  }

  function paintKpis() {
    paintRain();
    var temps = [], hums = [];
    Object.keys(state.devices).forEach(function (uid) {
      var d = state.devices[uid];
      if (d.humidity_pct != null) hums.push(parseFloat(d.humidity_pct));
      if (d.temperature_c !== null && d.temperature_c !== undefined) {
        temps.push(parseFloat(d.temperature_c));
      }
    });

    var avg = function (a) { return a.length ? a.reduce(function (x, y) { return x + y; }, 0) / a.length : null; };

    var tNode = q('[data-kpi="temp"]');
    if (tNode) { tNode.textContent = disp(avg(temps)); }
    var hNode = q('[data-kpi="humidity"]');
    if (hNode) { hNode.textContent = hums.length ? avg(hums).toFixed(1) : '--'; }

    var nNode = q('[data-kpi="nodes"]');
    var online = onlineCount();
    if (nNode) { nNode.textContent = String(online); }

    var nNote = q('[data-kpi="nodes-note"]');
    if (nNote) {
      nNote.textContent = online > 0
        ? 'reporting within 60s'
        : 'no node reporting in the last 60s';
    }

    pushSpark('temp', temps);
    pushSpark('humidity', hums);
    pushSpark('nodes', nodesTally());
  }

  var nodesTallyCache = [];
  function nodesTally() {
    nodesTallyCache = Object.keys(state.devices).map(function (uid) {
      return state.devices[uid].status === 'online' ? 1 : 0;
    });
    return nodesTallyCache;
  }

  function pushSpark(kind, values) {
    if (!sparks[kind]) { return; }
    var clean = values.filter(function (v) { return v !== null && !isNaN(v); });
    if (state.unit === 'f' && (kind === 'temp')) {
      clean = clean.map(toF);
    }
    sparks[kind].update({ values: clean.slice(-30), color: SPARK_COLOR[sparkColorFor(kind)] || CHARTS.accent });
  }
  function sparkColorFor(kind) {
    var node = q('[data-spark="' + kind + '"]');
    return (node && node.getAttribute('data-spark-color')) || 'accent';
  }

  /* ---- charts ---------------------------------------------------------- */
  var lines = {};
  var gauge = null;

  function seriesConfig() {
    return {
      xLabel: 'Time (Asia/Manila)',
      yLabel: 'Temperature',
      time12Hour: true,
      endpointLabels: true,
      labels: state.series.labels,
      window: RANGE_POINTS[state.range] || 360,
      series: [
        { key: 'temperature_c', label: 'Temperature', unit: unitSymbol(), color: CHARTS.accent, values: temperatureValues(), decimals: 2 }
      ]
    };
  }

  function buildCharts() {
    // Turn the server's initial placeholders into chart hosts, so the first
    // sensor packet can populate an empty dashboard without a page reload.
    [['chart', 'temperature'], ['humidity', 'humidity'], ['gauge', null]].forEach(function (item) {
      var empty = q('[data-empty="' + item[0] + '"]');
      if (!empty) return;
      var host = doc.createElement('div');
      if (item[1]) {
        host.className = 'chart chart--lg';
        host.setAttribute('data-chart', item[1]);
      } else {
        host.className = 'gauge';
        host.setAttribute('data-gauge', '');
      }
      empty.replaceWith(host);
    });
    var t = q('[data-chart="temperature"]');
    if (t) {
      lines.temperature = win.EcoChart.line(t, seriesConfig());
      lines.temperature.opts.window = RANGE_POINTS[state.range];
    }
    var h = q('[data-chart="humidity"]');
    if (h) {
      lines.humidity = win.EcoChart.line(h, {
        xLabel: 'Time (Asia/Manila)',
        yLabel: 'Relative humidity',
        time12Hour: true,
        endpointLabels: true,
        labels: state.series.labels,
        window: RANGE_POINTS[state.range],
        series: [{ key: 'humidity_pct', label: 'Humidity', unit: '%RH', color: CHARTS.violet, values: state.series.humidity_pct, decimals: 2 }]
      });
      lines.humidity.opts.window = RANGE_POINTS[state.range];
    }
    var p = q('[data-chart="rain"]');
    if (p) {
      lines.rain = win.EcoChart.line(p, {
        yMin: 0, yMax: 1, decimals: 2, yLabel: 'Rain detection',
        labels: state.series.labels,
        window: RANGE_POINTS[state.range],
        series: [{ key: 'rain_detected', label: 'Rain', unit: '0=dry, 1=wet', stepped: true, fill: false, color: CHARTS.lime, values: state.series.rain_detected, decimals: 1 }]
      });
      lines.rain.opts.window = RANGE_POINTS[state.range];
    }
    paintGauge();
  }

  function repaintCharts() {
    if (lines.temperature) {
      lines.temperature.opts.labels = state.series.labels;
      lines.temperature.opts.series[0].values = temperatureValues();
      lines.temperature.opts.series[0].unit = unitSymbol();
      lines.temperature.update({ labels: state.series.labels, series: lines.temperature.opts.series, window: RANGE_POINTS[state.range] }, true);
    }
    if (lines.humidity) {
      lines.humidity.opts.series[0].values = state.series.humidity_pct;
      lines.humidity.update({ labels: state.series.labels, series: lines.humidity.opts.series, window: RANGE_POINTS[state.range] }, true);
    }
    if (lines.rain) {
      lines.rain.opts.series[0].values = state.series.rain_detected;
      lines.rain.update({ labels: state.series.labels, series: lines.rain.opts.series, window: RANGE_POINTS[state.range] }, true);
    }
  }

  function paintGauge() {
    var host = q('[data-gauge]');
    if (!host) { return; }
    var dev = state.devices[state.node];
    var temp = dev && dev.temperature_c !== null && dev.temperature_c !== undefined ? parseFloat(dev.temperature_c) : null;

    var opts = {
      value: temp === null || isNaN(temp) ? null : tempValue(temp),
      min: tempValue(-5),
      max: tempValue(45),
      unit: unitSymbol(),
      decimals: 1,
      label: dev ? dev.name : 'no node',
      thresholds: [
        { at: tempValue(Number(TH.tempCritHigh == null ? 38 : TH.tempCritHigh)), color: CHARTS.crit },
        { at: tempValue(Number(TH.tempWarnHigh == null ? 32 : TH.tempWarnHigh)), color: CHARTS.warn },
        { at: tempValue(Number(TH.tempWarnLow == null ? 12 : TH.tempWarnLow)), color: CHARTS.accent }
      ]
    };

    if (gauge) { gauge.update(opts, true); } else { gauge = win.EcoChart.gauge(host, opts); }
  }

  /* ---- device table ---------------------------------------------------- */
  var BADGE = {
    online: ['Online', 'good'],
    offline: ['Offline', 'crit'],
    warning: ['Warning', 'warn'],
    critical: ['Critical', 'crit'],
    maintenance: ['Maintenance', 'info'],
    unknown: ['Unknown', 'idle']
  };

  function badgeHtml(status) {
    var entry = BADGE[status] || BADGE.unknown;
    return '<span class="badge badge--' + entry[1] + '"><span class="badge__dot" aria-hidden="true"></span>' + entry[0] + '</span>';
  }

  function ago(seconds) {
    var s = Math.max(0, Math.round(seconds));
    if (s < 10) { return 'just now'; }
    if (s < 60) { return s + 's ago'; }
    if (s < 3600) { return Math.floor(s / 60) + 'm ago'; }
    if (s < 86400) { return Math.floor(s / 3600) + 'h ago'; }
    return Math.floor(s / 86400) + 'd ago';
  }

  /* Inline stroke icons mirror includes/icons.php so JS-built rows match PHP. */
  var ICONS = {
    history: '<path d="M3.6 12a8.4 8.4 0 1 0 2.6-6.1"/><path d="M3.4 4.6v4.2h4.2"/><path d="M12 7.8V12l3 1.8"/>',
    target: '<circle cx="12" cy="12" r="8.2"/><circle cx="12" cy="12" r="4.2"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>'
  };
  function ico(name) {
    return '<svg class="ico" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" '
      + 'stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
      + (ICONS[name] || '') + '</svg>';
  }

  function ensureRow(d) {
    if (!rows || !d || !d.uid) { return null; }
    var tr = rows.querySelector('[data-row="' + cssEscape(d.uid) + '"]');
    if (tr) { return tr; }

    tr = doc.createElement('tr');
    tr.setAttribute('data-row', d.uid);
    tr.innerHTML =
      '<td><span class="strong"></span><br><span class="uid"></span></td>' +
      '<td class="wrap-any"></td>' +
      '<td data-cell="status"></td>' +
      '<td class="num"><span data-cell="seen">—</span></td>' +
      '<td class="num"><span class="text-lo">—</span></td>' +
      '<td class="num"><span class="text-lo">—</span></td>' +
      '<td class="num"><span data-cell="temp">—</span> <span class="kpi__unit"></span></td>' +
      '<td class="num"><span class="row-actions">' +
      '<a class="icon-btn" href="#">' + ico('history') + '</a>' +
      '<button type="button" class="icon-btn" data-focus-node>' + ico('target') + '</button>' +
      '</span></td>';
    tr.querySelector('[data-focus-node]').setAttribute('data-focus-node', d.uid);
    rows.appendChild(tr);
    bindRowActions(tr);
    return tr;
  }

  function hydrateRowIcons(tr, d) {
    var link = tr.querySelector('.row-actions a');
    if (link) {
      link.setAttribute('aria-label', 'Open history for ' + d.uid);
      link.setAttribute('href', (CFG.historyPage || 'history.php') + '?device_uid=' + encodeURIComponent(d.uid));
    }
    var btn = tr.querySelector('[data-focus-node]');
    if (btn) { btn.setAttribute('aria-label', 'Focus ' + d.uid + ' on the charts'); }
  }

  function bindRowActions(tr) {
    var btn = tr.querySelector('[data-focus-node]');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
      setNode(btn.getAttribute('data-focus-node'), true);
    });
  }

  function paintRow(d) {
    var tr = ensureRow(d);
    if (!tr) { return; }
    hydrateRowIcons(tr, d);

    var nameCell = tr.querySelector('.strong');
    if (nameCell) { nameCell.textContent = d.name; }
    var uidCell = tr.querySelector('.uid');
    if (uidCell) { uidCell.textContent = d.uid; }
    var locCell = tr.children[1];
    if (locCell) { locCell.textContent = d.location || '—'; }

    var statusCell = tr.querySelector('[data-cell="status"]');
    if (statusCell) { statusCell.innerHTML = badgeHtml(d.status); }

    var seenCell = tr.querySelector('[data-cell="seen"]');
    if (seenCell && typeof d.last_seen_ago === 'number') {
      seenCell.textContent = ago(d.last_seen_ago);
    }

    var tempCell = tr.querySelector('[data-cell="temp"]');
    if (tempCell) { tempCell.textContent = disp(d.temperature_c); }
    var unitCell = tr.querySelector('.kpi__unit');
    if (unitCell) { unitCell.textContent = unitSymbol(); }

    var tds = tr.children;
    if (tds[4]) {
      tds[4].textContent = d.rain_detected == null ? '—' : (Number(d.rain_detected) === 1 ? 'Wet' : 'Dry');
    }
    if (tds[5]) {
      tds[5].innerHTML = d.signal_dbm === null || d.signal_dbm === undefined
        ? '<span class="text-lo">—</span>'
        : signalHtml(parseInt(d.signal_dbm, 10));
    }
  }

  function meterHtml(pct, warn) {
    var mod = pct <= warn ? 'crit' : (pct <= warn * 2 ? 'warn' : '');
    return '<span class="meter' + (mod ? ' meter--' + mod : '') + '" style="min-width:96px">'
      + '<span class="meter__track"><span class="meter__fill" style="width:' + Math.max(0, Math.min(100, pct)) + '%"></span></span>'
      + '<span class="meter__val">' + pct + '%</span></span>';
  }

  function signalHtml(dbm) {
    var mod = dbm < -80 ? 'crit' : (dbm < -65 ? 'warn' : 'good');
    return '<span class="badge badge--' + mod + '"><span class="badge__dot" aria-hidden="true"></span>' + dbm + ' dBm</span>';
  }

  function cssEscape(value) {
    if (win.CSS && win.CSS.escape) { return win.CSS.escape(value); }
    return String(value).replace(/["\\]/g, '\\$&');
  }

  function paintDevices() {
    var uids = Object.keys(state.devices);
    /* Pass the device object, not the uid string, or every field goes blank. */
    uids.forEach(function (uid) { paintRow(state.devices[uid]); });
    if (fleetCount) { fleetCount.textContent = String(uids.length); }
    syncNodeSelect(uids);
  }

  function syncNodeSelect(uids) {
    if (!nodeSelect) { return; }
    nodeSelect.disabled = !uids.length;
    Array.prototype.slice.call(nodeSelect.options).forEach(function (option) {
      if (uids.indexOf(option.value) < 0) option.remove();
    });
    var have = {};
    Array.prototype.forEach.call(nodeSelect.options, function (o) { have[o.value] = true; });

    uids.forEach(function (uid) {
      if (have[uid]) { return; }
      var d = state.devices[uid];
      var opt = doc.createElement('option');
      opt.value = uid;
      opt.textContent = d.name + ' — ' + uid;
      nodeSelect.appendChild(opt);
    });
    if (state.node && have[state.node] !== undefined) { nodeSelect.value = state.node; }
  }

  /* ---- node selection -------------------------------------------------- */
  function setNode(uid, refetch) {
    if (!uid) { return; }
    var changed = state.node !== uid;
    state.node = uid;
    if (changed) {
      state.historySample = null;
      state.series = { labels: [], temperature_c: [], humidity_pct: [], rain_detected: [] };
      repaintCharts();
    }
    if (nodeSelect) { nodeSelect.value = uid; }
    if (chartSub) {
      var d = state.devices[uid];
      chartSub.textContent = d ? uid : uid;
    }
    var hint = nodeSelect ? nodeSelect.parentNode.querySelector('.field__hint') : null;
    if (hint && state.devices[uid]) { hint.textContent = state.devices[uid].location || ''; }
    paintGauge();
    paintRain();
    if (refetch) { loadHistory(state.range, uid); }
  }

  /* ---- history fetch --------------------------------------------------- */
  function loadHistory(range, uid) {
    if (!uid) { return; }
    var key = uid + ':' + range;
    if (state.historyPending === key) return;
    state.historyPending = key;
    var request = ++state.historyRequest;
    var sample = state.devices[uid] && state.devices[uid].last_seen_at;
    var url = CFG.history + '?device_uid=' + encodeURIComponent(uid) + '&range=' + encodeURIComponent(range) + '&limit=600';
    win.fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) { throw new Error('history ' + res.status); }
        return res.json();
      })
      .then(function (data) {
        if (!data || data.ok !== true) { throw new Error('bad payload'); }
        if (request !== state.historyRequest) return;
        state.historyPending = null;
        if (uid !== state.node || range !== state.range) return;
        state.historySample = key + ':' + sample;
        applyHistory(data);
      })
      .catch(function () {
        if (request === state.historyRequest) state.historyPending = null;
      });
  }

  function applyHistory(data) {
    var series = {};
    (data.series || []).forEach(function (s) { series[s.key] = s.values || []; });

    state.series.labels = data.labels || [];
    state.series.temperature_c = series.temperature_c || [];
    state.series.humidity_pct = series.humidity_pct || [];
    state.series.rain_detected = series.rain_detected || [];

    if (chartSub) { chartSub.textContent = (data.device_uid || state.node) + ' · ' + (data.range || state.range); }
    repaintCharts();
  }

  /* ---- range tabs ------------------------------------------------------ */
  qa('[data-range]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      qa('[data-range]').forEach(function (b) {
        b.classList.toggle('is-on', b === btn);
        b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
      });
      state.range = btn.getAttribute('data-range');
      if (lines.temperature) { lines.temperature.opts.window = RANGE_POINTS[state.range]; }
      if (lines.humidity) { lines.humidity.opts.window = RANGE_POINTS[state.range]; }
      if (lines.rain) { lines.rain.opts.window = RANGE_POINTS[state.range]; }
      loadHistory(state.range, state.node);
    });
  });

  if (nodeSelect) {
    nodeSelect.addEventListener('change', function () {
      setNode(nodeSelect.value, true);
    });
  }

  /* ---- connection state ------------------------------------------------ */
  function setConnection(ok) {
    state.connected = ok;
    paintRain();
    if (!liveChip || !liveText) { return; }
    liveChip.classList.toggle('is-warn', !ok);
    liveChip.classList.toggle('is-off', false);
    liveText.textContent = ok ? 'LIVE' : 'RECONNECTING';
  }

  /* ---- poll ------------------------------------------------------------ */
  function poll() {
    if (state.inFlight) { return; }
    state.inFlight = true;

    win.fetch(CFG.latest + (CFG.latest.indexOf('?') < 0 ? '?' : '&') + 'series=0', { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) { throw new Error('latest ' + res.status); }
        return res.json();
      })
      .then(function (data) {
        state.inFlight = false;
        if (!data || data.ok !== true) { throw new Error('bad payload'); }
        ingest(data);
        state.failures = 0;
        setConnection(true);
      })
      .catch(function () {
        state.inFlight = false;
        state.failures++;
        setConnection(false);
      });
  }

  function ingest(data) {
    var nextDevices = {};
    (data.devices || []).forEach(function (raw) {
      /* api/latest.php keys the identifier as device_uid; the seed uses uid. */
      var d = raw;
      if (!d.uid && d.device_uid) {
        d = {};
        for (var k in raw) {
          if (Object.prototype.hasOwnProperty.call(raw, k)) { d[k] = raw[k]; }
        }
        d.uid = raw.device_uid;
      }
      if (!d.uid) { return; }
      nextDevices[d.uid] = d;
    });

    state.devices = nextDevices;
    if (rows) qa('[data-device-rows] tr[data-row]').forEach(function (row) {
      if (!nextDevices[row.getAttribute('data-row')]) row.remove();
    });
    var live = data.devices || [];
    if (!nextDevices[state.node]) {
      state.node = '';
      state.historySample = null;
      state.series = { labels: [], temperature_c: [], humidity_pct: [], rain_detected: [] };
      repaintCharts();
      if (live.length) setNode(live[0].device_uid, true);
    }
    var focus = state.devices[state.node];

    // Refresh the selected period only when that node reports a new reading.
    if (focus && state.historySample !== state.node + ':' + state.range + ':' + focus.last_seen_at) {
      loadHistory(state.range, state.node);
    }
    paintKpis();
    paintDevices();
    paintGauge();

    if (typeof data.units === 'string' && (data.units === 'c' || data.units === 'f') && data.units !== state.unit) {
      state.unit = data.units;
      body.setAttribute('data-unit', state.unit);
      if (win.EcoUnits) { win.EcoUnits.apply(state.unit, true); }
      repaintCharts();
      paintGauge();
    }
  }

  /* ---- unit re-render -------------------------------------------------- */
  doc.addEventListener('eco:unit', function (ev) {
    state.unit = (ev.detail && ev.detail.unit) === 'f' ? 'f' : 'c';
    var temps = [], hums = [], pres = [];
    Object.keys(state.devices).forEach(function (uid) {
      var d = state.devices[uid];
      if (d.humidity_pct != null) hums.push(parseFloat(d.humidity_pct));
      if (d.temperature_c === null || d.temperature_c === undefined) { return; }
      temps.push(parseFloat(d.temperature_c));
    });
    var avg = function (a) { return a.length ? a.reduce(function (x, y) { return x + y; }, 0) / a.length : null; };

    var tNode = q('[data-kpi="temp"]');
    if (tNode) { tNode.textContent = disp(avg(temps)); }
    var hNode = q('[data-kpi="humidity"]');
    if (hNode) { hNode.textContent = hums.length ? avg(hums).toFixed(1) : '--'; }

    Object.keys(state.devices).forEach(function (uid) { paintRow(state.devices[uid]); });
    paintKpis();
    repaintCharts();
    paintGauge();
  });

  /* ---- boot ------------------------------------------------------------ */
  qa('[data-spark]').forEach(function (host) {
    var kind = host.getAttribute('data-spark');
    sparks[kind] = win.EcoChart.sparkline(host, { values: [], color: SPARK_COLOR[host.getAttribute('data-spark-color')] || CHARTS.accent });
  });

  buildCharts();
  qa('[data-device-rows] tr[data-row]').forEach(bindRowActions);
  paintKpis();
  paintDevices();
  setNode(state.node, true);

  if (state.connected) {
    poll();
    state.timer = win.setInterval(poll, POLL);
  } else {
    setConnection(false);
    /* Retry anyway: MySQL may be restarted while the page is open. */
    state.timer = win.setInterval(poll, 5000);
    poll();
  }

  doc.addEventListener('visibilitychange', function () {
    if (doc.hidden) {
      win.clearInterval(state.timer);
      state.timer = 0;
    } else if (!state.timer) {
      poll();
      state.timer = win.setInterval(poll, POLL);
    }
  });
}(window, document));
