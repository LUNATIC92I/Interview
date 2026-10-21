/* InterviewPro shared behaviours: navigation, dialogs, toasts, reveal animations, form helpers. */
(function () {
  'use strict';

  document.documentElement.classList.remove('no-js');

  var configEl = document.getElementById('app-config');
  var config = configEl ? JSON.parse(configEl.textContent) : { i18n: {}, csrf: '', base: '/' };
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
    document.documentElement.classList.contains('reduce-motion');

  function t(key, params) {
    var s = config.i18n[key] || key;
    if (params) {
      Object.keys(params).forEach(function (k) { s = s.split(':' + k).join(String(params[k])); });
    }
    return s;
  }

  function url(path) {
    var base = config.base.replace(/\/$/, '');
    return base + '/' + String(path).replace(/^\//, '');
  }

  /** JSON fetch with CSRF header, timeout and uniform error shape. */
  function request(path, options) {
    options = options || {};
    var controller = 'AbortController' in window ? new AbortController() : null;
    var timer = controller ? setTimeout(function () { controller.abort(); }, options.timeout || 20000) : null;
    var init = {
      method: options.method || 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': config.csrf },
      signal: controller ? controller.signal : undefined
    };
    if (options.body !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(options.body);
    }
    return fetch(url(path), init).then(function (res) {
      if (timer) { clearTimeout(timer); }
      return res.json().catch(function () { return {}; }).then(function (data) {
        data.httpStatus = res.status;
        if (!res.ok) {
          var err = new Error(data.error || data.message || t('error_generic'));
          err.status = res.status;
          err.data = data;
          throw err;
        }
        return data;
      });
    }, function (e) {
      if (timer) { clearTimeout(timer); }
      var err = new Error(t('network_error'));
      err.network = true;
      err.cause = e;
      throw err;
    });
  }

  function toast(message, type) {
    var region = document.getElementById('toast-region');
    if (!region) { return; }
    var el = document.createElement('div');
    el.className = 'toast' + (type === 'error' ? ' toast-error' : '');
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      (type === 'error' ? '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>' : '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>') +
      '</svg><span></span><button type="button" aria-label="' + t('close') + '">&times;</button>';
    el.querySelector('span').textContent = message;
    var remove = function () {
      el.classList.add('is-leaving');
      setTimeout(function () { el.remove(); }, reduceMotion ? 0 : 260);
    };
    el.querySelector('button').addEventListener('click', remove);
    region.appendChild(el);
    setTimeout(remove, type === 'error' ? 8000 : 4500);
  }

  /** Promise based confirmation using the shared native dialog. */
  function confirmDialog(message, okLabel) {
    var dialog = document.getElementById('confirm-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') {
      return Promise.resolve(window.confirm(message));
    }
    return new Promise(function (resolve) {
      dialog.querySelector('#confirm-message').textContent = message;
      var ok = dialog.querySelector('[data-confirm-ok]');
      var cancel = dialog.querySelector('[data-confirm-cancel]');
      var original = ok.textContent;
      if (okLabel) { ok.textContent = okLabel; }
      var done = function (value) {
        ok.removeEventListener('click', onOk);
        cancel.removeEventListener('click', onCancel);
        dialog.removeEventListener('cancel', onCancel);
        ok.textContent = original;
        dialog.close();
        resolve(value);
      };
      var onOk = function () { done(true); };
      var onCancel = function (e) { if (e) { e.preventDefault(); } done(false); };
      ok.addEventListener('click', onOk);
      cancel.addEventListener('click', onCancel);
      dialog.addEventListener('cancel', onCancel);
      dialog.showModal();
      ok.focus();
    });
  }

  window.IP = { config: config, t: t, url: url, request: request, toast: toast, confirm: confirmDialog, reduceMotion: reduceMotion };

  document.addEventListener('DOMContentLoaded', function () {
    // Mobile navigation
    var toggle = document.querySelector('[data-nav-toggle]');
    var menu = document.getElementById('mobile-menu');
    if (toggle && menu) {
      toggle.addEventListener('click', function () {
        var open = menu.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    // Close the account menu on outside click or Escape
    document.querySelectorAll('.user-menu').forEach(function (details) {
      document.addEventListener('click', function (e) { if (!details.contains(e.target)) { details.removeAttribute('open'); } });
      details.addEventListener('keydown', function (e) { if (e.key === 'Escape') { details.removeAttribute('open'); details.querySelector('summary').focus(); } });
    });

    // Reveal on scroll. Besides elements marked .reveal, common content blocks of the public and
    // candidate pages are revealed automatically; siblings get an index (--i) for staggering.
    var main = document.querySelector('main:not(.admin-main)');
    if (main) {
      main.querySelectorAll('.section-head, .grid > .card, .grid > article, .steps > .step, .faq, .stats > *, .page-header .container > *')
        .forEach(function (el) { if (!el.closest('.hero, .interview-shell, dialog') && !el.classList.contains('reveal')) { el.classList.add('reveal'); } });
    }
    var reveals = document.querySelectorAll('.reveal');
    reveals.forEach(function (el) {
      var siblings = el.parentElement ? Array.prototype.filter.call(el.parentElement.children, function (c) { return c.classList.contains('reveal'); }) : [el];
      el.style.setProperty('--i', String(Math.min(siblings.indexOf(el), 6)));
    });
    if (reduceMotion || !('IntersectionObserver' in window)) {
      reveals.forEach(function (el) { el.classList.add('is-visible'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
        });
      }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
      reveals.forEach(function (el) { io.observe(el); });
    }

    // Payment hand-off: open the provider checkout (a plain navigation, allowed by the CSP form-action rule)
    var handoff = document.querySelector('[data-auto-redirect]');
    if (handoff) {
      setTimeout(function () { window.location.assign(handoff.href); }, 400);
    }

    // Header elevation once the page scrolls
    var header = document.querySelector('.site-header');
    if (header) {
      var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }

    // Animated counters, started when the figure scrolls into view
    var countUp = function (el) {
      var target = parseInt(el.getAttribute('data-count'), 10) || 0;
      if (reduceMotion || target === 0) { return; }
      var start = null;
      var step = function (ts) {
        if (!start) { start = ts; }
        var p = Math.min(1, (ts - start) / 1100);
        el.textContent = String(Math.round(target * (1 - Math.pow(1 - p, 3))));
        if (p < 1) { requestAnimationFrame(step); }
      };
      el.textContent = '0';
      requestAnimationFrame(step);
    };
    var counters = document.querySelectorAll('[data-count]');
    if (!reduceMotion && 'IntersectionObserver' in window) {
      var countIo = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) { countIo.unobserve(entry.target); countUp(entry.target); }
        });
      }, { threshold: 0.4 });
      counters.forEach(function (el) { el.textContent = '0'; countIo.observe(el); });
    }

    // Typed answer in the home page mock
    document.querySelectorAll('[data-typewriter]').forEach(function (el) {
      var text = el.getAttribute('data-typewriter') || '';
      if (reduceMotion) { return; }
      el.textContent = '';
      var i = 0;
      var tick = function () {
        i += 1;
        el.textContent = text.slice(0, i);
        if (i < text.length) { setTimeout(tick, text.charAt(i - 1) === ' ' ? 45 : 24); }
      };
      setTimeout(tick, 1300);
    });

    // Pointer feedback (fine pointers only): card spotlight and slight tilt of the hero mock
    var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    if (finePointer && !reduceMotion) {
      document.querySelectorAll('.card-hover').forEach(function (card) {
        card.addEventListener('pointermove', function (e) {
          var r = card.getBoundingClientRect();
          card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
          card.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
      });
      document.querySelectorAll('[data-tilt]').forEach(function (el) {
        var area = el.closest('.hero') || el;
        area.addEventListener('pointermove', function (e) {
          var r = el.getBoundingClientRect();
          var x = (e.clientX - (r.left + r.width / 2)) / r.width;
          var y = (e.clientY - (r.top + r.height / 2)) / r.height;
          el.classList.add('is-tilting');
          el.style.setProperty('--ry', (Math.max(-1, Math.min(1, x)) * 5).toFixed(2) + 'deg');
          el.style.setProperty('--rx', (Math.max(-1, Math.min(1, -y)) * 4).toFixed(2) + 'deg');
        });
        area.addEventListener('pointerleave', function () {
          el.classList.remove('is-tilting');
          el.style.setProperty('--rx', '0deg');
          el.style.setProperty('--ry', '0deg');
        });
      });
    }

    // Confirmation before sensitive form submissions
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (form.dataset.confirmed === '1') { return; }
        e.preventDefault();
        confirmDialog(form.getAttribute('data-confirm')).then(function (ok) {
          if (ok) { form.dataset.confirmed = '1'; form.requestSubmit ? form.requestSubmit() : form.submit(); }
        });
      });
    });

    // Password visibility
    document.querySelectorAll('[data-reveal]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-reveal'));
        if (!input) { return; }
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', t(show ? 'hide_password' : 'show_password'));
      });
    });

    // Client side validation hints (the server always validates again)
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var firstInvalid = null;
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
          var holder = field.closest('.form-group');
          if (!holder || !field.willValidate) { return; }
          var existing = holder.querySelector('.field-error[data-client]');
          if (existing) { existing.remove(); }
          field.removeAttribute('aria-invalid');
          if (!field.checkValidity()) {
            var msg = document.createElement('p');
            msg.className = 'field-error';
            msg.setAttribute('data-client', '');
            msg.id = 'cerr-' + (field.id || field.name);
            msg.textContent = field.validity.valueMissing ? t('required') : (field.validity.typeMismatch ? t('invalid_email') : (field.validity.tooShort ? t('too_short', { min: field.minLength }) : field.validationMessage));
            holder.appendChild(msg);
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', msg.id);
            firstInvalid = firstInvalid || field;
          }
        });
        if (firstInvalid) { e.preventDefault(); firstInvalid.focus(); }
      });
    });

    // Submit buttons: loading state and double submit protection
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (e.defaultPrevented) { return; }
        var btn = form.querySelector('button[type="submit"][data-loading-text]');
        if (btn) {
          if (btn.classList.contains('is-loading')) { e.preventDefault(); return; }
          btn.classList.add('is-loading');
          btn.setAttribute('aria-busy', 'true');
          btn.lastChild.textContent = ' ' + btn.getAttribute('data-loading-text');
        }
      });
    });

    document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

    initSetupForm();
    initGoalForm();
    initCompare();
  });

  function initSetupForm() {
    var form = document.querySelector('[data-setup-form]');
    if (!form) { return; }
    var prof = form.querySelector('[data-profession-select]');
    var scen = form.querySelector('[data-scenario-select]');
    var typeSel = form.querySelector('#interview_type');
    var typeNote = form.querySelector('[data-scenario-type-note]');
    var countNote = form.querySelector('[data-scenario-count-note]');
    var countSel = form.querySelector('#question_count');
    var custom = form.querySelector('[data-custom-options]');

    function filterScenarios() {
      var opt = prof.options[prof.selectedIndex];
      var pid = prof.value;
      var cid = opt ? opt.getAttribute('data-category') : '';
      Array.prototype.forEach.call(scen.options, function (o) {
        if (!o.value) { return; }
        var sp = o.getAttribute('data-profession');
        var sc = o.getAttribute('data-category');
        var visible = sp === pid || (sp === '0' && (sc === cid || sc === '0'));
        o.hidden = !visible;
        o.disabled = !visible;
        if (!visible && o.selected) { scen.value = ''; }
      });
      onScenario();
    }
    function onScenario() {
      var chosen = scen.value !== '';
      typeSel.disabled = chosen;
      countSel.disabled = chosen;
      typeNote.hidden = !chosen;
      countNote.hidden = !chosen;
      if (chosen) {
        var o = scen.options[scen.selectedIndex];
        typeSel.value = o.getAttribute('data-type');
      }
    }
    function onMode() {
      var checked = form.querySelector('[data-mode-radio]:checked');
      custom.hidden = !checked || checked.value !== 'custom';
    }
    var accessNote = form.querySelector('[data-access-note]');
    function onAccess() {
      if (!accessNote) { return; }
      var opt = prof.options[prof.selectedIndex];
      var unlocked = (accessNote.getAttribute('data-unlocked') || '').split(',');
      var state = !prof.value ? 'none' : (unlocked.indexOf(prof.value) !== -1 ? 'unlocked'
        : (parseInt(accessNote.getAttribute('data-trials'), 10) > 0 ? 'trial' : 'locked'));
      accessNote.querySelectorAll('[data-access-state]').forEach(function (el) { el.hidden = el.getAttribute('data-access-state') !== state; });
      accessNote.querySelectorAll('[data-access-link]').forEach(function (a) {
        a.href = accessNote.getAttribute('data-checkout') + (opt ? opt.getAttribute('data-slug') || '' : '');
      });
    }
    prof.addEventListener('change', filterScenarios);
    prof.addEventListener('change', onAccess);
    scen.addEventListener('change', onScenario);
    form.querySelectorAll('[data-mode-radio]').forEach(function (r) { r.addEventListener('change', onMode); });
    form.addEventListener('submit', function () { typeSel.disabled = false; countSel.disabled = false; });
    filterScenarios();
    onMode();
  }

  function initGoalForm() {
    var form = document.querySelector('[data-goal-form]');
    if (!form) { return; }
    var type = form.querySelector('[data-goal-type]');
    var skill = form.querySelector('[data-goal-skill]');
    var update = function () { skill.hidden = type.value !== 'skill_score'; };
    type.addEventListener('change', update);
    update();
  }

  function initCompare() {
    var form = document.querySelector('[data-compare-form]');
    if (!form) { return; }
    var picks = form.querySelectorAll('[data-compare-pick]');
    var submit = form.querySelector('[data-compare-submit]');
    var update = function () {
      var chosen = Array.prototype.filter.call(picks, function (p) { return p.checked; });
      if (chosen.length > 2) { chosen[0].checked = false; chosen.shift(); }
      submit.disabled = chosen.length !== 2;
      form.querySelector('[data-compare-a]').value = chosen[0] ? chosen[0].value : '';
      form.querySelector('[data-compare-b]').value = chosen[1] ? chosen[1].value : '';
    };
    picks.forEach(function (p) { p.addEventListener('change', update); });
    update();
  }
})();
