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
