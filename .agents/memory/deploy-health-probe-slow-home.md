---
name: 1inme deploy health probe must not target the home page
description: Why 1inme autoscale publishes failed with zero build-phase logs, and the /up fix.
---

# 1inme deploy: startup health probe on "/" fails the promote

**Symptom:** `publish` fails; `listDeploymentBuilds` shows `status: "failed"` builds
with **zero log lines**, and no NEW build record appears for a fresh attempt.
Meanwhile the production BUILD command reproduces green locally
(`pnpm install --frozen-lockfile`, vite `build`, `composer check-platform-reqs --no-dev`
all pass).

**Root cause:** `[services.production.health.startup].path` was `"/"` — the full
home page. 1inme's home is a heavy Blade render (~4.7s cold / ~1.5s warm on the
dev box; worse in a cold production container over the distant RDS). The Replit
autoscale startup probe enforces a tight per-response latency bound, so it never
receives a fast 200 → the promote phase fails. A promote/health failure surfaces
as a "failed" build with **no build-phase logs** (the build itself succeeded;
the failure is downstream of it).

**Fix:** point the startup probe at Laravel's built-in `/up` route
(`path = "/up"`). It returns 200 in ~0.05s and has **no DB/schema dependency**,
so it is both fast AND preserves the deploy policy that a partial schema must not
fail the deploy (that's why `/up/schema`, which 503s on drift, is deliberately
NOT used). Change it via the artifacts skill (`verifyAndReplaceArtifactToml`),
never by hand-editing `artifact.toml`.

**Why this matters / how to apply:** when a 1inme publish "fails" but the build
logs are empty and the build steps pass locally, suspect the promote/health
phase, not the build. Any Laravel/Blade artifact whose landing page is slow
should health-check a lightweight route, not the home page. NB: the Replit *dev*
readiness probe ignores this setting and always polls `/` (hence the separate
dev-only DevStartupProbe splash middleware) — this fix is for the *production*
probe only.

## Addendum: promote probe also hits each service's BASE PATH

**Symptom (July 2026):** publish stuck at Promote for 25+ min; deployment logs
show repeating `healthcheck failed ... /api returned status 500/404` plus slow
(5-6s, "request aborted") `GET /api` 404s every ~2-5 min.

**Root cause:** besides the configured `health.startup.path`, the promote-phase
probe ALSO polls each service's base path (`/`, `/api`, `/mobile/` — i.e.
`paths[0]` per service). The api-server had no handler for bare `/api`; the
request fell through the Laravel fallthrough proxy → slow 404 → promote never
went green (configured `/api/healthz` was passing the whole time).

**Fix:** every service must answer its own base path fast — added a local
`GET /` (→ `/api`) 200 handler in the api-server health router ahead of the
proxy fallthrough.

**How to apply:** if a publish hangs in Promote and deployment logs show
repeated probes of a bare service prefix, make that exact path return an
instant 2xx locally — configuring `health.startup.path` alone is NOT enough.

## Addendum: synchronous boot-time migrate can ALSO fail the promote

**Symptom (July 2026):** repeated publishes fail at "Creating Autoscale
service" with zero runtime logs even though `/up` is the probe path and the
build phase is fully green (image pushed, security scan passed). Retries did
NOT reliably fix it.

**Root cause:** the production run command ran `php artisan migrate --force`
(+ reconcile fallback) SYNCHRONOUSLY before `exec`ing the web server; over the
distant ap-south-2 RDS this delayed the server past the startup-probe window,
so `/up` never answered. A fast `/up` route is useless if the server hasn't
started yet.

**Fix:** wrap the whole migrate/reconcile/cache-forget block in a backgrounded
subshell `( ... ) &` before the `exec`, so the server binds immediately. Safe
because the keep-serving policy already tolerates a briefly-unmigrated schema;
stderr markers (`::1inme:: DEPLOY MIGRATION FAILED`, completion marker) keep
observability. Edit via `verifyAndReplaceArtifactToml`, never by hand.

## Addendum: base-path "/" probe has a HARD ~6s deadline — needs an in-app fast path

