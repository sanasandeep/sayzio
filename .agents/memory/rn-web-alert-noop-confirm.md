---
name: RN-web Alert.alert is a no-op
description: Confirmation dialogs on the mobile app's web build need a window.confirm branch or they silently swallow taps.
---

react-native-web ships `Alert` as `static alert() {}` — a literal no-op. Any
native `Alert.alert` confirmation (e.g. destructive-action confirms) does
NOTHING on the Expo web build: the tap is silently swallowed, no dialog, no
action.

**Why:** the drawer Sign out confirm was untestable (and broken) on web until
a `Platform.OS === "web"` → `window.confirm(...)` branch was added, mirroring
`lib/upgradePrompt.ts`. Dismissing the confirm (Cancel / Android hardware back)
must run NO action — only explicit accept fires the destructive callback.

**How to apply:** never call `Alert.alert` directly in
`artifacts/1inme-mobile` — use the shared `showAlert` shim
(`lib/webAlert.ts`, same signature) which delegates to the native Alert
off-web and maps buttons onto `window.alert`/`window.confirm` on web
(info → alert; one actionable button → confirm; multi-choice sheet →
sequential confirms; fails closed if no `window.confirm`). All ~100 call
sites were codemodded to it in July 2026. Source-driven tests that mock
`Alert` must also stub `showAlert`, and assertions matching
`Alert\.alert(` in screen source must match `showAlert(`. E2e-cover
confirms in
a headless harness via Playwright `page.on("dialog")` (dismiss vs accept), the
throwaway Expo server manager, and a seeded localStorage session
(`1inme.auth.token`/`.user` + `1inme.onboarding.complete`). See
`scripts/test-drawer-signout-e2e.mjs`.
