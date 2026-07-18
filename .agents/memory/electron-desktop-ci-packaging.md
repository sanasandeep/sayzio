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
