# Browser tests (Playwright)

End-to-end browser tests for the 1inme web artifact. These complement the
PHPUnit feature suite under `tests/Feature/` by validating runtime
JavaScript behavior (keyboard nav, touch gestures, network pings) that
the controller-level tests can't observe.

## Running

The Laravel app must be reachable at `APP_URL` (defaults to
`http://localhost:80`, the workspace's path-based proxy). Migrations
must be applied so the seed step in each spec can write fixtures.

```sh
# from artifacts/1inme/
pnpm install
pnpm exec playwright install chromium
pnpm test:e2e
```

Each spec is self-bootstrapping: it shells out to `php artisan tinker`
to seed the rows it needs (idempotent — re-running is a no-op once the
fixture exists), then drives a real browser against the public alias.

## Validation step (runs on every change)

So a runtime regression (a CSS/Alpine break in the home-page mobile
sign-in popup, a shape-aware card-gallery preview rendering blank tiles,
a broken block-editor drag-and-drop, or a cookie-consent layout
reserving a phantom footer band) gets caught automatically instead of
only when someone remembers to run `pnpm test:e2e`, the gated specs are
wired into a named validation step called `e2e`. It runs the wrapper
`tests/Browser/run-validation.sh` (also exposed as the `test:e2e:ci`
package script) scoped to the reliable broader subset below, so a
failing assertion in any of them blocks the change instead of passing
silently.

The registered validation command is:

```
bash artifacts/1inme/tests/Browser/run-validation.sh \
  home-auth-modal-mobile.spec.ts \
  biolink-editor-card-gallery-preview.spec.ts \
  biolink-editor-palette-dnd.spec.ts \
  cookie-consent-footer-gap.spec.ts \
  cookie-consent-layout-styles.spec.ts
```

The wrapper handles its own prerequisites:

- Installs the Playwright chromium browser (idempotent — a no-op once
  cached under `.cache/ms-playwright`).
- Ensures the app is reachable: if `APP_URL` is already serving (the dev
  workflow up on `localhost:80`), it tests against that; otherwise it
  boots an ephemeral `php artisan serve` on port 5000 (probing the
  lightweight `/up` route, since the home page is slow to render), waits
  for it, runs the spec(s), and tears the server down on exit.
- **Warms the shared app server before Playwright starts** (see below).

It does **not** run database migrations — the schema must already exist
on the (distant) RDS before any per-spec seeds can write fixtures.

### Warm-server strategy (why the whole suite is now feasible)

The dominant cost is the *first* render of each route: over a distant RDS
a cold home render is ~30s and a cold biolink-editor render ~20s, while a
warm one is ~2-3s. That first hit primes the file-backed config/AppSetting
caches (shared across all php-cli workers) and the per-worker opcache; the
editor's first paint also compiles the heavy block-editor Blade view.

Booting **one** ephemeral server for the whole Playwright run (specs share
it) and then **warming it once up front** — instead of paying a cold
render inside every spec's tight navigation budget — is what turns the
full suite from "too slow/flaky to gate" into a bounded ~5-8 min run. The
`warm()` step in `run-validation.sh`:

1. GETs the public routes the specs use (`/`, `/pricing`, `/contact`,
   `/user/login`).
2. Logs in as the demo user via the real CSRF-protected `demo-login` form
   and GETs `/user/links/1/blocks`, so the heavy authenticated editor
   render is compiled here rather than mid-spec.

All warm-up steps are best-effort: a failure (e.g. no demo link, CSRF
drift, or an already-warm `localhost:80`) is logged and never fails the
run — the specs themselves are the real assertions.

Because the server is pre-warmed, `playwright.config.ts` budgets are tuned
down from the old cold-render-per-spec headroom (per-test `60s`,
`navigationTimeout 45s`) while still leaving slack for a cold php-cli
worker the warm-up loop didn't reach. `retries: 1` absorbs the rare flake
where a heavy editor spec loses a CPU race (php server + node + Chromium
all share this box) and misses a client-side wait by a hair — a real,
consistently-failing regression still fails both attempts. The
card-gallery and palette-dnd describe blocks additionally keep their own
generous per-test ceilings and explicit per-call timeouts on the slow
editor-open / store round-trips.

### Why these five specs are gated (and not the whole suite)

The gate covers the specs that run reliably as an unattended check here:

- `home-auth-modal-mobile.spec.ts` — needs no seeding; visits `/` with a
  consent cookie and drives the mobile sign-in popup.
- `biolink-editor-card-gallery-preview.spec.ts` — self-bootstrapping: it
  seeds one no-thumbnail card template per shape family via `php artisan
  tinker`, logs in as the demo user, and asserts the shape-aware gallery
  preview draws a real mock for every tile (not a blank fallback).
- `biolink-editor-palette-dnd.spec.ts` — self-bootstrapping: it seeds a
  biolink (with a Card Container child) via `php artisan tinker`, logs in
  as the demo user, and drives the real palette-drop pipeline through the
  `window.__editorTest` hook (armed only when `window.__E2E__` is set),
  asserting each drop inserts a block in place (no reload). Gating it
  catches regressions in the editor render or the drop/persist flow.
- `cookie-consent-footer-gap.spec.ts` — pins the "no phantom band below
  the footer" invariant for the default banner layout. No login/seeding.
- `cookie-consent-layout-styles.spec.ts` — extends the footer-reserve
  guard across the corner / pill / modal / takeover consent layouts. No
  login/seeding.

The remaining specs are still NOT gated because, in this environment, they
fail or flake for reasons unrelated to the code under test:

- `slides-mode.spec.ts` — its `php artisan tinker` seed intermittently
  trips a psysh / PHP 8.4 `ParseErrorException` parse error, so the seed
  (not the assertion) fails. Runs fine when tinker behaves.
- `cookie-consent-footer-reserve.spec.ts` — two of its three tests pass,
  but the "Customize" expansion test's reserve-settle wait does not
  reliably converge here.

Run the full suite manually (when you can tolerate the slow renders) with
`pnpm test:e2e`, or any subset by passing args:

```sh
# from artifacts/1inme/
pnpm run test:e2e:ci                 # the five gated specs, self-bootstrapping
pnpm test:e2e                        # the whole Browser suite
bash tests/Browser/run-validation.sh cookie-consent-footer-gap.spec.ts
```

## Specs

- `slides-mode.spec.ts` — task #1059. Seeds a published 2-slide biolink
  at alias `e2e-slides-demo`, then in a real browser asserts both
  slides render, the active-slide class moves on `ArrowRight` /
  `ArrowLeft` and on a synthesized swipe-left gesture, and the inline
  `/sl/{alias}/view` tracker pings during navigation.
- `biolink-editor-palette-dnd.spec.ts` — task #1340. Gated. Seeds a
  biolink at alias `e2e-editor-dnd` (divider, spacer, and a card with one
  paragraph child) and logs in as the demo user, then drives the real
  palette-drop pipeline through production-safe `window.__editorTest`
  hooks (armed only when `window.__E2E__` is set). Asserts a palette tile
  drops at the top, between blocks, at the end, and inside a Card
  Container (verifying position and parent); that card-type tiles are
  rejected inside a Card Container; and that `prefers-reduced-motion`
  disables the drop animation. All tests share one logged-in browser
  context because the `demo-login` route is rate-limited.
- `home-auth-modal-mobile.spec.ts` — gated. On a mobile viewport, opens
  the home-page sign-in popup, asserts the correct tab is active, the
  close button clears the tabs, background scroll is locked, and the X /
  Escape keys close the popup and restore scrolling. No login/seeding.
- `biolink-editor-card-gallery-preview.spec.ts` — gated. Seeds one
  no-thumbnail card template per shape family (avatar/pill/media/form/
  list_rows/heading/text_lines/dot_row) at alias `e2e-card-gallery-preview`,
  logs in as the demo user, opens the editor's Card Templates gallery, and
  asserts every tile draws its shape-aware `preview_layout` mock — never
  the empty-snapshot fallback icon — proving a blank-tile regression would
  fail the gate. All tests share one logged-in context (the `demo-login`
  route is rate-limited).
- `cookie-consent-footer-reserve.spec.ts` — guards the cookie-banner
  footer-reserve invariant. On `/contact` it asserts that a first-time
  visitor's `document.body` bottom padding equals the *visible* banner
  height (the bug once reserved ~416px for a ~154px prompt), that the
  in-place "Customize" expansion grows the reserve in lockstep, and that
  a returning visitor (consent cookie present) gets no banner, no reserve,
  and a footer flush with the bottom of the document. No login needed.
