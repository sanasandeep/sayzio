---
name: Zio sync plan-gate real-API e2e harness
description: How the Electron + real-Laravel sync plan-gate e2e works; better-sqlite3 dual-ABI swap; mode-picker dismissal
---

Harness: `artifacts/zio-browser/tests/e2e-sync-plan-gate/run.sh` (registered validation command `e2e-zio-sync-plan-gate`). One invocation: throwaway local Postgres (initdb/pg_ctl, trust auth) → `migrate:fresh` → tinker-seeded plans (gated / capped=2 / open) + Sanctum token → `php -S` from `public/` with the framework router (inherits DB_* env; `artisan serve` strips it) → pre-seed the Zio SQLite DB → Electron UI e2e under `xvfb-run` → raw-DB asserts. Plan switches mid-run via direct `psql UPDATE users SET plan_id`.

**better-sqlite3 dual ABI:** the same `build/Release/better_sqlite3.node` serves vitest (Node ABI, e.g. abi137) and the Electron app (Electron ABI). `prebuild-install` ships NO prebuilt for recent Electron majors — build from source: `npx node-gyp rebuild --runtime=electron --target=<electron ver> --dist-url=https://electronjs.org/headers` (a few min, needs network). Both binaries are cached in `artifacts/zio-browser/prebuilds/` (`...-abi<N>-linux-x64.node` and `...-electron<ver>-linux-x64.node`); the harness copies the right one in per phase and ALWAYS restores the Node-ABI binary on exit (trap), or vitest breaks. After an Electron upgrade the electron prebuild must be rebuilt or the harness fails fast.

**Why:** a Node-ABI binary silently fails to load in Electron → the app falls back to an in-memory DB → pre-seeded prefs/queue invisible and nothing persists.

**Seeding a signed-in app:** write prefs directly — `sayzio_api_base_url`, `device_id`, `auth_token_encrypted = 'plain:<token>'` (safeStorage-unavailable fallback), plus a due `sync_queue` row; exec `CREATE_TABLES_SQL` + set `schema_version` from `dist/main/shared/db-schema.js`.

**Mode picker dismissal (Playwright):** two clicks — `button div:text-is("Browser")` then `button:has-text("Open in")`; a single `h3` click never leaves the picker. Exact-match "Browser" (Split card description contains the substring). Window may be recreated right after — keep polling for `button[title="Settings"]`/`"More tools"`.

**Gate flow facts verified:** 402 gate persists `SYNC_PLAN_GATE` pref; a successful partial push clears the gate and sets `SYNC_REJECTED_NOTICE` (`N item(s) not synced — over your plan's limit (limit: X)`); after upgrading, the background retry loop flushes queued items with NO UI action (backoff doubles from 1s, so keep gated-phase short or the wait grows).

**Stale dist trap:** the harness runs whatever is in `dist/` — after any `src/` change rebuild ALL THREE bundles (`build:main`, `build:preload` via `tsc -p tsconfig.preload.json`, `build:renderer`) or the gate never persists / `planStatus` returns undefined / settings UI is old. Also: the toolbar "Sync paused — upgrade to resume" pill substring-matches `button:has-text("Sync")` and precedes the settings nav in the DOM — target the nav item with `button:has(span:text-is("Sync"))`. This stale-dist failure looks exactly like "Phase A: plan gate blocked=true (last=undefined)" — seen Aug 2026 in a task env where it reproduced identically with the task's change reverted (i.e. pre-existing at that env's HEAD); verify against the parent commit before blaming your change.

**Electron dist can go missing in task envs:** `node_modules/electron/dist` absent → `Electron failed to install correctly`; repair with `node node_modules/electron/install.js`.

**Pid-cap hardening (Aug 2026):** under the parallel validation battery the cgroup pid cap starves spawns (pthread_create/fork EAGAIN). run.cjs now retries `_electron.launch` with backoff, has a non-fatal `unhandledRejection` guard (Playwright's launch failure also escapes as a duplicate rejection that would crash Node before the catch), retries psql spawns (sync sleep via `Atomics.wait`, never fork-to-sleep), and POLLS for the rejected-notice clear (it clears a moment after the queue drains — asserting the drain-time snapshot is a race).
