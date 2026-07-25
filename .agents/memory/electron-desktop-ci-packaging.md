---
name: Electron desktop CI packaging (zio-browser)
description: Gotchas when building Electron installers for mac/windows in GitHub Actions from this pnpm workspace
---

Lessons from getting SayZio Browser (Electron) building on macOS/Windows GitHub runners:

- **pnpm-workspace.yaml blocks non-Linux native binaries** (`pkg>platform-binary: '-'` overrides for rollup, esbuild, lightningcss, @tailwindcss/oxide, @expo/ngrok-bin — a dev-env size optimization). Mac/Windows CI installs then fail with "Cannot find module @rollup/rollup-darwin-arm64". Strip all lines ending `: '-'` before `pnpm install` in CI (script: `artifacts/zio-browser/scripts/allow-native-binaries.cjs`).
- **electron-builder does NOT auto-discover `electron-builder.config.ts`** — silently falls back to package.json defaults (default icon, `@workspace...` app name). Must pass `--config` explicitly. And **TS configs crash on Windows** (config-file-ts builds an invalid cache dir from the `D:` drive letter) — use a plain `.cjs` config.
- **Empty signing env vars break the Windows build**: `WIN_CSC_LINK: ${{ secrets.X }}` with unset secret defines an empty var and electron-builder still attempts cert resolution ("not a file"). Only `export` CSC/APPLE vars when non-empty (bash `if [ -n ... ]` step). Mac tolerates empty CSC_LINK; guard anyway.
- **better-sqlite3 must be ≥11** to compile against modern Electron V8 (v9 fails with `CopyablePersistentTraits`); add `prebuild-install` as devDep so electron-builder can fetch prebuilds.
- **`vite build src/renderer` (positional root) skips the package-root vite.config.ts** — output lands in `src/renderer/dist`. Use plain `vite build` with `root:` set in config.
- **tsc with `rootDir: src` + include of `src/main` and `src/shared` nests output** at `dist/main/main/index.js`; the runtime `../preload` / `../renderer` relative paths mean all three outputs must live under one parent (`dist/main/{main,preload,renderer}`) and `extraMetadata.main` must point at the nested path.
- **pnpm/action-setup `version:` conflicts with root `packageManager` field** — omit the version input.
- Workflow YAML: a plain scalar `run:` line containing `": '-'"` (colon+space) is invalid YAML; GitHub then shows the run named by file path and `workflow_dispatch` returns 422 "no workflow_dispatch trigger".
- Release job pattern: builds upload `release/*` + `latest*.yml` as artifacts; a final job downloads both and creates a draft GitHub Release tagged `zio-browser-v<version>` — that release feed is what electron-updater consumes (publish.provider=github in config).

Auto-update feed verification lessons (v0.1.1 release, July 2026):
- **Asset names with spaces break the feed**: GitHub converts spaces→dots on upload ("SayZio Browser Setup 0.1.1.exe" → `SayZio.Browser.Setup.0.1.1.exe`) but `latest.yml` refers to hyphenated names (`SayZio-Browser-Setup-0.1.1.exe`) → updater download 404s. Fix: space-free `artifactName` in the builder config; existing releases can be repaired by PATCHing asset names to match the yml.
- **electron-updater ignores draft releases** — the CI-created draft must be PATCHed `draft:false` before installed apps see the update.
- **If the same version is re-released, softprops updates the existing tag's release in place** (overwrites assets) instead of creating a new one — always confirm the version bump actually landed on the remote before dispatching.
- **DMG target flake on GitHub macOS runners** (dmgbuild `background.tiff` ENOENT race) is FIXED (July 2026): set `dmg.backgroundColor` (skips the generated tiff entirely) + package mac arches sequentially (`--mac --x64` then `--mac --arm64`). DMG is required — the download-page refresh job rejects releases missing mac dmgs. `dmg.writeUpdateInfo:false` since the mac update feed uses the ZIPs.
- **Repairing a release without touching main**: push changed CI files as one API commit (blob/tree/commit) onto a temp branch from the remote tip, `workflow_dispatch` on that branch (release=false), download the run artifact zip, upload assets to the existing release via uploads.github.com, delete the branch.
- Feed verification without a Windows machine: `releases/latest/download/latest.yml` → version + sha512, range-GET the referenced asset URL (must be 200/206), `openssl dgst -sha512 -binary | base64` must equal the yml sha512, and 7z-extract `resources/app-update.yml` from the NSIS exe (`$PLUGINSDIR/app-64.7z`) to confirm the embedded feed.
- macOS auto-update still requires a signed app (Squirrel.Mac); unsigned mac builds only log the update error.

