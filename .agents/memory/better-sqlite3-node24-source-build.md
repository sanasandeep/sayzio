---
name: better-sqlite3 needs source build on Node 24
description: How to get better-sqlite3 loading in fresh workspace envs (no prebuilt for Node 24)
---

Rule: in fresh task environments, any test that actually opens a better-sqlite3 database fails with "Could not locate the bindings file" — there is NO prebuilt binary for Node 24 (`prebuild-install` reports none for target=24.x).

**Why:** better-sqlite3 ships prebuilds only for older Node ABIs; pnpm install here skips build scripts, so the native addon never gets compiled.

**How to apply:** build it from source once per environment:
```
cd node_modules/.pnpm/better-sqlite3@<ver>/node_modules/better-sqlite3
nohup npx node-gyp rebuild --release > /tmp/bsql-build.log 2>&1 &
```
- Run it in the BACKGROUND — the compile exceeds the 120s bash timeout and the tool kills the whole tree (exit -1, no output).
- node-gyp exits nonzero on a trailing `node_gyp_bins` lstat ENOENT, but `build/Release/better_sqlite3.node` is still produced and loads fine — verify with `node -e "new (require('better-sqlite3'))(':memory:')"`.
- `initDb(':memory:')` in zio-browser's db.ts accepts an explicit path, so tests can use a real in-memory SQLite DB instead of mocking the DB layer.
- This is now automated: zio-browser has a `pretest` hook (`scripts/ensure-better-sqlite3.cjs`) that detects a non-loading addon and source-builds it. Gotchas: must use `npx --yes node-gyp` (plain npx hangs forever on its "Ok to proceed?" install prompt in non-TTY runners), and the whole test run must go through the managed validation runner — even `setsid nohup ... &` builds get reaped when the bash tool call exits.
