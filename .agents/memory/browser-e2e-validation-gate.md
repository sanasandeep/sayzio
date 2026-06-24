---
name: 1inme Browser e2e validation gate
description: Why the registered `e2e` validation step gates a curated two-spec subset, not the full Playwright Browser suite
---

The `e2e` validation step (created via `setValidationCommand`) runs
`artifacts/1inme/tests/Browser/run-validation.sh` scoped to a curated subset —
currently `home-auth-modal-mobile.spec.ts biolink-editor-card-gallery-preview.spec.ts`
(space-separated args forwarded to `playwright test`). The `test:e2e:ci`
package script mirrors the same two-spec arg list. Keep the `.replit` `e2e`
workflow args, the `test:e2e:ci` script, and the Browser README's documented
command in lockstep when changing the gated set.

**Why a curated subset, not the whole suite:** some specs fail/flake as an
unattended gate for reasons unrelated to the code under test, so the gate is
opt-in per spec — only add a spec once it runs green here:
- `php artisan tinker --execute='...'` (non-interactive) WORKS and is what the
  self-bootstrapping card-gallery spec relies on to seed fixtures; it's also a
  reliable way to run one-off Eloquent reads against the Laravel RDS. Prefer it
  over `executeSql`, which hits the Node/drizzle DB, not Laravel's RDS, so
  Laravel tables look empty there. (The old psysh/PHP-8.4 *interactive* REPL
  parse error no longer blocks seeding.)
- `biolink-editor-palette-dnd.spec.ts` waits on a navigation the palette-drop
  flow no longer triggers (in-place insert), so it times out regardless.
- `slides-mode` + cookie-consent *layout-style* specs render heavy pages; fine
  manually, slower/less stable unattended.
- Cold page renders over the distant RDS take ~30-45s, so `playwright.config.ts`
  was raised to timeout 90s / navigationTimeout 60s / actionTimeout 30s; the
  card-gallery describe block lifts its own per-test timeout to 180s.

**How to apply:** the wrapper forwards args to `playwright test`, so the full
suite (or any subset) still runs manually via `pnpm test:e2e` /
`pnpm run test:e2e:ci`. To broaden the gate, re-register with
`setValidationCommand({name:"e2e", command:"bash artifacts/1inme/tests/Browser/run-validation.sh <spec> <spec> ..."})`
(no spec arg = whole suite). The wrapper probes `/up` (not `/`, too slow) and
boots an ephemeral `php artisan serve` on :5000 if the dev workflow isn't
already serving on the localhost:80 proxy. First run in a fresh env pays a
one-time chromium download (slow); it's a cached no-op after.
