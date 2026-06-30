---
name: Mobile tests that need the live Expo dev server
description: Which 1inme-mobile checks require the running Expo dev workflow vs. boot their own server, and why they shouldn't be permanent validation gates.
---

Some 1inme-mobile Playwright checks target the **live** Expo dev domain
(`https://$REPLIT_EXPO_DEV_DOMAIN/`) and therefore require the Expo dev
workflow to already be running. The workflow name is `artifacts/1inme-mobile: expo`
(NOT "1INME Mobile"); start it with `restart_workflow`, then `/status` should
return `packager-status:running` before these run.

- **Needs the live server up:** `test:icon-fonts` (check-icon-fonts.mjs's
  direct `main()` reads APP_URL = live dev domain). Any ad-hoc signed-in smoke
  driven against APP_URL is the same. This is the manual/live entry point only.
- **Boots its own throwaway server:** `test:auth-flow-e2e` (core + google) AND
  `test:icon-fonts-e2e` (test-icon-fonts-e2e.mjs wraps the same
  `runIconFontCheck` from check-icon-fonts.mjs) — safe to run anytime; these are
  the appropriate persistent CI gates (`e2e-mobile-icons`). The Expo
  boot/warm/teardown dance is shared in `scripts/expo-web-server.mjs`
  (`createExpoServerManager(log)`), used by BOTH harnesses so they can't drift.

**Why:** A validation command that depends on the live dev server will fail on
*every* future `mark_task_complete` (across unrelated tasks) whenever that server
isn't running. During the pre-launch QA pass the icon-fonts and a temporary
signed-in smoke check were registered as validation commands, then removed for
exactly this reason.

**How to apply:** Run live-domain mobile checks via `startValidationRun`
(no 120s cap; Expo cold boots are slow) only while the expo workflow is up, and
do NOT leave them registered as validation commands. If you want permanent
signed-in-flow coverage, model it on test-auth-flow-e2e.mjs so it boots its own
server instead of relying on the live dev domain.

**Playwright route ordering gotcha:** the most-recently-registered matching route
runs FIRST. Register a broad catch-all (`**/api/**`) BEFORE the specific routes
(`/auth/demo`, `/admin/context`) so the specific ones win; otherwise the
catch-all swallows login and nothing signs in.
