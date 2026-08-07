---
name: Alpine x-data factories from body @vite modules never initialize
description: Deferred Alpine vendor bundle starts before body-emitted @vite modules run; plus e2e gotchas for synthetic paste, CORS stubs, service workers, stale vite bundles.
---

# x-data factory defined by a body `@vite(...)` module is dead on arrival

The user layout loads Alpine as a deferred head script that starts synchronously
at evaluation. Deferred scripts (classic + module) execute in document order, so
a `@vite` module emitted in the BODY that defines a global `x-data` factory runs
AFTER Alpine has scanned: console shows `Alpine Expression Error: ... is not
defined` and the component is silently inert, even though the factory exists by
the time you inspect it.

**Why:** deterministic script ordering — the component never works on that page.

**How to apply:** push such includes into the layout's `@stack('head-scripts')`
(placed before the Alpine vendor tags) via `@push('head-scripts')`. When Alpine
handlers "silently do nothing", check the console for `is not defined`
expression errors first.

## Related e2e gotchas (found while testing the map picker)

- Chromium ignores `clipboardData` in the `ClipboardEvent` constructor init
  dict — attach it with `Object.defineProperty(evt, 'clipboardData', {value: dt})`.
  Synthetic pastes are untrusted: the browser never performs the default text
  insertion, so "falls through" cases assert `!defaultPrevented` + unchanged value.
- `page.route`-fulfilled CROSS-ORIGIN fetches need an
  `access-control-allow-origin: *` header or the browser fetch fails on CORS.
- The app registers a service worker that serves same-origin fetches —
  SW-handled requests BYPASS `page.route` entirely (stub never hit, live data
  leaks in) while cross-origin fetches still get intercepted. Use
  `test.use({ serviceWorkers: "block" })` when stubbing same-origin endpoints.
- After a rebase that changed frontend source, `public/build` can be stale:
  the e2e exercises OLD JS behavior. `php artisan view:clear && pnpm run build`
  before trusting failures.
