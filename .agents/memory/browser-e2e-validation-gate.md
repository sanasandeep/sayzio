---
name: 1inme Browser e2e validation gate
description: Why the registered `e2e` validation step gates only the auth-popup spec, not the full Playwright Browser suite
---

The `e2e` validation step (created via `setValidationCommand`) runs
`artifacts/1inme/tests/Browser/run-validation.sh` scoped to
`home-auth-modal-mobile.spec.ts`, then chains the mobile sign-in click-through
(`pnpm --filter @workspace/1inme-mobile run test:auth-flow-e2e`). The two are
joined with `&&`, so the web spec must pass first; the mobile test relies on
chromium that run-validation.sh installs (it has no installer of its own).

The mobile test skips gracefully (exit 0) when the Expo dev server is down, but
that skip costs ~90s (NAV_TIMEOUT_MS): when the Expo workflow is off the proxy
still answers, so it's `page.waitForFunction` that times out, not connection-
refused. Budget for that when timing the gate.

**Why:** the full Browser suite cannot run as an unattended gate in this
environment. Two hard blockers, both unrelated to the code under test:
1. `php artisan tinker` is broken here (psysh / PHP 8.4 parse error:
   "unexpected T_NS_SEPARATOR in vendor/psy/psysh/.../ParseErrorException.php").
   Every spec that seeds fixtures via tinker (slides-mode, palette-dnd, the
   cookie-consent *layout-style* specs) fails before the browser even runs.
   NOTE: it's the *interactive REPL* that's broken — non-interactive
   `php artisan tinker --execute='...'` DOES work and is a reliable way to
   run one-off Eloquent reads/counts against the Laravel RDS (e.g. verifying
   seeded rows). Prefer it over `executeSql`, which hits the Node/drizzle DB,
   not Laravel's RDS, so Laravel tables look empty there.
2. Cold page renders over the distant RDS take ~30-45s, so the default 30s
   Playwright budgets time out; `playwright.config.ts` was raised to
   timeout 90s / navigationTimeout 60s / actionTimeout 30s to compensate.

The auth-popup spec is the only one that just visits `/` with a consent
cookie and needs no seeding, so it is reliably green.

**How to apply:** the wrapper forwards args to `playwright test`, so the
full suite (or any subset) still runs manually via `pnpm test:e2e` /
`pnpm run test:e2e:ci <spec>`. If tinker gets fixed, broaden the gate (a
follow-up task tracks exactly this) by
re-registering `setValidationCommand({name:"e2e", command:"bash artifacts/1inme/tests/Browser/run-validation.sh"})`
(no spec arg = whole suite). The wrapper probes `/up` (not `/`, which is
too slow) and boots an ephemeral `php artisan serve` on :5000 if the dev
workflow isn't already serving on the localhost:80 proxy.
