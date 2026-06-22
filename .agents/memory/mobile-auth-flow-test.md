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

# Testing EXPO_PUBLIC_*-gated UI on web (e.g. the Google button)

**Why:** Expo inlines `EXPO_PUBLIC_*` into the web bundle at Metro build time,
so you CANNOT toggle such a feature against the already-running dev server from
the browser — the value is baked in. The login screen's Google button only
renders when `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID` is set, and that path also runs
the guarded `useIdTokenAuthRequest` hook which throws at render on web when the
client id is missing (crashing the screen into the error boundary).

**How to apply:** to e2e-cover a build-time-gated variant, have the harness boot
a SECOND throwaway `expo start --localhost --port <free>` with the env var set
(`detached:true`, kill via `process.kill(-child.pid)`), poll `/status` for
`packager-status:running`, then drive a browser at `http://localhost:<port>/`.
Reaching "Welcome back" is itself the no-error-boundary proof. Two Metro servers
on the same project coexist fine (shared cache). Keep it best-effort: skip (exit
0) if the server can't boot, mirroring the "skip when dev server down" contract.
See `scripts/test-auth-flow-e2e.mjs` `runGoogleVariant`. Note: Playwright's
chromium-headless-shell must be installed (`npx playwright install
chromium-headless-shell`); the e2e validation step installs it idempotently.
