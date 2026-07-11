---
name: Mobile onboarding slider e2e quirks
description: Gotchas when driving the RN-Web onboarding carousel in Playwright (pager detection, route warm-up, slides envelope)
---

Rules learned building the onboarding slider e2e harness:

- **Pager detection**: React Native Web renders a horizontal FlatList as ONE div with `scroll-snap-type: x mandatory` / scrollable overflow-x — but every slide wrapper ALSO reports `scrollWidth > clientWidth` (overflow: visible). Setting `scrollLeft` on those wrappers is a silent no-op. To simulate a swipe, select the element whose computed `overflowX` is auto/scroll or whose `scrollSnapType` includes `x`, then advance `scrollLeft` by `clientWidth`; the scroll event drives the same onScroll/index logic as a finger swipe.
- **Route warm-up**: `page.goto(<expo-dev>/onboarding)` directly can blow a 90s nav timeout because Metro compiles that route on demand. Navigate to the already-warmed root instead and let the launch gate redirect client-side (clear the `1inme.onboarding.complete` localStorage flag first) — also the real fresh-install path.
- **Slides envelope**: `GET /api/v1/onboarding/slides` returns a FLAT `{items:[...]}` payload — no `{data}` envelope — unlike most of the mobile API.
- **Frame-containment assertion**: the regression class this guards (glass card sliding off-screen) is caught by asserting the chip/title boundingBox sits fully inside the viewport, not just Playwright "visible".
- Background `expo start` launched from a bash tool call does not survive across tool invocations; for iteration, use the expo workflow + `APP_URL=https://$REPLIT_EXPO_DEV_DOMAIN/`.
- **Throwaway boot is unreliably slow (20+ min, sometimes never completes)**: the `e2e-mobile-onboarding` validation workflow boots its OWN throwaway Metro; in constrained envs that can hang effectively forever. Reliable path: restart the persistent `artifacts/1inme-mobile: expo` workflow, warm it with `curl GET /` until HTTP 200, then run the .mjs directly with `APP_URL` pointed at `$REPLIT_EXPO_DEV_DOMAIN` — completes in well under a minute.
- **Full intro arc = 11 pages, not the raw slide list**: FALLBACK_SLIDES is 10 (welcome→creators→business→freelancer→networker→students→coaches→platform→grow→get-started) and the app inserts the `ai-dashboard` page just before `get-started` at render time (see `ctaIdx`/`pages`). To verify the SHIPPED arc, mock the slides endpoint EMPTY (`{items:[]}`) so the bundled FALLBACK arc renders instead of admin content.
- **External footer link check**: the "Website" footer link opens `https://sayzio.app` via RN-Web `Linking.openURL`, which calls `window.open(url, target, 'noopener')` (URL normalized via `new URL`, so it arrives as `https://sayzio.app/` with trailing slash). Stub `window.open` in an addInitScript to record opens rather than spawning a real popup; match with-or-without trailing slash.
- **Pager dot count**: dots are plain Views (no text) — count them by finding the first div whose direct child divs are ALL tiny bars (height 4–14px, width 4–40px) and there are ≥8; assert === 11.
