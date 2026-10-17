/* ==========================================================================
   EcoChart — dependency-free canvas telemetry charts.
   EcoChart.line()  multi-series smoothed line chart with crosshair tooltip
   EcoChart.sparkline()  40px inline trend line
   EcoChart.gauge()  240 degree arc gauge with threshold markers
   devicePixelRatio aware, ResizeObserver driven, reduced-motion aware.
   ========================================================================== */
(function (win, doc) {
  'use strict';

  if (!win || !doc) { return; }

  var REDUCED = win.matchMedia ? win.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  var DPR_CAP = 2.5;

  function reduced() { return !!REDUCED.matches; }

  /* ---- tokens ---------------------------------------------------------- */
  function token(name, fallback) {
    var v = win.getComputedStyle(doc.documentElement).getPropertyValue(name);
    return v ? String(v).trim() : fallback;
  }

  var T = {};
  function refreshTokens() {
    T.accent = token('--accent', '#22D3EE');
    T.accentDeep = token('--accent-deep', '#0891B2');
    T.good = token('--good', '#34D399');
    T.warn = token('--warn', '#FBBF24');
    T.crit = token('--crit', '#F87171');
    T.violet = token('--violet', '#A78BFA');
    T.lime = token('--lime', '#A3E635');
    T.hi = token('--text-hi', '#E8EDF4');
    T.mid = token('--text-mid', '#9BA7B8');
    T.lo = token('--text-lo', '#64707F');
    T.inset = token('--bg-inset', '#0A0D11');
    T.surface = token('--bg-surface', '#11151C');
    T.raised = token('--bg-raised', '#161B24');
    T.hair = token('--line-hair', 'rgba(148,163,184,0.10)');
    T.strong = token('--line-strong', 'rgba(148,163,184,0.18)');
  }
  refreshTokens();

  var PALETTE = ['#22D3EE', '#A78BFA', '#A3E635', '#FBBF24', '#34D399', '#F87171'];

  /* ---- helpers --------------------------------------------------------- */
  function isNum(v) { return typeof v === 'number' && isFinite(v); }

  function numeric(values) {
    var out = [];
    for (var i = 0; i < (values || []).length; i++) {
      var v = values[i];
      out.push(isNum(+v) && v !== null && v !== '' ? +v : null);
    }
    return out;
  }

  function traceSteps(ctx, pts) {
    if (!pts.length) return;
    ctx.moveTo(pts[0].x, pts[0].y);
    for (var i = 1; i < pts.length; i++) {
      if (pts[i].index !== pts[i - 1].index + 1) { ctx.moveTo(pts[i].x, pts[i].y); continue; }
      ctx.lineTo(pts[i].x, pts[i - 1].y);
      ctx.lineTo(pts[i].x, pts[i].y);
    }
  }

  function extent(series) {
    var lo = Infinity, hi = -Infinity;
    for (var s = 0; s < series.length; s++) {
      var vals = series[s].values || [];
      for (var i = 0; i < vals.length; i++) {
        var v = vals[i];
        if (v === null || v === undefined) { continue; }
        if (v < lo) { lo = v; }
        if (v > hi) { hi = v; }
      }
    }
    if (lo === Infinity) { lo = 0; hi = 1; }
    if (lo === hi) { lo -= 1; hi += 1; }
    var pad = (hi - lo) * 0.14;
    return { lo: lo - pad, hi: hi + pad };
  }

  function fmt(value, decimals) {
    if (value === null || value === undefined || !isFinite(value)) { return '--'; }
    var d = decimals === undefined ? 1 : decimals;
    var n = Math.abs(value) >= 100 ? Math.round(value) : Number(value.toFixed(d));
    return String(n);
  }

  function prepCanvas(canvas) {
    var rect = canvas.getBoundingClientRect();
    var w = Math.max(1, Math.round(rect.width));
    var h = Math.max(1, Math.round(rect.height));
    var dpr = Math.min(win.devicePixelRatio || 1, DPR_CAP);
    canvas.width = Math.round(w * dpr);
    canvas.height = Math.round(h * dpr);
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    return { ctx: ctx, w: w, h: h, dpr: dpr };
  }

  /* Monotone cubic (Fritsch–Carlson) so telemetry never overshoots. */
  function tracePath(ctx, pts) {
    var n = pts.length;
    if (n === 0) { return; }
    if (n === 1) { ctx.moveTo(pts[0].x, pts[0].y); return; }
    if (n === 2) {
      ctx.moveTo(pts[0].x, pts[0].y);
      ctx.lineTo(pts[1].x, pts[1].y);
      return;
    }

    var dx = [], slope = [], tan = [], i;
    for (i = 0; i < n - 1; i++) {
      dx[i] = pts[i + 1].x - pts[i].x || 1e-6;
      slope[i] = (pts[i + 1].y - pts[i].y) / dx[i];
    }
    tan[0] = slope[0];
    tan[n - 1] = slope[n - 2];
    for (i = 1; i < n - 1; i++) {
      if (slope[i - 1] * slope[i] <= 0) {
        tan[i] = 0;
      } else {
        var w1 = 2 * dx[i] + dx[i - 1];
        var w2 = dx[i] + 2 * dx[i - 1];
        tan[i] = (w1 + w2) / (w1 / slope[i - 1] + w2 / slope[i]);
      }
    }

    ctx.moveTo(pts[0].x, pts[0].y);
    for (i = 0; i < n - 1; i++) {
      var seg = dx[i] / 3;
      ctx.bezierCurveTo(
        pts[i].x + seg, pts[i].y + tan[i] * seg,
        pts[i + 1].x - seg, pts[i + 1].y - tan[i + 1] * seg,
        pts[i + 1].x, pts[i + 1].y
      );
    }
  }

  /* Gradient from a hex colour + alpha. */
  function alpha(hex, a) {
    var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(String(hex).trim());
    if (!m) { return 'rgba(34,211,238,' + a + ')'; }
    return 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')';
  }

  function autoResize(el, draw) {
    if (!win.ResizeObserver) {
      win.addEventListener('resize', draw, { passive: true });
      return function () { win.removeEventListener('resize', draw); };
    }
    var ro = new win.ResizeObserver(function () { draw(); });
    ro.observe(el);
    return function () { ro.disconnect(); };
  }

  function roundRect(ctx, x, y, w, h, r) {
    var rr = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + rr, y);
    ctx.lineTo(x + w - rr, y);
    ctx.arcTo(x + w, y, x + w, y + rr, rr);
    ctx.lineTo(x + w, y + h - rr);
    ctx.arcTo(x + w, y + h, x + w - rr, y + h, rr);
    ctx.lineTo(x + rr, y + h);
    ctx.arcTo(x, y + h, x, y + h - rr, rr);
    ctx.lineTo(x, y + rr);
    ctx.arcTo(x, y, x + rr, y, rr);
    ctx.closePath();
  }

  function makeCanvas(host) {
    var canvas = doc.createElement('canvas');
    canvas.setAttribute('role', 'img');
    canvas.style.display = 'block';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    host.appendChild(canvas);
    return canvas;
  }

  /* ====================================================================== */
  /*  LINE CHART                                                            */
  /* ====================================================================== */
  function Line(el, opts) {
    this.el = typeof el === 'string' ? doc.querySelector(el) : el;
    if (!this.el) { return; }
    this.el.classList.add('chart');
    this.canvas = this.el.querySelector('canvas') || makeCanvas(this.el);
    this.tip = this.el.querySelector('.chart__tip');
    if (!this.tip) {
      this.tip = doc.createElement('div');
      this.tip.className = 'chart__tip';
      this.tip.setAttribute('role', 'presentation');
      this.el.appendChild(this.tip);
    }
    this.opts = opts || {};
    this.hover = -1;
    this.progress = reduced() ? 1 : 0;
    this.raf = 0;
    this.geometry = null;

    var self = this;
    this._onMove = function (ev) { self._pointer(ev, true); };
    this._onLeave = function () { self.hover = -1; self.tip.classList.remove('is-on'); self._draw(); };
    this.el.addEventListener('mousemove', this._onMove);
    this.el.addEventListener('mouseleave', this._onLeave);
    this.el.addEventListener('touchstart', this._onMove, { passive: true });
    this.el.addEventListener('touchmove', this._onMove, { passive: true });
    this.el.addEventListener('touchend', this._onLeave);

    this.stopResize = autoResize(this.el, function () { self._draw(); });

    this.update(this.opts, true);
    if (!reduced()) { this._animate(); }
  }

  Line.prototype._animate = function () {
    var self = this;
    var start = win.performance ? win.performance.now() : Date.now();
    var dur = 720;
    cancelAnimationFrame(this.raf);
    var step = function (now) {
      var t = Math.min(1, (now - start) / dur);
      var eased = 1 - Math.pow(1 - t, 3);
      self.progress = eased;
      self._draw();
      if (t < 1) { self.raf = requestAnimationFrame(step); }
      else { self.progress = 1; self.raf = 0; }
    };
    this.progress = 0;
    this.raf = requestAnimationFrame(step);
  };

  /** Merge new options and redraw. Keeps the hover index in range. */
  Line.prototype.update = function (opts, skipAnim) {
    opts = opts || {};
    var labels = opts.labels || this.opts.labels || [];
    var incoming = opts.series || this.opts.series || [];

    this.opts = {
      labels: labels,
      tooltipLabels: opts.tooltipLabels !== undefined ? opts.tooltipLabels : this.opts.tooltipLabels,
      series: (incoming || []).map(function (s, i) {
        return {
          key: s.key || ('series' + i),
          label: s.label || ('Series ' + (i + 1)),
          unit: s.unit || '',
          color: s.color || PALETTE[i % PALETTE.length],
          decimals: s.decimals,
          values: numeric(s.values),
          fill: !!s.fill,
          dashed: !!s.dashed,
          stepped: !!s.stepped
        };
      }),
      yUnit: opts.yUnit !== undefined ? opts.yUnit : (this.opts.yUnit || ''),
      yMin: opts.yMin !== undefined ? opts.yMin : this.opts.yMin,
      yMax: opts.yMax !== undefined ? opts.yMax : this.opts.yMax,
      decimals: opts.decimals !== undefined ? opts.decimals : this.opts.decimals,
      endpointLabels: opts.endpointLabels !== undefined ? opts.endpointLabels : !!this.opts.endpointLabels,
      xLabel: opts.xLabel !== undefined ? opts.xLabel : this.opts.xLabel,
      yLabel: opts.yLabel !== undefined ? opts.yLabel : this.opts.yLabel,
      time12Hour: opts.time12Hour !== undefined ? opts.time12Hour : !!this.opts.time12Hour,
      emptyText: opts.emptyText || 'No readings in this window'
    };

    if (this.hover > this.opts.labels.length - 1) { this.hover = -1; }
    if (!skipAnim && !reduced() && this.progress >= 1) { this._animate(); }
    this._draw();
    return this;
  };

  /** Append one sample to every series (live streaming). */
  Line.prototype.push = function (label, values) {
    var labels = this.opts.labels.slice();
    labels.push(label);
    if (labels.length > this.opts.window) { labels = labels.slice(labels.length - this.opts.window); }

    this.opts.series.forEach(function (s, i) {
      var v = values && values[s.key] !== undefined ? values[s.key] : null;
      s.values.push(v === null || v === '' ? null : +v);
      if (s.values.length > this.opts.window) {
        s.values = s.values.slice(s.values.length - this.opts.window);
      }
    }, this);

    this.opts.labels = labels;
    this.progress = reduced() ? 1 : this.progress;
    this._draw();
    return this;
  };

  Line.prototype._pointer = function (ev, active) {
    var g = this.geometry;
    if (!g || !this.opts.labels.length) { return; }
    var rect = this.canvas.getBoundingClientRect();
    var clientX = ev.clientX !== undefined ? ev.clientX : (ev.touches && ev.touches[0] ? ev.touches[0].clientX : 0);
    var x = clientX - rect.left;

    var step = g.plotW / Math.max(1, g.count - 1);
    var idx = g.count > 1 ? Math.round(x / step) : 0;
    if (idx < 0) { idx = 0; }
    if (idx > g.count - 1) { idx = g.count - 1; }

    if (idx !== this.hover) {
      this.hover = active ? idx : -1;
      this._draw();
    }
  };

  Line.prototype._draw = function () {
    if (!this.canvas) { return; }
    var s = prepCanvas(this.canvas);
    var ctx = s.ctx;
    var W = s.w, H = s.h;
    var series = this.opts.series || [];
    var labels = this.opts.labels || [];

    ctx.font = '10px ' + (token('--font-mono', 'ui-monospace, monospace') || 'ui-monospace, monospace');
    ctx.textBaseline = 'middle';

    var padT = 14, padR = 12, padB = 22, padL = 46;
    if (this.opts.xLabel) padB = 44;
    if (this.opts.yLabel) padL = 68;
    var formatTime = function (label) {
      var text = String(label);
      if (!this.opts.time12Hour) return text;
      return text.replace(/(^|[ T])(\d{1,2}):(\d{2})(:\d{2})?(?=$|\s)/, function (match, prefix, hour, minute, seconds) {
        var h = Number(hour);
        if (h > 23) return match;
        return prefix + (h % 12 || 12) + ':' + minute + (seconds || '') + (h >= 12 ? ' PM' : ' AM');
      });
    }.bind(this);
    var endpoints = [];
    if (this.opts.endpointLabels) {
      series.forEach(function (ser) {
        for (var i = Math.min(ser.values.length, labels.length) - 1; i >= 0; i--) {
          if (!isNum(ser.values[i])) continue;
          var valueText = fmt(ser.values[i], ser.decimals !== undefined ? ser.decimals : this.opts.decimals);
          var text = ser.label + ': ' + valueText + (ser.unit ? ' ' + ser.unit : '');
          endpoints.push({ series: ser, index: i, value: ser.values[i], text: text });
          padR = Math.max(padR, ctx.measureText(text).width + 28);
          break;
        }
      }, this);
    }
    var plotW = Math.max(10, W - padL - padR);
    var plotH = Math.max(10, H - padT - padB);

    /* y range */
    var range;
    if (isNum(this.opts.yMin) && isNum(this.opts.yMax)) {
      range = { lo: this.opts.yMin, hi: this.opts.yMax };
    } else {
      range = extent(series);
    }

    var X = function (i) { return labels.length > 1 ? padL + (plotW * i) / (labels.length - 1) : padL + plotW / 2; };
    var Y = function (v) { return padT + plotH - ((v - range.lo) / (range.hi - range.lo)) * plotH; };

    this.geometry = { plotW: plotW, plotH: plotH, count: labels.length, padL: padL, padT: padT };

    /* ---- plot well ---- */
    ctx.fillStyle = T.inset;
    roundRect(ctx, 0.5, 0.5, W - 1, H - 1, 8);
    ctx.fill();
    ctx.strokeStyle = T.hair;
    ctx.lineWidth = 1;
    ctx.stroke();

    /* ---- horizontal grid + y labels ---- */
    ctx.textAlign = 'right';
    for (var g = 0; g <= 4; g++) {
      var frac = g / 4;
      var y = padT + plotH * frac;
      ctx.beginPath();
      ctx.moveTo(padL, Math.round(y) + 0.5);
      ctx.lineTo(W - padR, Math.round(y) + 0.5);
      ctx.strokeStyle = g === 4 ? T.hair : 'rgba(148,163,184,0.055)';
      ctx.stroke();

      var value = range.hi - (range.hi - range.lo) * frac;
      ctx.fillStyle = T.lo;
      ctx.fillText(fmt(value, this.opts.decimals), padL - 8, y);
    }

    /* ---- vertical grid on a light touch ---- */
    ctx.textAlign = 'center';
    var tickEvery = Math.max(1, Math.ceil(labels.length / 6));
    for (var t = 0; t < labels.length; t += tickEvery) {
      var tx = Math.round(X(t)) + 0.5;
      ctx.beginPath();
      ctx.moveTo(tx, padT);
      ctx.lineTo(tx, padT + plotH);
      ctx.strokeStyle = 'rgba(148,163,184,0.045)';
      ctx.stroke();
      ctx.fillStyle = T.lo;
      ctx.fillText(formatTime(labels[t]), tx, padT + plotH + 12);
    }

    ctx.save();
    ctx.fillStyle = T.lo;
    ctx.textAlign = 'center';
    if (this.opts.xLabel) ctx.fillText(this.opts.xLabel, padL + plotW / 2, H - 8);
    if (this.opts.yLabel) {
      ctx.translate(14, padT + plotH / 2);
      ctx.rotate(-Math.PI / 2);
      var axisUnit = series.length === 1 && series[0].unit ? ' (' + series[0].unit + ')' : '';
      ctx.fillText(this.opts.yLabel + axisUnit, 0, 0);
    }
    ctx.restore();

    if (!series.length || !labels.length) {
      ctx.fillStyle = T.lo;
      ctx.textAlign = 'center';
      ctx.font = '12px ' + (token('--font-body', 'system-ui, sans-serif') || 'system-ui');
      ctx.fillText(this.opts.emptyText, W / 2, H / 2);
      return;
    }

    /* ---- series ---- */
    ctx.save();
    ctx.beginPath();
    ctx.rect(0, 0, W * this.progress, H);
    ctx.clip();

    var primary = null;

    series.forEach(function (ser, si) {
      var pts = [];
      for (var i = 0; i < ser.values.length && i < labels.length; i++) {
        var v = ser.values[i];
        if (v === null || v === undefined) { continue; }
        pts.push({ x: X(i), y: Y(v), index: i });
      }
      if (pts.length < 1) { return; }
      if (si === 0) { primary = { ser: ser, pts: pts }; }

      /* gradient area under the first series only */
      if (si === 0 && ser.fill !== false) {
        var grad = ctx.createLinearGradient(0, padT, 0, padT + plotH);
        grad.addColorStop(0, alpha(ser.color, 0.24));
        grad.addColorStop(1, alpha(ser.color, 0));
        ctx.beginPath();
        if (ser.stepped) traceSteps(ctx, pts); else tracePath(ctx, pts);
        ctx.lineTo(pts[pts.length - 1].x, padT + plotH);
        ctx.lineTo(pts[0].x, padT + plotH);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();
      }

      ctx.beginPath();
      if (ser.stepped) traceSteps(ctx, pts); else tracePath(ctx, pts);
      ctx.strokeStyle = ser.color;
      ctx.lineWidth = 1.8;
      ctx.lineJoin = 'round';
      ctx.lineCap = 'round';
      if (ser.dashed) { ctx.setLineDash([4, 4]); } else { ctx.setLineDash([]); }
      ctx.stroke();
      ctx.setLineDash([]);

      /* leading dot on the newest sample */
      var last = pts[pts.length - 1];
      ctx.beginPath();
      ctx.arc(last.x, last.y, 2.6, 0, Math.PI * 2);
      ctx.fillStyle = ser.color;
      ctx.fill();
      ctx.beginPath();
      ctx.arc(last.x, last.y, 5.4, 0, Math.PI * 2);
      ctx.strokeStyle = alpha(ser.color, 0.28);
      ctx.lineWidth = 1;
      ctx.stroke();
    });

    /* hover marker travels over the whole set */
    if (this.hover >= 0 && this.hover < labels.length) {
      var hx = Math.round(X(this.hover)) + 0.5;
      ctx.beginPath();
      ctx.moveTo(hx, padT);
      ctx.lineTo(hx, padT + plotH);
      ctx.strokeStyle = alpha(T.accent, 0.42);
      ctx.lineWidth = 1;
      ctx.setLineDash([3, 3]);
      ctx.stroke();
      ctx.setLineDash([]);

      series.forEach(function (ser) {
        var v = ser.values[this.hover];
        if (v === null || v === undefined) { return; }
        ctx.beginPath();
        ctx.arc(hx, Y(v), 3.6, 0, Math.PI * 2);
        ctx.fillStyle = ser.color;
        ctx.fill();
        ctx.strokeStyle = T.inset;
        ctx.lineWidth = 1.6;
        ctx.stroke();
      });
    }

    ctx.restore();

    if (this.opts.endpointLabels && this.progress >= 1) {
      endpoints.forEach(function (point) { point.y = Y(point.value); });
      endpoints.sort(function (a, b) { return a.y - b.y; });
      endpoints.forEach(function (point, index) {
        point.labelY = Math.max(padT + 8, point.y);
        if (index) point.labelY = Math.max(point.labelY, endpoints[index - 1].labelY + 22);
      });
      for (var e = endpoints.length - 1; e >= 0; e--) {
        endpoints[e].labelY = Math.min(endpoints[e].labelY, padT + plotH - 8);
        if (e < endpoints.length - 1) endpoints[e].labelY = Math.min(endpoints[e].labelY, endpoints[e + 1].labelY - 22);
      }
      ctx.save();
      ctx.textAlign = 'left';
      ctx.textBaseline = 'middle';
      endpoints.forEach(function (point) {
        var labelX = padL + plotW + 16;
        ctx.beginPath();
        ctx.moveTo(X(point.index) + 5, point.y);
        ctx.lineTo(labelX - 5, point.labelY);
        ctx.strokeStyle = alpha(point.series.color, 0.6);
        ctx.lineWidth = 1;
        ctx.stroke();
        ctx.fillStyle = point.series.color;
        ctx.fillText(point.text, labelX, point.labelY);
      });
      ctx.restore();
    }

    /* ---- tooltip ---- */
    if (this.hover >= 0 && this.hover < labels.length) {
      var tooltipLabel = this.opts.tooltipLabels && this.opts.tooltipLabels[this.hover] || labels[this.hover];
      var html = '<p class="chart__tip-label">' + escapeHtml(formatTime(tooltipLabel)) + '</p>';
      series.forEach(function (ser) {
        var v = ser.values[this.hover];
        html += '<p class="chart__tip-row">'
          + '<span class="chart__swatch" style="background:' + ser.color + '"></span>'
          + '<span>' + escapeHtml(ser.label) + (ser.unit ? ' (' + escapeHtml(ser.unit) + ')' : '') + '</span>'
          + '<b>' + fmt(v, ser.decimals !== undefined ? ser.decimals : this.opts.decimals) + '</b></p>';
      }, this);
      this.tip.innerHTML = html;
      this.tip.classList.add('is-on');

      var tipW = this.tip.offsetWidth || 150;
      var left = X(this.hover) + 14;
      if (left + tipW > W - 4) { left = X(this.hover) - tipW - 14; }
      if (left < 4) { left = 4; }
      this.tip.style.left = left + 'px';
      this.tip.style.top = padT + 6 + 'px';
    } else {
      this.tip.classList.remove('is-on');
    }

    if (primary) { this.lastPrimary = primary.ser; }
  };

  Line.prototype.destroy = function () {
    cancelAnimationFrame(this.raf);
    if (this.stopResize) { this.stopResize(); }
    this.el.removeEventListener('mousemove', this._onMove);
    this.el.removeEventListener('mouseleave', this._onLeave);
    if (this.tip && this.tip.parentNode) { this.tip.parentNode.removeChild(this.tip); }
  };

  function escapeHtml(str) {
    return String(str === null || str === undefined ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /* ====================================================================== */
  /*  SPARKLINE                                                             */
  /* ====================================================================== */
  function Sparkline(el, opts) {
    this.el = typeof el === 'string' ? doc.querySelector(el) : el;
    if (!this.el) { return; }
    this.canvas = this.el.querySelector('canvas') || makeCanvas(this.el);
    this.opts = opts || {};
    var self = this;
    this.stopResize = autoResize(this.el, function () { self._draw(); });
    this._draw();
  }

  Sparkline.prototype.update = function (opts) {
    this.opts = opts || {};
    this._draw();
    return this;
  };

  Sparkline.prototype.push = function (value) {
    var vals = (this.opts.values || []).concat([value]);
    if (vals.length > (this.opts.window || 30)) {
      vals = vals.slice(vals.length - (this.opts.window || 30));
    }
    this.opts.values = vals;
    this._draw();
    return this;
  };

  Sparkline.prototype._draw = function () {
    var vals = numeric(this.opts.values || []);
    if (!this.canvas) { return; }
    var s = prepCanvas(this.canvas);
    var ctx = s.ctx;
    var W = s.w, H = s.h;
    var color = this.opts.color || T.accent;

    ctx.strokeStyle = T.hair;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(0, Math.round(H / 2) + 0.5);
    ctx.lineTo(W, Math.round(H / 2) + 0.5);
    ctx.stroke();

    var pts = [];
    for (var i = 0; i < vals.length; i++) {
      if (vals[i] === null) { continue; }
      pts.push({ x: vals.length > 1 ? (W * i) / (vals.length - 1) : W, v: vals[i] });
    }
    if (pts.length < 2) {
      if (pts.length === 1) {
        ctx.beginPath();
        ctx.arc(pts[0].x, H / 2, 2, 0, Math.PI * 2);
        ctx.fillStyle = alpha(color, 0.6);
        ctx.fill();
      }
      return;
    }

    var lo = Infinity, hi = -Infinity;
    for (var j = 0; j < pts.length; j++) {
      if (pts[j].v < lo) { lo = pts[j].v; }
      if (pts[j].v > hi) { hi = pts[j].v; }
    }
    var pad = (hi - lo) * 0.2 || 1;
    lo -= pad; hi += pad;

    var py = function (v) { return H - 3 - ((v - lo) / (hi - lo)) * (H - 6); };
    var mapped = pts.map(function (p) { return { x: p.x, y: py(p.v) }; });

    var grad = ctx.createLinearGradient(0, 0, 0, H);
    grad.addColorStop(0, alpha(color, 0.22));
    grad.addColorStop(1, alpha(color, 0));

    ctx.beginPath();
    tracePath(ctx, mapped);
    ctx.lineTo(mapped[mapped.length - 1].x, H);
    ctx.lineTo(mapped[0].x, H);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();

    ctx.beginPath();
    tracePath(ctx, mapped);
    ctx.strokeStyle = color;
    ctx.lineWidth = 1.4;
    ctx.lineJoin = 'round';
    ctx.stroke();

    var tail = mapped[mapped.length - 1];
    ctx.beginPath();
    ctx.arc(tail.x, tail.y, 2, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();
  };

  Sparkline.prototype.destroy = function () {
    if (this.stopResize) { this.stopResize(); }
  };

  /* ====================================================================== */
  /*  GAUGE                                                                 */
  /* ====================================================================== */
  function Gauge(el, opts) {
    this.el = typeof el === 'string' ? doc.querySelector(el) : el;
    if (!this.el) { return; }
    this.canvas = this.el.querySelector('canvas') || makeCanvas(this.el);
    this.opts = opts || {};
    this.progress = reduced() ? 1 : 0;
    this.raf = 0;
    var self = this;
    this.stopResize = autoResize(this.el, function () { self._draw(); });
    if (!reduced()) { this._animate(); } else { this._draw(); }
  }

  Gauge.prototype._animate = function () {
    var self = this;
    var start = win.performance ? win.performance.now() : Date.now();
    cancelAnimationFrame(this.raf);
    var step = function (now) {
      var t = Math.min(1, (now - start) / 820);
      self.progress = 1 - Math.pow(1 - t, 3);
      self._draw();
      if (t < 1) { self.raf = requestAnimationFrame(step); }
      else { self.progress = 1; self.raf = 0; }
    };
    this.progress = 0;
    this.raf = requestAnimationFrame(step);
  };

  Gauge.prototype.update = function (opts, skipAnim) {
    this.opts = opts || {};
    if (!skipAnim && !reduced()) { this._animate(); } else { this.progress = 1; this._draw(); }
    return this;
  };

  Gauge.prototype._draw = function () {
    if (!this.canvas) { return; }
    var rect = this.canvas.getBoundingClientRect();
    var W = Math.max(80, Math.round(rect.width || 240));
    // Size from width so the full lower arc remains visible after resizing.
    var H = Math.round(W * 0.90);

    var dpr = Math.min(win.devicePixelRatio || 1, DPR_CAP);
    this.canvas.width = Math.round(W * dpr);
    this.canvas.height = Math.round(H * dpr);
    this.canvas.style.height = H + 'px';
    var ctx = this.canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, W, H);

    var min = isNum(this.opts.min) ? this.opts.min : 0;
    var max = isNum(this.opts.max) ? this.opts.max : 50;
    var hasValue = isNum(this.opts.value);
    var value = hasValue ? this.opts.value : min;

    var cx = W / 2;
    var cy = H * 0.51;
    var r = Math.max(24, Math.min(W / 2 - 30, H * 0.40));
    var lw = Math.max(9, r * 0.20);
    var start = Math.PI * 0.78;          /* 240 degree sweep */
    var sweep = Math.PI * 1.44;

    var clamp = function (v) { return Math.max(min, Math.min(max, v)); };
    var frac = max === min ? 0 : (clamp(value) - min) / (max - min);

    /* track */
    ctx.beginPath();
    ctx.lineCap = 'round';
    ctx.lineWidth = lw;
    ctx.strokeStyle = T.raised;
    ctx.arc(cx, cy, r, start, start + sweep);
    ctx.stroke();

    /* warn band */
    var marks = this.opts.thresholds || [];
    for (var m = 0; m < marks.length; m++) {
      var mk = marks[m];
      if (!isNum(mk.at) || mk.at < min || mk.at > max) { continue; }
      var a = start + ((mk.at - min) / (max - min)) * sweep;
      var inner = r - lw / 2 - 5;
      var outer = r - lw / 2 + 5;
      ctx.beginPath();
      ctx.lineWidth = 2;
      ctx.strokeStyle = mk.color || T.warn;
      ctx.shadowColor = mk.color || T.warn;
      ctx.shadowBlur = 8;
      ctx.moveTo(cx + Math.cos(a) * inner, cy + Math.sin(a) * inner);
      ctx.lineTo(cx + Math.cos(a) * outer, cy + Math.sin(a) * outer);
      ctx.stroke();
      ctx.shadowBlur = 0;
    }

    /* value arc */
    var shown = frac * this.progress;
    if (shown > 0.001) {
      var end = start + sweep * shown;
      var grad = ctx.createLinearGradient(cx - r, cy - r, cx + r, cy);
      grad.addColorStop(0, T.accentDeep);
      grad.addColorStop(1, T.accent);
      ctx.beginPath();
      ctx.lineWidth = lw;
      ctx.strokeStyle = grad;
      ctx.shadowColor = alpha(T.accent, 0.5);
      ctx.shadowBlur = 14;
      ctx.arc(cx, cy, r, start, end);
      ctx.stroke();
      ctx.shadowBlur = 0;

      /* needle */
      var na = start + sweep * shown;
      ctx.beginPath();
      ctx.lineCap = 'round';
      ctx.lineWidth = 2;
      ctx.strokeStyle = T.hi;
      ctx.moveTo(cx + Math.cos(na) * (r - lw), cy + Math.sin(na) * (r - lw));
      ctx.lineTo(cx + Math.cos(na) * (r - lw - 12), cy + Math.sin(na) * (r - lw - 12));
      ctx.stroke();

      ctx.beginPath();
      ctx.arc(cx + Math.cos(na) * (r - lw - 12), cy + Math.sin(na) * (r - lw - 12), 3, 0, Math.PI * 2);
      ctx.fillStyle = T.hi;
      ctx.fill();
    }

    /* min / max labels */
    var mono = token('--font-mono', 'ui-monospace, monospace');
    ctx.font = '9px ' + mono;
    ctx.fillStyle = T.lo;
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'center';
    ctx.fillText(fmt(min, 0) + (this.opts.unit || ''), cx + Math.cos(start) * r, cy + Math.sin(start) * r + lw / 2 + 16);
    ctx.fillText(fmt(max, 0) + (this.opts.unit || ''), cx + Math.cos(start + sweep) * r, cy + Math.sin(start + sweep) * r + lw / 2 + 16);

    /* readout */
    ctx.textAlign = 'center';
    ctx.fillStyle = T.hi;
    ctx.font = '600 ' + Math.max(18, Math.round(r * 0.40)) + 'px ' + mono;
    ctx.fillText(hasValue ? fmt(value, this.opts.decimals !== undefined ? this.opts.decimals : 1) : '--', cx, cy - r * 0.30);
    ctx.fillStyle = T.lo;
    ctx.font = '10px ' + mono;
    ctx.fillText(this.opts.unit || '', cx, cy - r * 0.30 + Math.max(16, r * 0.30));
    if (this.opts.label) {
      ctx.fillStyle = T.mid;
      ctx.font = '10px ' + token('--font-body', 'system-ui, sans-serif');
      ctx.fillText(String(this.opts.label), cx, cy - r * 0.30 + Math.max(32, r * 0.56));
    }
  };

  Gauge.prototype.destroy = function () {
    cancelAnimationFrame(this.raf);
    if (this.stopResize) { this.stopResize(); }
  };

  /* ====================================================================== */
  win.EcoChart = {
    line: function (el, opts) {
      var inst = new Line(el, opts);
      if (el) { el.__ecoLine = inst; }
      return inst;
    },
    sparkline: function (el, opts) {
      var inst = new Sparkline(el, opts);
      if (el) { el.__ecoSpark = inst; }
      return inst;
    },
    gauge: function (el, opts) {
      var inst = new Gauge(el, opts);
      if (el) { el.__ecoGauge = inst; }
      return inst;
    },
    palette: PALETTE,
    refresh: function () { refreshTokens(); },
    tokens: function () { return T; }
  };
}(window, document));
