---
name: e2e-mobile-main signed-in-tabs crash (Home null-guard)
description: Why e2e-mobile-main timed out at waitForSignedInTabs and the real root cause (mobile Home dashboard crash on empty stats shape), now fixed.
---

`e2e-mobile-main` (`pnpm --filter @workspace/1inme-mobile run test:auth-flow-e2e:core`, `scripts/test-auth-flow-e2e.mjs`) timed out at `waitForSignedInTabs` — waiting 30s for the `Profile` tab label after a (mocked) sign-in.

**Real root cause (NOT a navigation race, NOT OTP-specific, NOT Laravel):** the signed-in Home tab (`app/(tabs)/index.tsx`) threw `TypeError: Cannot read properties of undefined` and the app's ErrorBoundary swallowed it into "Something went wrong" — so the tab bar (and `Profile`) never rendered. The error is a `console.error` from the ErrorBoundary, NOT a `pageerror`, so the test (which only logs `pageerror`) hid it. The test's catch-all mock returns `{data: []}` for every endpoint; Home read `q.data?.totals.total_clicks` and `q.data?.recent_links.length` — the `?.` only guarded `q.data`, so when `q.data` was the truthy `[]`, `.totals`/`.recent_links` were undefined and the next property access crashed.

**Fix:** deepen the optional chaining in `app/(tabs)/index.tsx`: `q.data?.totals?.X` and `q.data?.recent_links?.length`. Also a real defensive win for fresh users whose dashboard stats come back empty/partial.

**Why it looked OTP/demo-specific & flaky:** every sign-in path lands on Home, so all of them hit the crash; which step the timeout reported first just depended on CPU/timing (locally the first Demo login; in managed runs the OTP step). `e2e-mobile-google` reported pass under different timing, masking it.

**How to debug app crashes this test hides:** the harness only logs `pageerror`. To see ErrorBoundary `console.error`, drive the flow with a standalone Playwright script that listens to `page.on('console')` and points at the already-running expo web server (`https://$REPLIT_EXPO_DEV_DOMAIN/`, hot-reloads your edits) — far faster than the test's throwaway-server boot. Reuse `reachLoginScreen` from `scripts/check-icon-fonts.mjs`.

Also: don't run multiple e2e suites at once and don't add a tight external `timeout` to a full run — the core suite now runs to completion well past 120s (demo→OTP→5 oauth-callback variants→6 social providers), and CPU contention makes Expo `page.goto` flake. The bash tool's 120s cap can't hold a full run; rely on the managed validation gate for the authoritative pass.
