---
name: e2e-mobile-main social-button staleness
description: Why the mobile auth-flow e2e (e2e-mobile-main) currently fails at "Continue with Instagram" and why it's usually unrelated to your change.
---

The mobile auth-flow e2e (`e2e-mobile-main` → `test:auth-flow-e2e:core`,
`artifacts/1inme-mobile/scripts/test-auth-flow-e2e.mjs`) can go RED at
`FAIL: social button "Continue with Instagram" is missing from the login screen`
deterministically, right after `reachLoginScreen`, before any demo/OTP step.

**Cause:** the test's `REQUIRED_SOCIAL_LABELS` hard-requires 6 web-browser
providers (Instagram, Facebook, X, LinkedIn, Pinterest, TikTok), but the app's
`WEB_BROWSER_PROVIDERS` in `app/(auth)/index.tsx` renders only `["linkedin"]`.
So the login screen no longer shows the other five, and the required-label loop
fails on the first missing one.

**Why:** this is a test↔app mismatch, not a per-change regression. It fails on a
freshly-reloaded login screen and touches nothing but the social-provider list.

**How to apply:** if you're touching an *unrelated* part of this suite (e.g.
adding the splash/voice-mic checks) and see this exact failure, it's pre-existing
— don't attribute it to your change. The real fix is a scoping decision someone
must make: either the app intentionally narrowed providers (update
`REQUIRED_SOCIAL_LABELS` to match, moving the dropped ones to
`OPTIONAL_SOCIAL_LABELS`) or the narrowing is itself a regression (restore the
providers in `WEB_BROWSER_PROVIDERS`). Don't silently "fix the test" without
deciding which, or you may mask a real provider regression.
