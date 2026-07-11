---
name: Simulating AppState foreground in RN-web Playwright e2e
description: How to trigger useForegroundRefresh (AppState "active") in a Playwright browser test of the 1inme-mobile web build.
---

# Simulating AppState background→foreground in RN-web e2e

react-native-web's `AppState` has no native lifecycle on web — it maps its `"change"` event onto the DOM `visibilitychange` event and derives the state by reading `document.visibilityState` (`'hidden'` ⇒ background, anything else ⇒ `'active'`). So `useForegroundRefresh` (which subscribes to `AppState` `"change"` and fires on `"active"`) is driven purely by `visibilitychange` on web.

To make it fire in Playwright, override the getter and dispatch the event in `page.evaluate`:

```js
const setVisibility = (v) =>
  Object.defineProperty(document, "visibilityState", { configurable: true, get: () => v });
setVisibility("hidden");  document.dispatchEvent(new Event("visibilitychange")); // background (no refresh)
setVisibility("visible"); document.dispatchEvent(new Event("visibilitychange")); // active → refresh callback
```

**Why:** the refresh callback invalidates React Query keys, so mutate the mocked backend BEFORE the foreground dispatch, then poll the DOM until the refetch/re-render lands (don't snapshot once — the refetch is async). `AppState.isAvailable` needs a truthy `document.visibilityState`, which headless Chromium already has.

**How to apply:** any browser e2e of a foreground-refresh screen in `1inme-mobile`. See `scripts/test-plans-foreground-refresh-e2e.mjs` for the full pattern (boots a throwaway expo-web server via `createExpoServerManager`, seeds `1inme.auth.*`/`1inme.onboarding.complete` localStorage, mocks `/api/**`, identifies plan cards by climbing to the largest ancestor subtree that contains only one plan-name leaf so no test-only markup is required). Registered as validation `e2e-mobile-plans-refresh`.
