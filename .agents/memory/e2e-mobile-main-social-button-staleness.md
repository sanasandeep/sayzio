---
name: e2e-mobile auth-flow social-provider list is app-driven (Google + LinkedIn)
description: The mobile auth-flow e2e's social-provider expectations must mirror the app's SOCIALS/WEB_BROWSER_PROVIDERS exactly; historically it drifted and went RED.
---

The mobile auth-flow e2e (`test:auth-flow-e2e[:core|:google]`,
`artifacts/1inme-mobile/scripts/test-auth-flow-e2e.mjs`) drives buttons by
`aria-label`, so its provider list and label format must track the app's
`app/(auth)/index.tsx` exactly, or it goes RED at button lookup before any
sign-in step runs.

**Ground truth (app):** `type SocialProvider = "google" | "linkedin"` — the app
intentionally ships exactly TWO social providers. `SOCIALS` and
`WEB_BROWSER_PROVIDERS` both list only `google` + `linkedin`. Buttons render with
`accessibilityLabel={`Log in with ${label}`}` → `aria-label="Log in with Google"`
/ `"Log in with LinkedIn"`. The type-level narrowing is the signal that dropping
Instagram/Facebook/X/Pinterest/TikTok is DELIBERATE product state, not a
regression — so reconcile the test to the app, don't "restore" phantom providers.

**Both branches, two gates:** Google has two sign-in code paths and each is a
separate CI gate (`.replit`, both `isValidation`):
- native expo-auth-session (client ID compiled) → `e2e-mobile-google`
  (`AUTH_FLOW_ONLY=google` → `runGoogleVariant`); label `"Log in with Google"`.
- web-browser fallback (no client ID) → `e2e-mobile-main`
  (`AUTH_FLOW_ONLY=main` → `main()`); `google` is `WEB_OAUTH_PROVIDERS[0]`, so
  step 7 drives `/user/social-oauth/google/login`. Google's web fallback only
  actually runs if the main flow gets past step 1 (`assertSocialButtonsTappable`),
  so a stale provider list silently HIDES the web-fallback coverage.

**How to apply:** any change to the app's social providers or the
`"Log in with {label}"` format must update, in lockstep in the test:
`REQUIRED_SOCIAL_LABELS`, `WEB_OAUTH_PROVIDERS`, the three Google-variant label
literals (assertGoogleButtonTappable / runGoogleSuccessPath /
runGooglePairingHandoff), both `label.replace(/^Log in with /, "")` displayName
strippers, and the step-10 `runWebProviderPairingCancelRetry` provider pair.
Keep the test's providers a strict mirror of the app; never leave labels the app
doesn't render.
