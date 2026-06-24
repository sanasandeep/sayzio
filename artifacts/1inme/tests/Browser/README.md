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
sign-in popup, or a shape-aware card-gallery preview rendering blank
tiles) gets caught automatically instead of only when someone remembers
to run `pnpm test:e2e`, the gated specs are wired into a named validation
step called `e2e`. It runs the wrapper `tests/Browser/run-validation.sh`
(also exposed as the `test:e2e:ci` package script) scoped to the two
self-bootstrapping specs below, so a failing assertion in either blocks
the change instead of passing silently.

The registered validation command is:

```
bash artifacts/1inme/tests/Browser/run-validation.sh home-auth-modal-mobile.spec.ts biolink-editor-card-gallery-preview.spec.ts
```

The wrapper handles its own prerequisites:

- Installs the Playwright chromium browser (idempotent — a no-op once
  cached under `.cache/ms-playwright`).
- Ensures the app is reachable: if `APP_URL` is already serving (the dev
  workflow up on `localhost:80`), it tests against that; otherwise it
  boots an ephemeral `php artisan serve` on port 5000 (probing the
  lightweight `/up` route, since the home page is slow to render), waits
  for it, runs the spec(s), and tears the server down on exit.

It does **not** run database migrations — the schema must already exist
on the (distant) RDS before any per-spec seeds can write fixtures.

### Why only these two specs are gated (not the whole suite)

The validation step is intentionally scoped to the two specs that run
reliably as an unattended gate here:

- `home-auth-modal-mobile.spec.ts` — needs no seeding; it just visits `/`
  with a consent cookie.
- `biolink-editor-card-gallery-preview.spec.ts` — self-bootstrapping: it
  seeds one no-thumbnail card template per shape family via `php artisan
  tinker`, logs in as the demo user, and asserts the shape-aware gallery
  preview draws a real mock for every tile (not a blank fallback). Gating
  it means a regression that makes card-gallery previews render blank
  tiles blocks the change instead of passing silently.

The other specs are NOT gated because, in this environment, they fail or
flake for reasons unrelated to the code under test:

- `biolink-editor-palette-dnd.spec.ts` waits on a navigation that the
  palette-drop flow no longer triggers (drops insert in-place), so it
  times out independent of the code under test.
- `slides-mode` and the consent *layout-style* specs seed via `php artisan
  tinker` and render heavy pages; they run fine manually but are slower /
  less stable as an unattended gate.
- Cold page renders over the distant RDS can take 30-45s, so navigation
  budgets had to be raised in `playwright.config.ts` to keep the gated
  specs stable.

(`php artisan tinker` seeding, once broken here by a psysh/PHP 8.4 parse
error, works again — which is what lets the self-bootstrapping
card-gallery spec be gated.)

Run the full suite manually (when you can tolerate the slow renders) with
`pnpm test:e2e`, or any subset by passing args:

```sh
# from artifacts/1inme/
pnpm run test:e2e:ci                 # the two gated specs, self-bootstrapping
pnpm test:e2e                        # the whole Browser suite
bash tests/Browser/run-validation.sh cookie-consent-footer-gap.spec.ts
```

## Specs

- `slides-mode.spec.ts` — task #1059. Seeds a published 2-slide biolink
  at alias `e2e-slides-demo`, then in a real browser asserts both
  slides render, the active-slide class moves on `ArrowRight` /
  `ArrowLeft` and on a synthesized swipe-left gesture, and the inline
  `/sl/{alias}/view` tracker pings during navigation.
- `biolink-editor-palette-dnd.spec.ts` — task #1340. Seeds a biolink at
  alias `e2e-editor-dnd` (divider, spacer, and a card with one paragraph
  child) and logs in as the demo user, then drives the real palette-drop
  pipeline through production-safe `window.__editorTest` hooks (armed only
  when `window.__E2E__` is set). Asserts a palette tile drops at the top,
  between blocks, at the end, and inside a Card Container (verifying
  position and parent); that card-type tiles are rejected inside a Card
  Container; and that `prefers-reduced-motion` disables the drop
  animation. All tests share one logged-in browser context because the
  `demo-login` route is rate-limited.
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
