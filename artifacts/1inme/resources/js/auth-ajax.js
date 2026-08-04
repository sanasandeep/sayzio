/**
 * auth-ajax.js — Progressive-enhancement AJAX helper for auth forms.
 *
 * Intercepts submit on any <form data-ajax> element, POSTs via fetch with the
 * CSRF header, shows/clears inline errors, updates the CSRF token when the
 * server rotates it, and navigates only when the server says to.
 *
 * Non-JS fallback: the native <form method="POST" action="…"> is left intact.
 * If JS is unavailable, or if fetch fails, the form submits normally.
 */
(function () {
  'use strict';

  /* ── CSRF helpers ─────────────────────────────────────────────────── */

  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function updateCsrfToken(tok) {
    if (!tok) return;
    var m = document.querySelector('meta[name="csrf-token"]');
    if (m) m.setAttribute('content', tok);
    document.querySelectorAll('input[name="_token"]').forEach(function (el) {
      el.value = tok;
    });
  }

  /* ── Error/status display ─────────────────────────────────────────── */

  function clearErrors(form) {
    form.querySelectorAll('[data-err]').forEach(function (el) {
      el.textContent = '';
      el.hidden = true;
    });
    form.querySelectorAll('[data-general-err]').forEach(function (el) {
      el.textContent = '';
      el.hidden = true;
    });
  }

  function showErrors(form, errors) {
    if (!errors || typeof errors !== 'object') return;
    var keys = Object.keys(errors);
    for (var i = 0; i < keys.length; i++) {
      var key = keys[i];
      var val = errors[key];
      var msg = Array.isArray(val) ? val[0] : String(val);
      if (key === '_') {
        form.querySelectorAll('[data-general-err]').forEach(function (el) {
          el.textContent = msg;
          el.hidden = false;
        });
      } else {
        var el = form.querySelector('[data-err="' + key + '"]');
        if (el) {
          el.textContent = msg;
          el.hidden = false;
        } else {
          /* field has no dedicated slot → fall into general error box */
          form.querySelectorAll('[data-general-err]').forEach(function (el2) {
            el2.textContent = msg;
            el2.hidden = false;
          });
        }
      }
    }
  }

  /* Status message box: look for [data-ajax-status] within the nearest
     [data-ajax-group] ancestor, or fall back to a sibling of the form. */
  function statusEl(form) {
    var group = form.closest('[data-ajax-group]');
    if (group) return group.querySelector('[data-ajax-status]');
    var p = form.parentElement;
    return p ? p.querySelector('[data-ajax-status]') : null;
  }

  function showStatus(form, msg) {
    var el = statusEl(form);
    if (!el || !msg) return;
    el.textContent = msg;
    el.hidden = false;
  }

  function clearStatus(form) {
    var el = statusEl(form);
    if (el) { el.hidden = true; el.textContent = ''; }
  }

  /* ── Turnstile helper ─────────────────────────────────────────────── */

  /* Turnstile tokens are single-use: once the server has verified (or
     rejected) one, a re-submit with the same token always fails. Whenever an
     AJAX submission completes WITHOUT navigating away, reset any Turnstile
     widget inside the form so the next attempt carries a fresh token. */
  function resetTurnstile(form) {
    if (!window.turnstile) return;
    /* Reset ONLY the widget(s) inside the submitted form — auth pages render
       several forms each carrying their own .cf-turnstile (email OTP,
       WhatsApp sign-up, …), and a bare turnstile.reset() only resets the
       FIRST widget on the page, leaving this form's spent token in place.
       turnstile.reset() accepts the widget container element. */
    var widgets = form.querySelectorAll('.cf-turnstile');
    for (var i = 0; i < widgets.length; i++) {
      try { window.turnstile.reset(widgets[i]); }
      catch (_) { /* best-effort */ }
    }
  }

  /* ── Form wiring ──────────────────────────────────────────────────── */

  function wireForm(form) {
    form.addEventListener('submit', function handler(e) {
      e.preventDefault();

      var btn = form.querySelector('[type="submit"]');
      var origHtml = btn ? btn.innerHTML : '';
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:0.7em;margin-right:0.3em;"></i>Please wait\u2026';
      }

      clearErrors(form);
      clearStatus(form);

      fetch(form.getAttribute('action') || window.location.href, {
        method: (form.getAttribute('method') || 'POST').toUpperCase(),
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
        },
        body: new FormData(form),
        credentials: 'same-origin',
      })
      .then(function (res) {
        return res.json().then(function (data) {
          return { httpStatus: res.status, data: data };
        });
      })
      .then(function (result) {
        var data = result.data;

        /* Rotate CSRF token when server regenerated it */
        if (data.csrf_token) updateCsrfToken(data.csrf_token);

        /* Server says go somewhere → navigate */
        if (data.redirect) {
          window.location.href = data.redirect;
          return; /* keep button disabled during navigation */
        }

        /* Restore button for in-place error/status display */
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        resetTurnstile(form);

        if (data.status) showStatus(form, data.status);

        /* errors from business logic (our envelope) OR Laravel 422 */
        var errs = data.errors || null;
        if (errs) {
          showErrors(form, errs);
        } else {
          /* no errors → fire success event for form-specific side-effects */
          form.dispatchEvent(
            new CustomEvent('authajax:success', { detail: data, bubbles: false })
          );
        }
      })
      .catch(function () {
        /* Network / JSON-parse failure — restore button, fall back to
           native submit so the user isn't stuck. */
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        form.removeEventListener('submit', handler);
        form.submit();
      });
    });
  }

  function init() {
    document.querySelectorAll('form[data-ajax]').forEach(wireForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
