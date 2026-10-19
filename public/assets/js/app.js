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

    // Header elevation once the page scrolls
    var header = document.querySelector('.site-header');
    if (header) {
      var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }

    // Animated counters
    document.querySelectorAll('[data-count]').forEach(function (el) {
      var target = parseInt(el.getAttribute('data-count'), 10) || 0;
      if (reduceMotion || target === 0) { return; }
      var start = null;
      var step = function (ts) {
        if (!start) { start = ts; }
        var p = Math.min(1, (ts - start) / 900);
        el.textContent = String(Math.round(target * (1 - Math.pow(1 - p, 3))));
        if (p < 1) { requestAnimationFrame(step); }
      };
      el.textContent = '0';
      requestAnimationFrame(step);
    });

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
    prof.addEventListener('change', filterScenarios);
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
