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
