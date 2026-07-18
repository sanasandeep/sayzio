---
name: Post-payment return-to-app deep link
description: How the website hands control back to the native app after an in-browser upgrade
---

# Post-payment return-to-app deep link

When the mobile app opens the website pricing page in the external browser, the
website must hand control back after checkout so the new plan shows instantly.

The pattern (all same-browser, session-backed):
- App opens `/pricing?client=app`. Web `PricingPagesController::plans` stashes a
  session flag when `client=app`.
- Payment gateway success/return URLs (`/user/billing?paid=<invoice>`) land in the
  SAME browser, so the session flag survives the round-trip. `BillingController::show`
  pull-and-forgets the flag (only when `paid` is present) → `$appReturn`.
- The billing success view fires `sayzio://billing/refresh` (anchor + inline script).

**Why native-intent AND DeepLinkRouter are BOTH needed:**
- `app/+native-intent.ts` `redirectSystemPath` must redirect `billing/refresh` →
  `/plans`, otherwise Expo Router lands the custom-scheme URL on `+not-found`.
- `components/DeepLinkRouter.tsx` receives the RAW url via
  `Linking.addEventListener`/`getInitialURL` INDEPENDENTLY of native-intent routing,
  and does the real work: invalidate `["billing","plans"]` + `["billing","subscription"]`
  react-query caches and call auth `refresh()` (re-pulls `/auth/me`).

**How to apply:** any "return to app after external browser action" needs (1) a
session flag that survives the gateway redirect, (2) a native-intent redirect so the
scheme URL resolves to a real screen, and (3) a DeepLinkRouter branch for the side
effect. DeepLinkRouter is NOT in the dialer-standalone sync set (the standalone
`_layout.tsx` deliberately omits it), so editing it is sync-safe.
