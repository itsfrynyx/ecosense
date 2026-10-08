/* EcoSense — landing page interactivity.
   Vanilla ES2020, no dependencies. Everything freezes under prefers-reduced-motion. */
(function () {
  'use strict';

  var doc = document;
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  function isReduced() {
    return reduceMotion.matches;
  }

  function $(sel, scope) {
    return (scope || doc).querySelector(sel);
  }

  function $$(sel, scope) {
    return Array.prototype.slice.call((scope || doc).querySelectorAll(sel));
  }

  function clamp(v, min, max) {
    return v < min ? min : (v > max ? max : v);
  }

  /* ---- scroll progress + sticky nav state ------------------------------ */
  function initScrollChrome() {
    var bar = $('[data-progress]');
    var nav = $('[data-nav]');
    var queued = false;

    function paint() {
      queued = false;
      var max = doc.documentElement.scrollHeight - window.innerHeight;
      var ratio = max > 0 ? clamp(window.scrollY / max, 0, 1) : 0;
      if (bar) {
        bar.style.transform = 'scaleX(' + ratio.toFixed(4) + ')';
      }
      if (nav) {
        nav.classList.toggle('is-stuck', window.scrollY > 12);
      }
    }

    window.addEventListener('scroll', function () {
      if (!queued) {
        queued = true;
        window.requestAnimationFrame(paint);
      }
    }, { passive: true });

    window.addEventListener('resize', paint, { passive: true });
    paint();
  }

  /* ---- mobile nav ------------------------------------------------------- */
  function initNav() {
    var toggle = $('[data-nav-toggle]');
    var panel = $('[data-nav-panel]');
    if (!toggle || !panel) {
      return;
    }

    function setOpen(open) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      panel.classList.toggle('is-open', open);
      panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    setOpen(false);

    toggle.addEventListener('click', function () {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    panel.addEventListener('click', function (ev) {
      if (ev.target.closest('a')) {
        setOpen(false);
      }
    });

    doc.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setOpen(false);
        toggle.focus();
      }
    });

    doc.addEventListener('click', function (ev) {
      if (!panel.contains(ev.target) && !toggle.contains(ev.target)) {
        setOpen(false);
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 900) {
        setOpen(false);
      }
    });
  }

  /* ---- staggered scroll reveals ---------------------------------------- */
  function initReveals() {
    var items = $$('[data-reveal]');
    if (!items.length) {
      return;
    }
    if (isReduced() || !('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) {
          return;
        }
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

    items.forEach(function (el) {
      var order = parseInt(el.getAttribute('data-reveal') || '0', 10);
      if (order > 0) {
        el.style.setProperty('--rd', Math.min(order, 8) * 70 + 'ms');
      }
      io.observe(el);
    });
  }

  /* ---- count-up statistics --------------------------------------------- */
  function formatNumber(value, decimals, group) {
    var fixed = value.toFixed(decimals);
    if (!group) {
      return fixed;
    }
    var parts = fixed.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
  }

  function runCount(el) {
    var target = parseFloat(el.getAttribute('data-count'));
    if (isNaN(target)) {
      return;
    }
    var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
    var group = el.getAttribute('data-group') === 'true';
    var prefix = el.getAttribute('data-prefix') || '';
    var suffix = el.getAttribute('data-suffix') || '';
    var duration = parseInt(el.getAttribute('data-duration') || '1500', 10);

    function paint(value) {
      el.textContent = prefix + formatNumber(value, decimals, group) + suffix;
    }

    if (isReduced()) {
      paint(target);
      return;
    }

    var start = performance.now();
    function frame(now) {
      var t = clamp((now - start) / duration, 0, 1);
      var eased = 1 - Math.pow(2, -10 * t);
      paint(target * (t === 1 ? 1 : eased));
      if (t < 1) {
        window.requestAnimationFrame(frame);
      }
    }
    window.requestAnimationFrame(frame);
  }

  function initCountUps() {
    var els = $$('[data-count]');
    if (!els.length) {
      return;
    }
    if (isReduced() || !('IntersectionObserver' in window)) {
      els.forEach(runCount);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) {
          return;
        }
        runCount(entry.target);
        io.unobserve(entry.target);
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ---- hero instrument -------------------------------------------------- */
  function initScope() {
    var line = $('[data-scope-line]');
    var area = $('[data-scope-area]');
    var ref = $('[data-scope-ref]');
    var dot = $('[data-scope-dot]');
    var cursor = $('[data-scope-cursor]');
    if (!line || !area) {
      return;
    }

    var W = 560;
    var LEFT = 36;
    var RIGHT = 548;
    var TOP = 16;
    var BOTTOM = 196;
    var SAMPLES = 116;
    var span = RIGHT - LEFT;
    var mid = (TOP + BOTTOM) / 2;

    function waveAt(i, phase) {
      var x = i / SAMPLES;
      var envelope = 0.68 + 0.32 * Math.sin(x * 3.1 + 0.6);
      return mid
        + Math.sin(x * 13.2 - phase * 1.5) * 34 * envelope
        + Math.sin(x * 27.5 + phase * 0.9) * 9
        + Math.sin(x * 4.1 + phase * 0.35) * 16;
    }

    function buildPath(phase, refMode) {
      var d = '';
      var lastX = LEFT;
      var lastY = mid;
      for (var i = 0; i < SAMPLES; i++) {
        var x = LEFT + (i / (SAMPLES - 1)) * span;
        var y = refMode
          ? mid + Math.sin((i / SAMPLES) * 8.4 - phase * 0.7 + 1.2) * 26 + Math.sin((i / SAMPLES) * 17 - phase) * 6
          : waveAt(i, phase);
        d += (i === 0 ? 'M' : 'L') + x.toFixed(1) + ' ' + y.toFixed(1) + ' ';
        lastX = x;
        lastY = y;
      }
      return { d: d.trim(), x: lastX, y: lastY };
    }

    function paint(phase) {
      var main = buildPath(phase, false);
      var ghost = buildPath(phase, true);

      line.setAttribute('d', main.d);
      area.setAttribute('d', main.d + ' L' + RIGHT + ' ' + BOTTOM + ' L' + LEFT + ' ' + BOTTOM + ' Z');
      if (ref) {
        ref.setAttribute('d', ghost.d);
      }
      if (dot) {
        dot.setAttribute('cx', main.x.toFixed(1));
        dot.setAttribute('cy', main.y.toFixed(1));
      }
      if (cursor) {
        cursor.setAttribute('x1', main.x.toFixed(1));
        cursor.setAttribute('x2', main.x.toFixed(1));
      }
      return main.y;
    }

    paint(0);

    if (isReduced()) {
      return;
    }

    var running = true;
    var start = performance.now();
    var loop = function (now) {
      if (running) {
        paint((now - start) / 1000);
        window.requestAnimationFrame(loop);
      }
    };
    window.requestAnimationFrame(loop);

    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting && !running) {
            running = true;
            start = performance.now();
            window.requestAnimationFrame(loop);
          } else if (!entry.isIntersecting) {
            running = false;
          }
        });
      }, { threshold: 0.05 }).observe(line.closest('.instrument') || line);
    }

    doc.addEventListener('visibilitychange', function () {
      running = !doc.hidden;
      if (running) {
        start = performance.now();
        window.requestAnimationFrame(loop);
      }
    });
  }

  function initTelemetry() {
    var host = $('[data-telemetry]');
    if (!host) {
      return;
    }

    var channels = [
      { key: 'temp', icon: 'thermometer', label: 'Temperature', unit: '°C', value: 28.4, min: 24.5, max: 33.2, decimals: 1, step: 0.22, pull: 0.26, delta: 0.1 },
      { key: 'hum', icon: 'droplet', label: 'Humidity', unit: '%', value: 63.2, min: 48, max: 82, decimals: 1, step: 1.6, pull: 0.22, delta: -0.8 },
      { key: 'pres', icon: 'gauge', label: 'Pressure', unit: 'hPa', value: 1008.4, min: 1002, max: 1016, decimals: 1, step: 0.32, pull: 0.24, delta: 0.2 }
    ];

    var packet = $('[data-packets]');
    var stamp = $('[data-stamp]');

    function seed(dbValue) {
      var el = $('[data-tele="' + dbValue.key + '"]', host);
      var deltaEl = $('[data-tele-delta="' + dbValue.key + '"]', host);
      return { cfg: dbValue, el: el, deltaEl: deltaEl };
    }

    function render(state) {
      var cfg = state.cfg;
      if (state.el && isFinite(cfg.value)) {
        state.el.textContent = cfg.value.toFixed(cfg.decimals);
      }
      if (state.deltaEl) {
        state.deltaEl.textContent = (cfg.delta >= 0 ? '+' : '−') + Math.abs(cfg.delta).toFixed(cfg.decimals)
          + ' vs last';
        state.deltaEl.setAttribute('data-dir', cfg.delta > 0.02 ? 'up' : (cfg.delta < -0.02 ? 'down' : 'flat'));
      }
    }

    var states = channels.map(seed);

    states.forEach(function (state) {
      if (state.cfg.value === null && state.el) {
        var seeded = parseFloat(state.el.getAttribute('data-seed') || 'NaN');
        if (!isNaN(seeded)) {
          state.cfg.value = seeded;
        }
      }
      render(state);
    });

    if (isReduced()) {
      return;
    }

    var packets = 0;
    window.setInterval(function () {
      states.forEach(function (state) {
        var cfg = state.cfg;
        var previous = cfg.value;
        var drift = (Math.random() - 0.5) * cfg.step;
        var mean = (cfg.min + cfg.max) / 2;
        var pull = (mean - cfg.value) * cfg.pull * 0.08;
        cfg.value = clamp(cfg.value + drift + pull, cfg.min, cfg.max);
        cfg.delta = cfg.value - previous;
        render(state);
      });

      packets += 1;
      if (packet) {
        packet.textContent = String(packets).padStart(6, '0');
      }
      if (stamp) {
        stamp.textContent = new Date().toLocaleTimeString([], { hour12: false });
      }
    }, 2000);
  }

  /* ---- feature spotlight ------------------------------------------------ */
  function initSpotlight() {
    var grids = $$('[data-spotlight]');
    grids.forEach(function (grid) {
      var cards = $$('.feat', grid);
      if (!cards.length) {
        return;
      }
      grid.addEventListener('pointermove', function (ev) {
        var rect = grid.getBoundingClientRect();
        var mx = ((ev.clientX - rect.left) / rect.width) * 100;
        var my = ((ev.clientY - rect.top) / rect.height) * 100;
        cards.forEach(function (card) {
          var box = card.getBoundingClientRect();
          card.style.setProperty('--mx', ((ev.clientX - box.left) / box.width) * 100 + '%');
          card.style.setProperty('--my', ((ev.clientY - box.top) / box.height) * 100 + '%');
        });
        grid.style.setProperty('--mx', mx + '%');
        grid.style.setProperty('--my', my + '%');
      });
    });
  }

  /* ---- FAQ accordion ---------------------------------------------------- */
  function initFaq() {
    var items = $$('[data-faq]');
    if (!items.length) {
      return;
    }

    items.forEach(function (item) {
      var btn = $('[data-faq-btn]', item);
      var panel = $('[data-faq-panel]', item);
      if (!btn || !panel) {
        return;
      }

      function setOpen(open) {
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        item.setAttribute('data-open', open ? 'true' : 'false');
      }

      setOpen(item.getAttribute('data-open') === 'true');

      btn.addEventListener('click', function () {
        var willOpen = btn.getAttribute('aria-expanded') !== 'true';
        items.forEach(function (other) {
          if (other === item) {
            return;
          }
          var otherBtn = $('[data-faq-btn]', other);
          if (otherBtn) {
            otherBtn.setAttribute('aria-expanded', 'false');
          }
          other.setAttribute('data-open', 'false');
        });
        setOpen(willOpen);
      });
    });
  }

  /* ---- boot ------------------------------------------------------------- */
  function boot() {
    initScrollChrome();
    initNav();
    initReveals();
    initCountUps();
    initScope();
    initTelemetry();
    initSpotlight();
    initFaq();
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
}());