**Symptom (July 2026):** build fully green, log ends at "Creating Autoscale
service"; runtime logs show `healthcheck /: Get "http://127.0.0.1:<port>/":
context deadline exceeded` (~6s). `/up` and `/api/healthz` were fine.

**Root cause:** the promote probe polling each service's base path enforces a
~5-6s per-response deadline; the cold home render over the distant RDS can
exceed it, so a marginal cold start fails the publish nondeterministically.

**Fix:** production-only `ProdStartupProbe` middleware (prepended, GET "/"
only): instant 200 "OK" when the UA is `Go-http-client`/`kube-probe`/`GoogleHC`
/`curl/`/`python-requests`/`Replit`/empty (the prober families; browsers never
send these), plus a **360s** (6-minute) post-boot splash window gated by
`storage/framework/cache/prod_boot_ms` stamped by the production run command.
The 360s window outlasts the ~5-min promote timeout, so the splash covers the
entire health-check window regardless of UA. Mirrors the dev-only
`DevStartupProbe` pattern.

**CRITICAL:** the boot window must be ≥ the promote timeout (empirically ~5 min
= 300s). The original fix used 25s — builds still failed because the prober
checked "/" repeatedly for the full 5 minutes, and after 25s the slow home page
answered again. Use `WINDOW_MS = 360000` (6 min) as the safe minimum.

**How to apply:** if a publish fails with "context deadline exceeded" on a
service's base path, don't just retry — give that exact path an in-app instant
2xx for probe requests; run-command warm-ups alone can't beat the deadline on
a cold container. The boot window must cover the FULL promote timeout, not just
the first few seconds.

## Addendum: container never starts during the promote window (platform-side)

**Symptom (July 2026):** build fully green, log ends at "Creating Autoscale
service" at T, build marked failed ~5 min later at T+5m — and the FIRST runtime
log line appears seconds AFTER the failure timestamp. All in-app fixes
(backgrounded migrate, ProdStartupProbe, /up) are already in place; once the
container finally boots, everything is healthy (server binds in ~2s, /up 200s,
migrations+seeders complete). Meanwhile prod keeps serving the previous build.

**Root cause:** the image pull / Cloud Run cold start itself consumed the whole
~5-min promote window ("Skipping container streaming artifacts (SOCI/ZTOC) for
non-reserved-VM deployment" — no streaming pull on autoscale; the repl image is
large). Nothing in the run command or app can fix this — the process hasn't
started yet.

**How to diagnose which variant you have:** compare the build's `timeUpdated`
(failure moment, via `getDeploymentBuild`) with the first runtime log
timestamp. Runtime logs BEFORE failure → in-app/probe problem (earlier
addenda). Runtime logs only AT/AFTER failure → platform pull/cold-start ate the
window: just retry the publish (layers are now cached, the second attempt
usually schedules fast); long-term lever is shrinking the image.

**Red herring:** early `healthcheck ... returned status 500` lines in the first
~1s of runtime logs are just the proxy answering before services bound their
ports — they are NOT the promote failure cause when they stop within a second.

## Promote deadline vs Cloud Run cold start (July 2026)
A publish can fail with NO code change: the promote step allows ~5 min from "Creating Autoscale service" to a healthy container. Compare failed vs prior successful build timestamps — if the container's first runtime logs appear ~5m+ after service creation, the image was scheduled/pulled too slowly and missed the window by seconds. Fix = just re-publish; the healthcheck 500s on `/` `/mobile/` in the last seconds are the sidecar answering before artifact ports are up, not app errors. ProdStartupProbe (probe-UA fast path + boot-window splash on "/") already covers the app-side slow-home case.

## Image-size margin (July 2026, second failure same day)
When TWO consecutive promotes miss the ~5-min window, don't just retry: the container image is huge (workspace ~12G; attached_assets screenshots alone were 1.4G) and non-reserved-VM deploys skip SOCI streaming, so pull time eats the whole window. Mitigations: prune attached_assets chat screenshots (keep named asset folders + newest few; nothing in code references attached_assets), or suggest Reserved VM (streams image + scheduler never sleeps).

Jul 31 sweep findings — the biggest single files are NOT screenshots: `artifacts/1inme/storage/logs/laravel.log` grows unbounded in dev (was 750M; truncate it before publish), and `sayzio-dialer-standalone/node_modules` (775M, gitignored but imaged; regenerate with `pnpm install` inside that dir if a dialer-sync typecheck needs it). Remaining large-but-kept: root `node_modules` (~1.4G, required), `exports/` APK/zip deliverables (~427M, user files — ask before deleting), `.git` (~2.7G, platform-managed). Sweep recipe: truncate `storage/logs/*.log`, prune loose attached_assets keeping newest ~5, clear `storage/framework/{sessions,opcache}`.

## Pre-framework fast path (Jul 31 2026)
ProdStartupProbe middleware alone was NOT enough: on a cold container Laravel boot itself (autoload + boot-time app_settings reads over cross-region RDS) exceeds the ~5s promote probe deadline before any middleware runs. Fix: server.php (php -S router, prod only) answers GET / probe-UAs instantly and serves /up + a "/" splash during the prod_boot_ms boot window BEFORE loading Laravel. Keep UA list/window in sync with the middleware; never make /up unconditionally 200 outside the boot window (masks broken deploys).
