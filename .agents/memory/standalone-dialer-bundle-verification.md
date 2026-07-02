---
name: Standalone dialer bundle verification
description: How to verify the extracted sayzio-dialer-standalone bundle end-to-end in an isolated env, and the failure classes it ships with.
---

# Standalone dialer bundle verification

The standalone dialer (source `sayzio-dialer-standalone/`, shipped as `dist/sayzio-dialer-app.tar.gz`) cannot run inside the monorepo (one Expo artifact max). Verify by extracting to /tmp, `npm install`, `npm run typecheck`, and booting `expo start --web` via a temporary workflow.

**Why this matters:**
- Expo typed routes are generated only after the dev server runs; a first-run typecheck passes and then FAILS after the server rewrites `.expo/types/router.d.ts`, exposing `router.push` targets to screens trimmed out of the bundle. Run typecheck again after the server has been up.
- Navigating to a bare route group (`router.replace("/(tabs)")`) with no `index` route inside the group renders "Unmatched Route" on web. Trimmed bundles that rename `index.tsx` → `dialer.tsx` must retarget every navigation to the concrete screen (`/(tabs)/dialer`), including Redirect hrefs and push-notification fallbacks.
- The `runTest` browser subagent does NOT reliably reach arbitrary container ports (`localhost:3000` resolved to the Laravel app via the shared :80 proxy in one run and to Expo in another). For port-bound verification, write a plain Playwright script and run it in-container with `node` from `artifacts/1inme` (has `@playwright/test` installed; chromium cached in ~/.cache/ms-playwright).

**How to apply:** backend for the app = `php -S` from `artifacts/1inme/public` + a small CORS proxy (Laravel CORS only covers assistant/*); OTP auth endpoints are rate-limited (~1/min per identifier) so back-to-back e2e login runs need a cooldown; demo@1inme.com OTP is always 123456 with a demo_reveal in the send response. Rebuild the tarball with `tar --exclude=.../node_modules --exclude=.../.expo -czf dist/sayzio-dialer-app.tar.gz sayzio-dialer-standalone` (excludes BEFORE the path).
