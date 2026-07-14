---
name: Same-URL redirect e2e wait
description: How to wait for a form save round-trip when the redirect target equals the current URL and sibling XHRs share the path prefix.
---

Rule: when a form POST redirects back to the SAME url, `waitForURL` resolves
immediately (no-op) and DB asserts race the write. `waitForResponse` on a path
prefix is also unsafe if the page fires debounced XHRs under the same prefix
(e.g. a live-preview POST to `.../{type}/preview`).

**Why:** the admin block-defaults spec kept reading the pre-save override
value; both "waits" were resolving on the old document / the preview XHR.

**How to apply:** tag the current document (`window.__marker = true`) before
submitting, then `waitForFunction(() => !window.__marker)` + `waitForLoadState`
— a fresh document is the only unambiguous round-trip signal. Also: fixed
sidebars can intercept pointer clicks on submit buttons; `form.requestSubmit()`
via evaluate exercises the same native serialization without pointer flake.
