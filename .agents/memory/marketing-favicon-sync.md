---
name: Marketing favicon sync
description: How the 1inme-com marketing favicons stay in lockstep with the Laravel app and its icon_version.
---

The marketing site (`artifacts/1inme-com`) does NOT own its brand favicons — the
Laravel app is the single source of truth.

- Reference icons live in `artifacts/1inme/public/` (favicon.svg, favicon.ico,
  favicon-96x96.png, apple-touch-icon.png, web-app-manifest-{192,512}.png).
- Cache-busting `?v=` in `artifacts/1inme-com/index.html` is derived from Laravel
  `config('app.icon_version')` (in `artifacts/1inme/config/app.php`), never
  hand-edited.
- `artifacts/1inme-com/scripts/sync-favicons.mjs` copies the icons + rewrites the
  version; `--check` fails on drift. Wired as `sync:favicons` / `check:favicons` /
  `prebuild` scripts and a `favicon-sync` validation gate.

**Why:** the two icon copies used to be independent, so a brand icon refresh could
silently leave the marketing gateway serving a stale favicon.

**How to apply:** to change the brand icon, update `artifacts/1inme/public/` and bump
`icon_version`, then run `pnpm --filter @workspace/1inme-com run sync:favicons`.
site.webmanifest is deliberately excluded from the copy (marketing needs relative
icon paths under its base path; Laravel uses root-absolute paths).
