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
