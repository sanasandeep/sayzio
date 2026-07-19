# Zio Browser

An AI-powered Chromium desktop browser for Mac and Windows, built on Electron. It bundles the **Zio AI assistant**, Sayzio CRM/contact actions, the Zio Dialer, smart link collections, and local-first SQLite storage — with optional cloud sync to your Sayzio account.

## Why

- **Browse + act** in one window: extract contacts, save pages to collections, call numbers, and ask Zio about any page — without switching apps.
- **Local-first**: everything works offline. Sign in to sync bookmarks, collections, and history across devices.
- **Chromium engine**: real-world web compatibility via Electron's WebContentsView.

## Status

> **Desktop-only app** — cannot run headlessly in Replit. This artifact is verified via `typecheck` and `vitest` unit tests only. Build and package runs via GitHub Actions (`.github/workflows/zio-browser-build.yml`).

## Architecture

```
src/
├── main/          # Electron main process (Node + better-sqlite3)
│   ├── index.ts          — app bootstrap, BrowserWindow, menu
│   ├── tab-manager.ts    — WebContentsView multi-tab engine
│   ├── ipc-handlers.ts   — all IPC bridges (tabs, bookmarks, auth…)
│   ├── db.ts             — SQLite read/write via better-sqlite3
│   ├── auth-store.ts     — Sanctum token stored via safeStorage
│   └── download-manager.ts
├── preload/       # contextBridge API exposed to renderer
│   └── index.ts          — window.zio API
├── renderer/      # React 19 + Vite app (browser chrome UI)
│   ├── App.tsx
│   ├── components/
│   │   ├── ChromeBar.tsx   — tab strip + address bar
│   │   ├── ZioPanel.tsx    — split AI assistant panel
│   │   ├── NewTabPage.tsx  — new tab dashboard
│   │   └── AuthModal.tsx   — sign in to Sayzio
│   └── store/
│       ├── tab-store.ts    — tab state (IPC-backed)
│       └── auth-store.ts   — auth state
└── shared/        # Pure TS modules (no Electron/DOM) — unit tested
    ├── api-client.ts        — typed Sayzio /api/v1 client
    ├── omnibox.ts           — URL/search parsing
    ├── context-extractor.ts — page context for AI
    ├── sync-engine.ts       — last-write-wins merge logic
    ├── collection-store.ts  — link collection model
    └── db-schema.ts         — SQLite schema + preference keys
```

## Development

> Requires Node.js 20+, pnpm 9+, and Electron 31.

```bash
# From repo root
pnpm install

# From this directory
pnpm run dev          # Start renderer (Vite) + main process (tsc --watch)
```

## Building & Packaging

```bash
pnpm run build        # Build renderer + main process
pnpm run package      # Build + electron-builder (creates release/)
```

### Code signing

The build is **unsigned by default** (works on dev machines; macOS Gatekeeper will warn on first launch). Signing activates automatically in CI when the GitHub repo secrets below are set — no workflow or config changes needed.

**GitHub repo secrets (Settings → Secrets and variables → Actions):**

| Secret | Purpose |
| --- | --- |
| `MAC_CERT_P12_BASE64` | Developer ID Application certificate (`.p12`), base64-encoded |
| `MAC_CERT_PASSWORD` | Password chosen when exporting the `.p12` |
| `APPLE_ID` | Apple ID email of the developer account |
| `APPLE_APP_SPECIFIC_PASSWORD` | App-specific password for notarization |
| `APPLE_TEAM_ID` | 10-character Team ID |
| `WIN_CERT_P12_BASE64` / `WIN_CERT_PASSWORD` | (optional) Windows Authenticode `.pfx` |

**Obtaining the macOS values** (requires a paid [Apple Developer Program](https://developer.apple.com/programs/) membership):

1. **Developer ID certificate**: at <https://developer.apple.com/account/resources/certificates/list>, create a **Developer ID Application** certificate (upload a CSR generated in Keychain Access → Certificate Assistant → *Request a Certificate From a Certificate Authority*). Download and open it so it lands in your login keychain.
2. **Export as `.p12`**: in Keychain Access, right-click the "Developer ID Application: …" certificate (expand it so the private key is included) → *Export* → `.p12`, and set a password (= `MAC_CERT_PASSWORD`).
3. **Base64-encode**: `base64 -i certificate.p12 | pbcopy` (= `MAC_CERT_P12_BASE64`).
4. **App-specific password**: at <https://account.apple.com> → Sign-In and Security → App-Specific Passwords (= `APPLE_APP_SPECIFIC_PASSWORD`; format `xxxx-xxxx-xxxx-xxxx`).
5. **Team ID**: shown on <https://developer.apple.com/account> under Membership details (= `APPLE_TEAM_ID`).

The workflow maps these into electron-builder's `CSC_LINK` / `CSC_KEY_PASSWORD` / `APPLE_ID` / `APPLE_APP_SPECIFIC_PASSWORD` / `APPLE_TEAM_ID` env vars only when non-empty, enabling hardened runtime + notarization (see `electron-builder.config.cjs`). Once set, dispatch **SayZio Browser Build & Package** with `release: true` to produce signed + notarized mac ZIPs; publish the draft release so installed apps pick up the update.

### Auto-updates

Packaged builds check the GitHub Releases feed of `sanasandeep/sayzio` every 4 hours (electron-updater; see `src/main/auto-updater.ts`). CI (`zio-browser-build.yml`, dispatched with `release: true`) uploads the installers plus `latest.yml` / `latest-mac.yml` into a **published** release tagged `zio-browser-v<version>` — installed apps see the update immediately (electron-updater ignores drafts, which is why the release is published, not drafted). A guard step fails the release job if the tag or release already exists, so bump the version in `package.json` before dispatching.

- **Windows**: works unsigned — the app detects, downloads (sha512-verified), and installs on quit/restart.
- **macOS**: auto-update requires a **code-signed** app (Squirrel.Mac refuses unsigned updates). Unsigned mac builds log the update error and keep running; users must download new versions manually until mac signing secrets are configured.
- Asset names must not contain spaces (GitHub converts spaces to dots on upload, breaking the yml → asset URL match); `artifactName` in the builder config enforces this.

## Cloud sync (optional)

Sign in with your Sayzio account to sync bookmarks, collections, and (optionally) history across devices. The sync protocol is **last-write-wins** on `updated_at`. Server-side storage lives in the Laravel app:

```
/api/v1/browser/devices                          POST  register device
/api/v1/browser/devices/{id}/bookmarks           POST  push bookmarks
/api/v1/browser/devices/{id}/collections         POST  push collections
/api/v1/browser/devices/{id}/history             POST  push history
/api/v1/browser/devices/{id}/pull?since=ISO8601  GET   pull all changes
```

## Verification

```bash
pnpm run typecheck    # tsc on renderer/shared + main + preload
pnpm run test         # vitest unit tests (omnibox, context-extractor, sync, collections, api-client)
```

## GitHub Actions

`.github/workflows/zio-browser-build.yml` runs on every push to `main` that touches `artifacts/zio-browser/**`:

1. **Test job** — typecheck + vitest (Ubuntu, no Electron)
2. **macOS build** — `electron-builder` → `.dmg` + `.zip` (x64 + arm64)
3. **Windows build** — `electron-builder` → NSIS installer (x64)
4. **Release job** — triggered manually; publishes a GitHub Release with all artifacts (refuses to overwrite an existing version's tag/release)

Set `workflow_dispatch.inputs.release = true` to publish a release.
