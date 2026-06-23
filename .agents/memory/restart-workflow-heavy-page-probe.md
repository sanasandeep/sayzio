---
name: restart_workflow probe vs heavy cold page
description: Why restart_workflow fails for the 1inme web workflow even when the app is healthy, and what not to retry
---

- `restart_workflow` marks `artifacts/1inme: web` FAILED even when the server is healthy: logs show it boots and serves `/` at ~1-2s for 30s straight, yet the probe still reports "preview endpoint did not respond with HTTP 200" after the full timeout, then SIGKILLs the server (leaving `:5000` connection-refused and `:80` → 502).
- The SAME probe passes instantly for the lightweight Vite artifacts (`1inme-com`, `1inme-deck`), so the failure is specific to the heavy ~4000-line RDS-backed home page `/`, not the workflow mechanism or general code.
- Tried and FAILED — do NOT re-thrash these: known-good plain `artisan serve` config; OPcache `enable_cli` + `file_cache`; a PRE-POPULATED 1056-file bytecode cache (`file_cache_only=1`) so workers skip compilation; a pre-warmed 300s file data cache. None made the probe pass. Eight restart attempts, every controllable variable eliminated.
- The app DOES come up via the platform's post-merge bring-up (more lenient than `restart_workflow`) and via deploy (prod `php -S` config). So leave the simple proven dev config in artifact.toml and let post-merge/deploy start it.
- **Why:** the probe appears to use an external/proxy check with a limited early-failure budget; the cold startup burst (concurrent cold `/` requests on limited `PHP_CLI_SERVER_WORKERS`, each doing framework boot + a heavy ~1.8s Blade render) exhausts that budget before the server drains to fast responses. This is beyond app/config control.
- **How to apply:** if asked to "fix the failing 1inme web workflow", verify health directly with `curl localhost:5000/` (warm ~1.8s, cold ~5s = healthy) instead of trusting the probe; fix any real perf regressions, then report the probe as a platform false-negative rather than burning attempts. The legitimately invasive option (not yet done, ask first) is a dev-only fast 200 on `/` during the startup window or full-page HTML caching of the anon home.
