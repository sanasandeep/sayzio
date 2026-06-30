---
name: Mobile offline unit gate
description: The mobile-unit validation gate chains the source-driven offline mobile regression tests; they rot silently when app source evolves.
---

# Mobile offline unit gate

`mobile-unit` validation gate runs `pnpm --filter @workspace/1inme-mobile run test:unit`,
which chains the 15 fast/offline mobile regression scripts in `artifacts/1inme-mobile/scripts/`
(citation-href, block-cache, login-auth-config, whatsapp-disconnect, wizard-flow, voice-bridge,
push-action, native-route, stats-range, stats-export, upgrade-hint, quick-contact, premium-cells,
tier-switch-toast, auth-flow).

**Why:** These are *source-driven* tests — they `readFileSync` the real screen/client source and
assert on it (regex wiring guards, or extracting a pure expression via `new Function` and evaluating
it). Because nothing ran them on change, the app source legitimately evolved and the tests silently
rotted: e.g. a `canExport` gate gained a stats-payload source, manage-subscription got a native
screen, a wizard var renamed `inGroup`→`personaValid`, deep-link building moved to `URLSearchParams`,
and `sendOtp` started reading the response body. When one of these fails, first check whether the
**source change is intentional** (it usually is) and update the stale test assertion — don't assume a
real regression.

**How to apply:** Adding/removing a mobile offline test = update `test:unit` in
`artifacts/1inme-mobile/package.json` (the gate just calls that script, so `.replit` stays in lockstep
via setValidationCommand). Keep LIVE tests OUT of this gate: `test:icon-fonts`
(`check-icon-fonts.mjs`) launches Playwright/Chromium against the running Expo web server, so it
belongs with the live-domain smokes, not the offline gate.
