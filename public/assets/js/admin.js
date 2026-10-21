/* Back office helpers: repeatable rows, ordered question picker, rubric weight total. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var t = window.IP ? window.IP.t : function (k) { return k; };

    // Repeatable rows (follow up questions)
    document.querySelectorAll('[data-add-row]').forEach(function (btn) {
      var name = btn.getAttribute('data-add-row');
      var holder = document.querySelector('[data-rows="' + name + '"]');
      var tpl = document.querySelector('template[data-template="' + name + '"]');
      btn.addEventListener('click', function () {
        var index = Date.now();
        var wrap = document.createElement('div');
        wrap.innerHTML = tpl.innerHTML.split('__INDEX__').join(String(index));
        var row = wrap.firstElementChild;
        holder.appendChild(row);
        var first = row.querySelector('select, input, textarea');
        if (first) { first.focus(); }
      });
    });
    document.addEventListener('click', function (e) {
      var rm = e.target.closest('[data-remove-row]');
      if (rm) { rm.closest('[data-row]').remove(); }
    });

    // Ordered picker for scenario questions
    document.querySelectorAll('[data-ordered-picker]').forEach(function (box) {
      var picker = box.querySelector('[data-picker]');
      var list = box.querySelector('[data-picker-list]');
      box.querySelector('[data-picker-add]').addEventListener('click', function () {
        var id = picker.value;
        if (!id || list.querySelector('li[data-id="' + id + '"]')) { return; }
        var text = picker.options[picker.selectedIndex].textContent;
        var li = document.createElement('li');
        li.setAttribute('data-id', id);
        li.innerHTML = '<input type="hidden" name="questions[]"><span class="grow"></span>' +
          '<button type="button" class="btn btn-ghost btn-sm" data-move="up">&uarr;</button>' +
          '<button type="button" class="btn btn-ghost btn-sm" data-move="down">&darr;</button>' +
          '<button type="button" class="btn btn-ghost btn-sm" data-move="remove">&times;</button>';
        li.querySelector('input').value = id;
        li.querySelector('.grow').textContent = text;
        li.querySelector('[data-move="up"]').setAttribute('aria-label', t('move_up'));
        li.querySelector('[data-move="down"]').setAttribute('aria-label', t('move_down'));
        li.querySelector('[data-move="remove"]').setAttribute('aria-label', t('remove'));
        list.appendChild(li);
      });
      list.addEventListener('click', function (e) {
        var b = e.target.closest('[data-move]');
        if (!b) { return; }
        var li = b.closest('li');
        var dir = b.getAttribute('data-move');
        if (dir === 'up' && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); }
        else if (dir === 'down' && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); }
        else if (dir === 'remove') { li.remove(); return; }
        b.focus();
      });
    });

    // Rubric weights must total 100
    document.querySelectorAll('[data-rubric-form]').forEach(function (form) {
      var out = form.querySelector('[data-weight-total]');
      var update = function () {
        var sum = 0;
        form.querySelectorAll('[data-weight]').forEach(function (i) { sum += parseInt(i.value, 10) || 0; });
        out.textContent = t('weight_total', { sum: sum });
        out.className = 'badge ' + (sum === 100 ? 'badge-success' : 'badge-danger');
      };
      form.addEventListener('input', update);
      update();
    });
  });
})();
