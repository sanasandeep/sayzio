---
name: Throwaway-Expo e2e harness termination guard
description: Mobile e2e harnesses must end with runHarness(); manager API is acquireServer; hangs are now impossible by design.
---
# Throwaway-Expo harness exit + manager API

`createExpoServerManager(log)` returns `{ acquireServer(label, explicitUrl) }`
(NOT start/stop — older harnesses written against a start() API throw
"manager.start is not a function"). `acquireServer` returns
`{ appUrl, child, explicit }` or null (callers SKIP on null).

**Termination guard (July 2026):** every harness in
`artifacts/1inme-mobile/scripts` must end its file with
`runHarness(main, { log, onError })` from `expo-web-server.mjs` instead of a
bare `main().catch(...)`. runHarness process.exit(0)s the moment main
resolves, routes rejections through `onError` (harnesses put their
skip-vs-fail infra classifiers there) then exits 1, and arms a deliberately
REF'D watchdog (default 15 min) that force-exits 1 if main never settles —
ref'd so a stuck-await with a drained loop hits exit 1 instead of silently
exiting 0. Both throwaway spawners (`expo-web-server.mjs` and
`native-bundle.mjs`) also `child.unref()` so a detached Metro child can never
keep the harness event loop alive on a missed exit path.

**Why:** a harness once printed PASS then hung the validation run for over an
hour because the detached Expo child kept the event loop alive.

**How to apply:** new `test-*-e2e.mjs` scripts must use acquireServer + end
with runHarness; never rely on the event loop draining or on hand-copied
process.exit(0) calls.
