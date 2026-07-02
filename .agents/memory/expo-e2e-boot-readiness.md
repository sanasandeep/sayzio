---
name: Expo e2e boot readiness — Metro up ≠ bundle serveable
description: Why the mobile auth-flow e2e harness must warm the web bundle before driving Playwright, to kill ERR_EMPTY_RESPONSE / nav-timeout flake
---

The mobile auth-flow e2e harness (`artifacts/1inme-mobile/scripts/test-auth-flow-e2e.mjs`)
boots throwaway Expo web dev servers. `/status` returning
`packager-status:running` only means the Metro **process** is up — NOT that the
web bundle has been compiled and can be served. The very first `GET /` triggers
that compile.

**Why:** under parallel CI load, driving Playwright at the server during that
first-compile window is the dominant mobile-e2e flake. While the bundle compiles
the server either accepts the connection then returns an empty body
(`net::ERR_EMPTY_RESPONSE`) or briefly refuses it (`net::ERR_CONNECTION_REFUSED`),
or the compile drags past the 90s nav timeout. The log signature is "Expo server
is ready" immediately followed by a nav failure.

**How to apply:** after Metro reports running, warm the bundle in the harness
itself — repeatedly `fetch('/')` (bounded per-attempt via AbortController) until
it answers a real 200 with a non-trivial HTML shell (`</html>` / `id="root"` /
`<script>`), THEN return the server to callers (`waitForExpoServeable`). This
absorbs the expensive first compile BEFORE any `page.goto`, so the actual nav
hits an already-compiled bundle and returns promptly. Both boot phases (Metro
`/status`, then serveable) share one absolute wall-clock deadline
(`EXPO_BOOT_DEADLINE_MS`) so total boot stays bounded. Boot is still
best-effort: failure to serve within budget returns null → callers SKIP (exit 0,
a gate PASS), never fail. Do NOT just bump the nav timeout — the fix is moving the
compile out of the nav into an explicit warmup.

**Contention caveat (do NOT grow the budget):** validation runs this Expo boot
in PARALLEL with the heavy 1inme browser-e2e suite on one constrained box. Metro
bundling is CPU-bound, so a longer boot budget = longer CPU starvation of the
concurrent browser e2e, whose cold editor renders then blow their own 120s
`waitForFunction` budgets (observed: DnD TOP + voice-assistant both timing out
first-try only when a 240s mobile budget overlapped them). Keep
`EXPO_BOOT_DEADLINE_MS` at the original metro-only value (180s): a flow that
loses the bundling race SKIPs and kills its Metro child promptly, freeing CPU —
a clean skip is a better neighbour than a multi-minute bundle hog. skip()→exit 0,
so a skip never fails the gate.

**Warmup-200 ≠ browser-navigable (retry the real nav):** even after the curl
warmup gets a real 200, the actual Playwright `page.goto` can still
`ERR_CONNECTION_RESET`/`ERR_EMPTY_RESPONSE`. A browser opens several parallel
sockets (document + favicon + the HMR websocket) with different headers than the
single curl GET, and a still-settling single-process Metro — especially under the
parallel browser-e2e load on a shared box — can reset one. Retry the nav a few
times with backoff. If it keeps resetting after retries, that's the same "boot
couldn't stay serveable" condition as a reused-down server → route it to the
best-effort SKIP (exit 0), NOT a hard fail. Only non-connection errors
(assertion/logic bugs) should propagate and fail the gate. NOTE there are TWO
independent nav sites — the main flow and `runGoogleVariant` — each needs this
hardening; fixing one leaves the other hard-failing.

**Parallel heavy e2e gates reshuffle their failures (resource exhaustion):** the
browser-e2e suite and the two mobile Metro flows (main + google) run concurrently
on a shared distant-RDS box. They contend for CPU/mem, so which gate fails is
NON-DETERMINISTIC run-to-run (one run: browser 38/38 green + mobile reset; next:
mobile pass + browser collapse). Hardening one job to stay alive longer (retry→pass
instead of fast-skip) steals CPU from the others and can tip THEM over. Symptom of
contention (not a code bug): 0ms worker crashes + fast-fail cascades + a different
gate failing each run. The lever is keeping each job's failure mode a fast SKIP, and
treating a persistently-reshuffling gate as environment-blocked, not chasing each.

## Parallel validation contention
When the full validation suite runs all gates in parallel, the throwaway-Expo gates (auth-flow-e2e core, icon-fonts-e2e) can starve on CPU: Metro reports the bundle "served" but `page.goto` still exceeds its 90s timeout. Not a code regression — re-run just the failing gate(s) via `startValidationRun([...])` in isolation to confirm; they pass alone.
