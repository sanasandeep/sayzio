---
name: Platform-asset CDN has no CORS — import server-side by key
description: Curated-asset CloudFront serves no Access-Control-Allow-Origin, so browser clients can never fetch asset blobs; import into the vault server-side by S3 key instead.
---

The curated platform-asset CDN (CloudFront in front of the S3 `assets/<folder>/` prefixes) serves NO CORS headers, so any browser-side `fetch(assetUrl)` (Expo WEB, web widgets) is blocked. Native (expo-file-system download) is unaffected — which makes the bug easy to miss in app testing (fixed July 2026).

**Rule:** when a client needs curated-asset bytes in the user's vault, POST the asset's S3 `key` to `POST /api/v1/me/files/import-platform-asset`; the server validates the key via `PlatformAssetCatalog::folderForKey` (assets/<folder>/ allowlist, never arbitrary URLs) and vault-writes it with `UserFile::createFromBytes` (size cap + storage quota + 402 plan-gate envelope).

**How to apply:** the mobile stock-sticker pick uses this on ALL platforms (one shared path); the `StockImageGalleryPicker` onSelect already hands `(url, asset)` — use `asset.key`. Don't reintroduce browser blob-fetch import paths for CDN-hosted assets; e2e harnesses no longer need a CDN CORS shim.
