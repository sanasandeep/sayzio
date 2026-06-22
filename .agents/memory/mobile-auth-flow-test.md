---
name: Mobile auth flow & its test
description: How the 1inme-mobile sign-in/registration flow behaves and how its screens are tested.
---

# Mobile auth flow

The native app (`artifacts/1inme-mobile`) has NO separate register screen.
Sign-in and sign-up are the same passwordless OTP path: a first-time identifier
is sent a code, and the backend creates the account when the code verifies. So
one OTP test covers both login and registration — don't go looking for a
register screen.

# Testing convention for these screens

**Why:** cross-region RDS + Expo boot make per-test rendered-app tests
impractical, so the auth screens are pinned with source-driven Node `.mjs`
checks rather than RefreshDatabase/Playwright. The one headless test in the
package needs the Expo dev server running, so default to source-driven for logic.

**How to apply:** lift the REAL function out of the TS source and run it against
mocks (a `useCallback = (fn)=>fn` shim for context methods; injected transport
for `apiFetch`), stripping TS with targeted regex since esbuild isn't reachable
from the package. Add screen-wiring string guards so the screen can't silently
drift from the logic under test. Follow the existing `scripts/test-*.mjs`
pattern and register a `pnpm test:*` script.