Runtime-startup lessons (v0.1.6, July 2026):
- **CI must explicitly run `electron-builder install-app-deps` before packaging** — in this pnpm workspace the implicit npmRebuild did NOT rebuild better-sqlite3 against Electron's ABI, and the packaged app shipped a Node-ABI binary. Symptom: app launches (menu bar + About work) but the main window never appears, because better-sqlite3 lazy-loads its native binding on the first `new Database()` — module import succeeds, `initDb()` throws.
- **A caught initDb error is not enough**: any later uncaught DB call inside `app.whenReady().then` becomes a silent unhandled rejection → default menu, no window, no error UI. Startup path now uses fail-soft preference wrappers, an in-memory DB fallback, `dialog.showErrorBox` on uncaught main-process errors, and a 6s failsafe `win.show()` if `ready-to-show` never fires. Keep all of these when touching startup code.

## Native modules must be asarUnpack'd under pnpm
electron-builder's smartUnpack fails to detect native modules in this pnpm workspace (symlinked node_modules), so better_sqlite3.node shipped INSIDE app.asar — Electron can't load .node files from asar, so every packaged build's initDb() threw at runtime ("Database not initialized" downstream). Fix: explicit `asarUnpack: ['**/*.node']` in electron-builder.config.cjs.
**How to verify:** the release zip must contain `app.asar.unpacked/node_modules/better-sqlite3/build/Release/better_sqlite3.node` with the right Mach-O arch (`unzip` + `npx @electron/asar extract-file` + `file`).
Also: mac CI now runs `install-app-deps --arch x64|arm64` immediately before each per-arch packaging run (arch-drift defense), and the app degrades gracefully when DB init fails (skip sync runner, catch background tick errors, surface the ORIGINAL init error).

## Renderer must never touch bare `process` (v0.1.12 lesson)
With `contextIsolation:true` + `nodeIntegration:false`, the packaged renderer has NO `process` global — a bare `process.platform` inside a component render throws `ReferenceError: process is not defined` and blanks the whole React tree the moment the init gate lifts (masked earlier by the DB-init blank). Use the preload-exposed `window.zio.platform` instead; vite does not define `process` (only `process.env.NODE_ENV` in some paths). Grep `src/renderer` for `process\.` before any release.

## Blank-window debugging recipe (Linux sandbox)
Simulate a mac DB failure locally: hide better-sqlite3's `build/` dir, `pnpm run build && pnpm run build:preload` (plain build SKIPS preload → window.zio undefined → misleading blank), then `xvfb-run pnpm exec electron dist/main/main/index.js --no-sandbox` with `ELECTRON_ENABLE_LOGGING=1`; xdotool Return dismisses the error dialog; `import -window root` screenshots. Renderer console lines appear as `INFO:CONSOLE(...)` in stderr.

## pnpm also omits native modules' transitive runtime deps
Even with asarUnpack fixed, packaged app crashed with "Cannot find module 'bindings'" — electron-builder under pnpm only reliably packs the artifact's DIRECT dependencies. better-sqlite3's runtime deps (bindings, file-uri-to-path@^1 — v2 is ESM-only and breaks require) must be declared as direct dependencies of the desktop package.
**How to verify:** `npx @electron/asar list app.asar | grep bindings` on the release zip.
