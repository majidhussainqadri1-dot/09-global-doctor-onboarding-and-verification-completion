(function () {
  'use strict';
  var config = window.gdoOnboarding || {};
  var form = document.querySelector('[data-gdo-wizard]');
  if (!form || !config.restUrl) return;
  var status = document.querySelector('[data-gdo-autosave-status]');
  var timer = null;
  var busy = false;

  function fields() {
    var profile = {};
    form.querySelectorAll('[data-gdo-profile]').forEach(function (input) {
      profile[input.name] = input.type === 'checkbox' ? (input.checked ? '1' : '') : input.value;
    });
    return profile;
  }

  function announce(message) {
    if (status) status.textContent = message;
  }

  function synchronizeRowVersion(rowVersion) {
    var value = String(rowVersion);
    form.setAttribute('data-row-version', value);
    document.querySelectorAll('input[name="row_version"]').forEach(function (hidden) {
      if (hidden.closest('[data-gdo-wizard], .gdo-submit-form')) hidden.value = value;
    });
  }

  function save() {
    if (busy) return;
    busy = true;
    announce(config.messages && config.messages.saving ? config.messages.saving : 'Saving…');
    fetch(config.restUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce},
      body: JSON.stringify({
        application_id: Number(form.getAttribute('data-application-id') || 0),
        row_version: Number(form.getAttribute('data-row-version') || 0),
        profile: fields()
      })
    }).then(function (response) {
      return response.json().then(function (data) { return {ok: response.ok, data: data}; });
    }).then(function (result) {
      if (!result.ok) throw result.data;
      if (result.data && result.data.row_version) synchronizeRowVersion(result.data.row_version);
      announce(config.messages && config.messages.saved ? config.messages.saved : 'Draft saved');
    }).catch(function (error) {
      var message = error && error.code === 'gdo_concurrent_change' && config.messages ? config.messages.conflict : (error && error.message ? error.message : 'Autosave failed');
      announce(message);
    }).finally(function () { busy = false; });
  }

  function stepIsValid(step) {
    var invalid = step && step.querySelector(':invalid');
    if (invalid) {
      invalid.reportValidity();
      invalid.focus();
      return false;
    }
    return true;
  }

  form.addEventListener('input', function (event) {
    if (!event.target.matches('[data-gdo-profile]')) return;
    clearTimeout(timer);
    timer = setTimeout(save, Number(config.autosaveDelay || 1500));
  });

  form.querySelectorAll('[data-gdo-step-next]').forEach(function (button) {
    button.addEventListener('click', function () {
      var current = button.closest('[data-gdo-step]');
      if (!stepIsValid(current)) return;
      var next = current && current.nextElementSibling;
      if (current && next) {
        current.hidden = true;
        next.hidden = false;
        var heading = next.querySelector('h2');
        if (heading) heading.focus();
      }
    });
  });
  form.querySelectorAll('[data-gdo-step-back]').forEach(function (button) {
    button.addEventListener('click', function () {
      var current = button.closest('[data-gdo-step]');
      var previous = current && current.previousElementSibling;
      if (current && previous) {
        current.hidden = true;
        previous.hidden = false;
        var heading = previous.querySelector('h2');
        if (heading) heading.focus();
      }
    });
  });
}());
