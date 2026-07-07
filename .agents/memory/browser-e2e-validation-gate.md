---
name: 1inme Browser e2e validation gate
description: Why the registered `e2e` validation step gates a curated subset, the warm-server strategy that makes it feasible, and how to broaden the gate
---

The `e2e` validation step (created via `setValidationCommand`) runs
`artifacts/1inme/tests/Browser/run-validation.sh` scoped to a curated subset.
FOUR lockstep surfaces must stay in sync when the gated set changes: the `e2e`
validation command (re-register via `setValidationCommand`), the `test:e2e:ci`
package script, the `.replit` `e2e` workflow args (platform-owned — driven by
`setValidationCommand`, don't edit `.replit`), and the Browser README (the
documented command + the "Why these N specs are gated" heading/count + its spec
description). The set grows over time (was 5, now 11) — re-read `test:e2e:ci` in
`package.json` for the authoritative current list rather than trusting a count
here. Args are space-separated and forwarded to `playwright test`.

**Warm-server strategy (the real lever):** the dominant cost is the *first*
render of each route — cold home ~25-30s, cold biolink-editor ~20s, warm ~2-3s —
because that first hit primes the file-backed config/AppSetting caches (shared
across all php-cli workers) + per-worker opcache, and the editor's first paint
compiles the heavy block-editor Blade view. The wrapper boots ONE ephemeral
`php artisan serve` for the whole run and then `warm()`s it before Playwright:
GETs `/`, `/pricing`, `/contact`, `/user/login`, then logs in as the demo user
via the real CSRF `demo-login` form and GETs `/user/links/1/blocks` (warms the
authenticated editor). All best-effort — warm-up failures are logged, never fail
the run. With this, the FULL suite runs in ~470s; the gate runs ~300-450s.
`playwright.config.ts` is tuned DOWN from the old cold-per-spec headroom:
timeout 60s, navigationTimeout 45s, actionTimeout 30s, workers 1, and
**`retries: 1`** to absorb the rare CPU-race flake (php+node+Chromium share the
box) — a real regression still fails both attempts. The card-gallery and
palette-dnd describe blocks keep their own 180s per-test ceiling + explicit
per-call timeouts (60-120s) on the slow openEditor / store round-trips.

**palette-dnd is now gated (was an intermittent 500, not flake):** it previously
looked like an unstable "block-count off-by-one / context-closes-mid-test" flake
and was kept out. Root cause was an editor HTTP 500 that round-robined across
workers — see editor-cache-incomplete-class.md. Once that was fixed (cache plain
arrays + is_countable guards) plus a faster demo login and in-place-insert
assertions (no waitForNavigation; see palette-dnd-test-no-reload.md), it runs
green and is gated.

**Why other specs stay OUT of the gate** (env, not code):
- `php artisan tinker --execute='...'` (non-interactive) WORKS and is what the
  self-bootstrapping specs use to seed fixtures; it's also a reliable way to run
  one-off Eloquent reads against the Laravel RDS. Prefer it over `executeSql`,
  which hits the Node/drizzle DB, not Laravel's RDS, so Laravel tables look
  empty there. BUT slides-mode's seed intermittently trips a psysh/PHP-8.4
  `ParseErrorException` (the seed fails, not the assertion).
- `cookie-consent-footer-reserve.spec.ts` — 2/3 pass; the "Customize" expansion
  reserve-settle wait doesn't reliably converge.

**Verifying a slow gate spec without the 120s bash cap:** a single 1inme Browser
spec/run can exceed the `bash` tool's 120s ceiling AND the code_execution
sandbox's 600s ceiling (full palette spec ~8min). Don't fight it in `bash`
(backgrounded servers get reaped on tool return; no managed app-serving workflow
exists). Instead register a throwaway `setValidationCommand` and
`startValidationRun` it — validation runs are NOT bash-capped; then poll
`getValidationRuns()` / tail the `logFilePath` across turns. Read
`test-results/*/error-context.md` + `test-failed-1.png` to see the actual
failing page (this is how the editor 500 was found — the "timeout" was a red
herring).

**How to apply:** the wrapper forwards args to `playwright test`, so the full
suite (or any subset) still runs manually via `pnpm test:e2e`. To broaden the
gate, re-register with
`setValidationCommand({name:"e2e", command:"bash artifacts/1inme/tests/Browser/run-validation.sh <spec> ..."})`
(no spec arg = whole suite) and update README + `test:e2e:ci` to match.
`.replit` is platform-owned — change the `e2e` args via `setValidationCommand`,
not by editing `.replit` (except while resolving a rebase). First run in a fresh
env pays a one-time chromium download; cached no-op after.

**Cross-command shared-state race: voice-assistant-panel appears in TWO validation
commands** (the big `e2e` gate AND a dedicated `e2e-voice-panel`), each booting its
own :5050-family server against the SAME shared RDS. The spec's gated-user block
mutates shared DB state via tinker (`setVoiceGated()` in beforeAll, `setVoiceAvailable()`
restore in afterAll) — when the two commands' runs of the same spec overlap, one run's
restore flips the flag mid-test in the other and the `.sa-mic-lock` assertion finds 0
elements. Symptom signature: voice-panel fails in ONE command while passing in the other
within the same validation run, and passes clean in isolation. Also: don't run
run-validation.sh locally while a validation `e2e` command is RUNNING — both use port
5050, and the loser's tests die with ERR_CONNECTION_REFUSED mid-suite (log ends
abruptly at "Running N tests"; empty test-results/ after a finished run = all passed,
since Playwright only writes dirs for failures/retries).

**Stale spec: voice-assistant-bridge.spec.ts is deterministically RED (July 2026).**
All ~6 bridge tests fail identically (waitForFunction 120s mount timeout, both
retries, ~2-2.5m each) in the full gate AND in isolation on a fresh server.
Root cause is NOT flake: the Zio-panel move set
`@include('partials.voice-assistant', ['voiceFloating' => false])` layout-wide
in `user/layouts/app.blade.php`, and the floating `x-data="voiceAssistant(...)"`
markup is gated on `@if($voiceFloating && $voiceAvailable)` — so the component
the spec waits for (`[x-data^="voiceAssistant"]` on /user/links/create,
/user/links/wizard, etc.) can never render on user pages. Voice itself is fine
(`window.__voice` present in the HTML; voice-assistant-panel.spec.ts passes on
the same server). Diagnosis trick: curl the page on the live :5050 e2e server
mid-run and grep for `voiceAssistant` vs `__voice`. Fix belongs to the voice/Zio
feature: rewrite the bridge spec to drive the panel voice agent (or drop it from
the gate); until then every task's `e2e` gate fails on these specs regardless of
its change.

**Dev-workflow CPU contention flips the whole gate red:** with the heavy
`artifacts/1inme: web` dev workflow running (10 php-cli workers + tailwind
watch), validation runs of the gate fail on SCATTERED, different specs each
round (mix of 0ms connection-refused deaths and 10s timeout clusters); with all
workflows stopped the identical code goes green. Before triaging gate failures
as regressions, check whether a dev workflow was running during the run — and
stop it before retrying validation. Separately, `e2e-mobile-icons` (Expo web)
occasionally dies with ERR_CONNECTION_REFUSED to the Expo server (boot race,
see expo-e2e-boot-readiness.md) — retry or, if everything else is green,
skip-with-reason; it is unrelated to Laravel-side changes.
