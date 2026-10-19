/* InterviewPro simulation runner.
 * The server is the source of truth (questions, timers, answers). The browser keeps a local copy of
 * the current draft so that nothing is lost if the connection drops, and re-sends it when back online. */
(function () {
  'use strict';

  var root = document.querySelector('[data-interview]');
  if (!root || !window.IP) { return; }
  var IP = window.IP;
  var t = IP.t;
  var id = root.getAttribute('data-id');
  var api = function (action) { return 'api/interview/' + id + '/' + action; };
  var stateEl = document.getElementById('interview-state');
  var state = stateEl ? JSON.parse(stateEl.textContent) : null;

  var $ = function (sel) { return root.querySelector(sel); };
  var els = {
    loading: $('[data-loading]'), wrap: $('[data-question-wrap]'), meta: $('[data-question-meta]'),
    text: $('[data-question-text]'), fallback: $('[data-fallback]'), guidance: $('[data-guidance]'),
    answer: $('[data-answer]'), words: $('[data-word-count]'), recommended: $('[data-recommended]'),
    save: $('[data-save-status]'), card: $('[data-question-card]'), feedback: $('[data-feedback]'),
    feedbackBody: $('[data-feedback-body]'), qnav: $('[data-qnav]'), rules: $('[data-rules]'),
    progressBar: $('[data-progress-bar]'), progress: $('[data-progress]'), progressText: $('[data-progress-text]'),
    progressCount: $('[data-progress-count]'), timer: $('[data-timer]'), timerValue: $('[data-timer-value]'),
    timerLabel: $('[data-timer-label]'), timerBar: $('[data-timer-bar]'), paused: $('[data-paused]'),
    allDone: $('[data-all-done]'), offline: $('[data-offline]'), submitLabel: $('[data-submit-label]'),
    btn: function (name) { return root.querySelectorAll('[data-action="' + name + '"]'); }
  };
  var finishDialog = document.getElementById('finish-dialog');
  var reportDialog = document.getElementById('report-dialog');

  var busy = false;
  var dirty = false;
  var saveTimer = null;
  var tick = null;
  var remaining = null;
  var elapsed = 0;
  var reviewing = false;
  var lastPosition = null;
  var autoSubmitted = false;

  function storageKey(answerId) { return 'ip-draft-' + id + '-' + answerId; }
  function readLocal(answerId) {
    try { return JSON.parse(localStorage.getItem(storageKey(answerId)) || 'null'); } catch (e) { return null; }
  }
  function writeLocal(answerId, text) {
    try { localStorage.setItem(storageKey(answerId), JSON.stringify({ text: text, ts: Date.now() })); } catch (e) { /* storage full or disabled */ }
  }
  function clearLocal(answerId) {
    try { localStorage.removeItem(storageKey(answerId)); } catch (e) { /* ignore */ }
  }

  function fmt(sec) {
    sec = Math.max(0, Math.round(sec));
    var m = Math.floor(sec / 60), s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }
  function countWords(text) {
    var m = text.trim().match(/[\p{L}\p{N}]+(?:['’-][\p{L}\p{N}]+)*/gu);
    return m ? m.length : 0;
  }
  function badge(text, cls) {
    var b = document.createElement('span');
    b.className = 'badge' + (cls ? ' ' + cls : '');
    b.textContent = text;
    return b;
  }

  function setSaveStatus(kind, extra) {
    els.save.className = 'save-status ' + kind;
    var labels = { saving: t('saving'), saved: t('saved_at', { time: extra || '' }), offline: t('saved_locally'), error: t('save_failed'), dirty: t('unsaved') };
    els.save.textContent = labels[kind] || '';
  }

  // Rendering
  function render() {
    if (!state) { return; }
    var s = state.session, q = state.question, p = state.progress;
    els.loading.hidden = true;

    var pct = p.total ? Math.round(p.done / p.total * 100) : 0;
    els.progressBar.style.setProperty('--value', pct + '%');
    els.progress.setAttribute('aria-valuenow', String(pct));
    els.progressText.textContent = t('question_of', { n: p.position, total: p.total });
    els.progressCount.textContent = t('answered_count', { done: p.done, total: p.total });

    els.btn('pause').forEach(function (b) { b.hidden = !s.allow_pause || s.status !== 'in_progress'; });

    var paused = s.status === 'paused';
    els.paused.hidden = !paused;
    var showDone = !paused && p.all_done && !reviewing;
    els.allDone.hidden = !showDone;
    els.wrap.hidden = paused || showDone || !q;
    renderNav();
    renderRules();
    if (paused || showDone || !q) { els.feedback.hidden = true; stopTimer(); renderElapsed(); return; }

    var changed = lastPosition !== q.position;
    if (changed && !IP.reduceMotion) {
      els.card.classList.remove('is-entering');
      void els.card.offsetWidth;
      els.card.classList.add('is-entering');
    }
    lastPosition = q.position;

    els.meta.innerHTML = '';
    els.meta.appendChild(badge(q.type_label, 'badge-primary'));
    els.meta.appendChild(badge(q.difficulty_label));
    els.meta.appendChild(badge(t('question_short', { n: q.position, total: p.total })));
    if (q.is_followup) { els.meta.appendChild(badge(t('followup_badge'), 'badge-warning')); }
    if (q.status === 'answered' || q.status === 'timed_out') { els.meta.appendChild(badge(t('status_' + q.status), 'badge-success')); }
    if (q.status === 'skipped') { els.meta.appendChild(badge(t('status_skipped'))); }
    els.fallback.hidden = !q.is_fallback;
    els.text.textContent = q.body;
    els.guidance.hidden = !q.guidance;
    els.guidance.textContent = q.guidance ? t('tip_prefix') + ' ' + q.guidance : '';
    els.recommended.textContent = t('recommended', { time: fmt(q.recommended_seconds) });

    if (changed) {
      var local = readLocal(q.answer_id);
      var serverTs = q.draft_saved_at ? Date.parse(q.draft_saved_at.replace(' ', 'T') + 'Z') : 0;
      var text = q.text || '';
      if (local && local.text !== text && local.ts > serverTs && (q.status === 'pending' || q.status === 'draft')) {
        text = local.text;
        dirty = true;
        scheduleSave(200);
        IP.toast(t('restored_local'));
      } else {
        dirty = false;
      }
      els.answer.value = text;
      if (text === '') { setSaveStatus(''); } else if (!dirty) { setSaveStatus('saved', q.draft_saved_at ? q.draft_saved_at.substr(11, 5) : ''); }
      autoSubmitted = false;
    }
    var locked = (q.status === 'answered' || q.status === 'skipped' || q.status === 'timed_out') && !s.allow_back;
    els.answer.readOnly = locked;
    els.words.textContent = String(countWords(els.answer.value));

    var done = q.status === 'answered' || q.status === 'skipped' || q.status === 'timed_out';
    els.btn('prev').forEach(function (b) { b.hidden = !s.allow_back || q.position <= 1; });
    els.btn('skip').forEach(function (b) { b.hidden = !s.allow_skip || done; });
    els.btn('next').forEach(function (b) { b.hidden = !s.allow_back || q.position >= p.total; });
    els.btn('submit').forEach(function (b) { b.hidden = locked; });
    els.submitLabel.textContent = done ? t('update_answer') : (q.position >= p.total && p.done >= p.total - 1 ? t('submit_last') : t('submit'));

    renderFeedback(q.feedback);
    startTimer(q, s);
    if (changed) { els.text.focus({ preventScroll: true }); }
  }

  function renderNav() {
    els.qnav.innerHTML = '';
    state.nav.forEach(function (n) {
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = String(n.position);
      b.className = n.status + (n.followup ? ' followup' : '');
      var statusText = t('status_' + n.status);
      b.setAttribute('aria-label', t('nav_label', { n: n.position, status: statusText }) + (n.followup ? ' (' + t('followup_badge') + ')' : ''));
      if (state.question && n.position === state.question.position && els.wrap.hidden === false) { b.setAttribute('aria-current', 'step'); }
      var canGo = state.session.allow_back && state.session.status === 'in_progress';
      b.disabled = !canGo;
      b.addEventListener('click', function () { reviewing = true; goTo(n.position); });
      li.appendChild(b);
      els.qnav.appendChild(li);
    });
  }

  function renderRules() {
    var s = state.session;
    var items = [
      [s.allow_back, 'rule_back', 'rule_no_back'],
      [s.allow_skip, 'rule_skip', 'rule_no_skip'],
      [s.allow_pause, 'rule_pause', 'rule_no_pause'],
      [!!s.per_question_seconds, 'rule_timer', 'rule_no_timer'],
      [s.instant_feedback, 'rule_instant', 'rule_end_feedback']
    ];
    els.rules.innerHTML = '';
    items.forEach(function (it) {
      var li = document.createElement('li');
      li.textContent = t(it[0] ? it[1] : it[2], { time: s.per_question_seconds ? fmt(s.per_question_seconds) : '' });
      els.rules.appendChild(li);
    });
  }

  function list(title, items, cls, iconPath) {
    if (!items || !items.length) { return null; }
    var wrap = document.createElement('div');
    var h = document.createElement('h4');
    h.textContent = title;
    wrap.appendChild(h);
    var ul = document.createElement('ul');
    ul.className = 'fb-list ' + cls;
    items.forEach(function (text) {
      var li = document.createElement('li');
      li.innerHTML = '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + iconPath + '</svg><span></span>';
      li.querySelector('span').textContent = text;
      ul.appendChild(li);
    });
    wrap.appendChild(ul);
    return wrap;
  }

  function renderFeedback(fb) {
    if (!fb) { els.feedback.hidden = true; return; }
    var body = els.feedbackBody;
    body.innerHTML = '';
    var head = document.createElement('div');
    head.className = 'row row-between';
    var h = document.createElement('h3');
    h.id = 'feedback-title';
    h.textContent = t('feedback_title');
    head.appendChild(h);
    head.appendChild(badge(Math.round(fb.score) + '/100 · ' + fb.score_label, 'badge-' + fb.score_class));
    body.appendChild(head);
    var engine = document.createElement('p');
    engine.className = 'small muted';
    engine.textContent = fb.evaluator_label + ' · ' + t('confidence') + ' : ' + fb.confidence_label;
    body.appendChild(engine);
    if (fb.summary) { var sum = document.createElement('p'); sum.textContent = fb.summary; body.appendChild(sum); }
    var check = '<path d="M20 6 9 17l-5-5"/>';
    var alert = '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>';
    var arrow = '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>';
    [list(t('strengths'), fb.strengths, 'good', check), list(t('gaps'), fb.gaps, 'gap', alert),
      list(t('missing'), fb.missing_elements, 'gap', alert), list(t('recommendations'), fb.recommendations, 'reco', arrow)]
      .forEach(function (n) { if (n) { body.appendChild(n); } });
    if (fb.next_tip) { var tip = document.createElement('p'); tip.className = 'guidance'; tip.textContent = t('next_tip') + ' : ' + fb.next_tip; body.appendChild(tip); }
    if (fb.example_answer) {
      var d = document.createElement('details');
      var s = document.createElement('summary');
      s.textContent = t('example_answer');
      var ex = document.createElement('div');
      ex.className = 'example-answer';
      ex.textContent = fb.example_answer;
      var note = document.createElement('p');
      note.className = 'small muted';
      note.textContent = t('example_note');
      d.appendChild(s); d.appendChild(note); d.appendChild(ex);
      body.appendChild(d);
    }
    els.feedback.hidden = false;
  }

  // Timers
  function stopTimer() { if (tick) { clearInterval(tick); tick = null; } }
  function renderElapsed() {
    els.timerValue.textContent = fmt(elapsed);
    els.timerLabel.textContent = t('elapsed_label', { time: fmt(elapsed), planned: state.session.duration_minutes });
    els.timer.classList.remove('is-low', 'is-out');
    els.timerBar.hidden = true;
  }
  function startTimer(q, s) {
    stopTimer();
    elapsed = s.elapsed_seconds;
    if (q.remaining_seconds === null || q.remaining_seconds === undefined) {
      remaining = null;
      renderElapsed();
      if (s.status === 'in_progress') { tick = setInterval(function () { elapsed++; renderElapsed(); }, 1000); }
      return;
    }
    remaining = q.remaining_seconds;
    var total = s.per_question_seconds || remaining;
    var paint = function () {
      els.timerValue.textContent = fmt(remaining);
      els.timerLabel.textContent = t('remaining_label', { time: fmt(remaining) });
      els.timer.classList.toggle('is-low', remaining <= 30 && remaining > 0);
      els.timer.classList.toggle('is-out', remaining <= 0);
      els.timerBar.hidden = false;
      els.timerBar.classList.toggle('is-low', remaining <= 30);
      els.timerBar.querySelector('span').style.setProperty('--value', Math.max(0, remaining / total * 100) + '%');
      if (remaining === 30) { IP.toast(t('time_warning')); }
    };
    paint();
    tick = setInterval(function () {
      remaining--;
      paint();
      if (remaining <= 0) {
        stopTimer();
        if (!autoSubmitted) { autoSubmitted = true; IP.toast(t('time_up')); submit(els.answer.value.trim() === ''); }
      }
    }, 1000);
  }

  // Network actions
  function setBusy(v) {
    busy = v;
    root.querySelectorAll('[data-action]').forEach(function (b) { b.disabled = v; });
    els.btn('submit').forEach(function (b) { b.classList.toggle('is-loading', v); });
  }
  function apply(res) {
    if (res && res.state) { state = res.state; render(); }
  }
  function onError(err) {
    if (err.network) {
      els.offline.hidden = false;
      setSaveStatus('offline');
      IP.toast(t('network_error'), 'error');
      return;
    }
    if (err.status === 401) { window.location.href = IP.url('login'); return; }
    IP.toast(err.message || t('error_generic'), 'error');
    if (err.data && err.data.state) { state = err.data.state; render(); }
  }

  function scheduleSave(delay) {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveDraft, delay === undefined ? 1500 : delay);
  }
  function saveDraft() {
    if (!state || !state.question || !dirty) { return Promise.resolve(); }
    var q = state.question;
    var text = els.answer.value;
    setSaveStatus('saving');
    return IP.request(api('draft'), { method: 'POST', body: { position: q.position, text: text } }).then(function (res) {
      if (els.answer.value === text) { dirty = false; }
      q.text = text;
      q.draft_saved_at = res.saved_at;
      els.offline.hidden = true;
      setSaveStatus(dirty ? 'dirty' : 'saved', new Date().toTimeString().substr(0, 5));
    }, function (err) {
      if (err.network) { els.offline.hidden = false; setSaveStatus('offline'); } else { setSaveStatus('error'); }
    });
  }

  function submit(skip) {
    if (busy || !state.question) { return; }
    var q = state.question;
    var text = els.answer.value;
    if (!skip && text.trim() === '') { IP.toast(t('empty_answer'), 'error'); els.answer.focus(); return; }
    clearTimeout(saveTimer);
    setBusy(true);
    var wasPosition = q.position;
    IP.request(api('submit'), { method: 'POST', body: { position: q.position, text: text, skip: skip ? 1 : 0 }, timeout: 60000 })
      .then(function (res) {
        clearLocal(q.answer_id);
        dirty = false;
        els.offline.hidden = true;
        if (res.followup) { IP.toast(t('followup_added')); }
        if (res.state && res.state.session.instant_feedback && !skip) {
          // Training: stay on the answered question to read the feedback.
          return IP.request(api('goto'), { method: 'POST', body: { position: wasPosition } }).then(function (r2) { reviewing = true; lastPosition = null; apply(r2); });
        }
        reviewing = false;
        apply(res);
      })
      .catch(onError)
      .then(function () { setBusy(false); });
  }

  function goTo(position) {
    if (busy || !state || position < 1 || position > state.progress.total) { return; }
    var go = function () {
      setBusy(true);
      return IP.request(api('goto'), { method: 'POST', body: { position: position } }).then(apply).catch(onError).then(function () { setBusy(false); });
    };
    if (dirty) { saveDraft().then(go); } else { go(); }
  }

  function simple(action) {
    if (busy) { return; }
    setBusy(true);
    var run = function () { return IP.request(api(action), { method: 'POST', body: {} }); };
    (dirty && action === 'pause' ? saveDraft().then(run) : run()).then(function (res) { lastPosition = null; apply(res); }).catch(onError).then(function () { setBusy(false); });
  }

  function openFinish() {
    var p = state.progress;
    var skipped = state.nav.filter(function (n) { return n.status === 'skipped'; }).length;
    var open = state.nav.filter(function (n) { return n.status === 'pending' || n.status === 'draft'; }).length;
    root.querySelector('[data-finish-summary]').textContent = t('finish_summary', { done: p.done, total: p.total, skipped: skipped, open: open });
    if (finishDialog && finishDialog.showModal) { finishDialog.showModal(); } else if (window.confirm(t('finish_summary', { done: p.done, total: p.total, skipped: skipped, open: open }))) { finish(); }
  }
  function finish() {
    if (busy) { return; }
    setBusy(true);
    (dirty ? saveDraft() : Promise.resolve()).then(function () {
      return IP.request(api('finish'), { method: 'POST', body: {}, timeout: 120000 });
    }).then(function (res) {
      try { Object.keys(localStorage).forEach(function (k) { if (k.indexOf('ip-draft-' + id) === 0) { localStorage.removeItem(k); } }); } catch (e) { /* ignore */ }
      window.location.href = res.redirect;
    }).catch(function (err) { onError(err); setBusy(false); });
  }

  // Events
  els.answer.addEventListener('input', function () {
    dirty = true;
    els.words.textContent = String(countWords(els.answer.value));
    if (state.question) { writeLocal(state.question.answer_id, els.answer.value); }
    setSaveStatus('dirty');
    scheduleSave();
  });
  els.answer.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); submit(false); }
  });
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-action]');
    if (!b || b.disabled) { return; }
    var a = b.getAttribute('data-action');
    if (a === 'submit') { submit(false); }
    else if (a === 'skip') { IP.confirm(t('skip_confirm'), t('skip')).then(function (ok) { if (ok) { submit(true); } }); }
    else if (a === 'prev') { reviewing = true; goTo(state.question.position - 1); }
    else if (a === 'next') { reviewing = true; goTo(state.question.position + 1); }
    else if (a === 'pause') { simple('pause'); }
    else if (a === 'resume') { simple('resume'); }
    else if (a === 'finish-open') { openFinish(); }
    else if (a === 'finish-cancel') { finishDialog.close(); }
    else if (a === 'finish-confirm') { finishDialog.close(); finish(); }
    else if (a === 'report-open') { reportDialog.showModal(); }
    else if (a === 'report-cancel') { reportDialog.close(); }
    else if (a === 'report-send') {
      var form = reportDialog.querySelector('[data-report-form]');
      IP.request('api/questions/' + state.question.question_id + '/report', { method: 'POST', body: { reason: form.reason.value, message: form.message.value } })
        .then(function (res) { reportDialog.close(); form.reset(); IP.toast(res.message); })
        .catch(onError);
    }
  });

  window.addEventListener('online', function () { els.offline.hidden = true; if (dirty) { saveDraft(); } });
  window.addEventListener('offline', function () { els.offline.hidden = false; });
  // Last chance save when leaving the page (reload, language switch, closing the tab).
  window.addEventListener('pagehide', function () {
    if (!dirty || !state || !state.question || !navigator.sendBeacon) { return; }
    var data = new FormData();
    data.append('_csrf', IP.config.csrf);
    data.append('position', String(state.question.position));
    data.append('text', els.answer.value);
    navigator.sendBeacon(IP.url(api('draft')), data);
  });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && dirty) { saveDraft(); } });

  if (state) {
    render();
  } else {
    IP.request(api('state')).then(apply).catch(onError);
  }
})();
