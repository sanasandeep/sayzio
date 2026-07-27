---
name: Mobile bg-preset swatch thumbnails
description: Pre-rendered PNG thumbnails give mobile the real CSS texture; manifest md5 gates staleness.
---

Mobile can't render layered CSS backgrounds (stripes/dots/blend-mode abstracts), so preset swatches use pre-rendered PNG thumbnails of the real CSS committed under the Laravel `public/img/bg-preset-swatches/` dir, with a `manifest.json` mapping preset key → md5(css).

**Why:** LinearGradient tint approximations misrepresented pattern/abstract presets; a server-rendered image is the only faithful cross-platform swatch.

**How to apply:**
- Regenerate with `pnpm --filter @workspace/1inme-mobile run generate:bg-preset-swatches` whenever preset CSS changes — `BgPresetCatalog::forApi()` only emits the `swatch` path when the manifest md5 matches the LIVE css, so stale thumbnails silently fall back to `swatch: null` (gradient fallback) rather than showing a wrong texture.
- `forApi()` must stay framework-free (e2e harnesses evaluate it via bare `php -r`); it reads the manifest with plain file functions, no Laravel helpers.
- Mobile renders the PNG over the LinearGradient fallback (expo-image, absolutized via getBaseUrl()); the swatch e2e harness serves the PNGs from disk via a Playwright route and waits for image decode before pixel checks.
