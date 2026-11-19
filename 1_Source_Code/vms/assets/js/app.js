/* =====================================================================
   TeekaCard - Vaccination Management System
   File    : assets/js/app.js
   Purpose : Small progressive enhancements. Every page still works if
             JavaScript is turned off; the server validates everything.
   ===================================================================== */
(function () {
  'use strict';

  /* ---------------------------------------------------------------
     1. Confirmation before destructive actions.
        Usage: <form data-confirm="Delete this hospital?">
     --------------------------------------------------------------- */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  /* ---------------------------------------------------------------
     2. Show / hide password fields.
        Usage: <button class="password-toggle" data-target="#password">
     --------------------------------------------------------------- */
  document.querySelectorAll('.password-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.querySelector(btn.getAttribute('data-target'));
      if (!input) { return; }
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  });

  /* ---------------------------------------------------------------
     3. Auto-submit small GET filter forms when a select changes.
        Usage: <select data-autosubmit>
     --------------------------------------------------------------- */
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () {
      if (el.form) { el.form.submit(); }
    });
  });

  /* ---------------------------------------------------------------
     4. Print buttons.   Usage: <button data-print>
     --------------------------------------------------------------- */
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  /* ---------------------------------------------------------------
     5. Shared modals: copy data-* values from the button that opened
        the modal into the modal's form fields.
        Usage: <button data-bs-toggle="modal" data-bs-target="#approveModal"
                       data-fill='{"appointment_id":"4","date":"2026-09-20"}'>
     --------------------------------------------------------------- */
  document.querySelectorAll('.modal[data-fill-form]').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
      var trigger = event.relatedTarget;
      if (!trigger || !trigger.getAttribute('data-fill')) { return; }
      var data;
      try { data = JSON.parse(trigger.getAttribute('data-fill')); } catch (e) { return; }
      Object.keys(data).forEach(function (key) {
        // Inputs are matched by name, text holders by data-slot
        modal.querySelectorAll('[name="' + key + '"]').forEach(function (field) {
          if (field.type === 'radio' || field.type === 'checkbox') { return; }
          field.value = data[key];
        });
        modal.querySelectorAll('[data-slot="' + key + '"]').forEach(function (holder) {
          holder.textContent = data[key];
        });
      });
    });
  });

  /* ---------------------------------------------------------------
     6. Hospital result form (hospital panel): build one row per
        vaccine inside the modal from the button's JSON list.
     --------------------------------------------------------------- */
  var resultModal = document.getElementById('resultModal');
  if (resultModal) {
    resultModal.addEventListener('show.bs.modal', function (event) {
      var trigger = event.relatedTarget;
      var list = resultModal.querySelector('[data-vaccine-list]');
      if (!trigger || !list) { return; }
      var vaccines = JSON.parse(trigger.getAttribute('data-vaccines') || '[]');
      list.innerHTML = '';
      vaccines.forEach(function (v) {
        var row = document.createElement('div');
        row.className = 'd-flex justify-content-between align-items-center gap-3 py-2 border-top';
        var id = String(v.id);
        row.innerHTML =
          '<div><div class="fw-bold"></div><div class="cell-sub"></div></div>' +
          '<div class="btn-group btn-group-sm" role="group">' +
          '<input type="radio" class="btn-check" name="result[' + id + ']" id="r-yes-' + id + '" value="Vaccinated" checked>' +
          '<label class="btn btn-outline-primary" for="r-yes-' + id + '">Given</label>' +
          '<input type="radio" class="btn-check" name="result[' + id + ']" id="r-no-' + id + '" value="Not Vaccinated">' +
          '<label class="btn btn-outline-danger" for="r-no-' + id + '">Not given</label>' +
          '</div>';
        // textContent keeps vaccine names safe from HTML injection
        row.querySelector('.fw-bold').textContent = v.short;
        row.querySelector('.cell-sub').textContent = v.name;
        list.appendChild(row);
      });
    });
  }

  /* ---------------------------------------------------------------
     7. Select / clear all vaccines of a visit in the booking form.
     --------------------------------------------------------------- */
  document.querySelectorAll('[data-check-group]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var group = document.getElementById(btn.getAttribute('data-check-group'));
      if (!group) { return; }
      var boxes = group.querySelectorAll('input[type="checkbox"]:not(:disabled)');
      var allChecked = Array.prototype.every.call(boxes, function (b) { return b.checked; });
      boxes.forEach(function (b) { b.checked = !allChecked; });
    });
  });

  /* ---------------------------------------------------------------
     8. Vaccine form: build the age label from value + unit.
     --------------------------------------------------------------- */
  var ageValue = document.getElementById('age_value');
  var ageUnit = document.getElementById('age_unit');
  var ageLabel = document.getElementById('age_label');
  if (ageValue && ageUnit && ageLabel) {
    var syncLabel = function () {
      if (ageLabel.dataset.touched === '1') { return; }
      var n = parseInt(ageValue.value, 10) || 0;
      var unit = ageUnit.value;
      ageLabel.value = (n === 0) ? 'At birth' : n + ' ' + (n === 1 ? unit.replace(/s$/, '') : unit);
    };
    ageValue.addEventListener('input', syncLabel);
    ageUnit.addEventListener('change', syncLabel);
    ageLabel.addEventListener('input', function () { ageLabel.dataset.touched = '1'; });
  }
})();
