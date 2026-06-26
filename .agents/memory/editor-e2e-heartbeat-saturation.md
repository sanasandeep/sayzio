---
name: Editor e2e flake = preview-iframe tracking heartbeats
description: Why the biolink-editor browser e2e suite flaked locally and the test-flag gate that fixed it
---

The 1inme biolink-editor browser e2e specs (palette drag-and-drop, card-gallery) flaked
intermittently in the isolated dev box, and failures *cascaded* into unrelated specs
(cookie-consent, home-auth) all timing out uniformly at ~30s.

**Root cause:** the editor's device-preview iframe loads the full public biolink page,
whose engagement-tracking script fires a `track/heartbeat` POST every 15s (plus a
`track/session` start). Over the distant cross-region RDS each beacon took ~9s and
continuously occupied several of the few `PHP_CLI_SERVER_WORKERS`, starving the editor's
own block-store/render AJAX. Symptom: `expect.poll` for the rendered block list stays
*stuck* (e.g. only `["Divider","Heading"]`, siblings missing) for the full timeout, and
the saturation spills over to later specs (requests 9–17s). The serve log shows NO
errors — pure latency saturation, not a crash.

**Fix:** gate all tracking beacons behind the browser test flag `window.__E2E__`
(`if (window.__E2E__) return;`) in `common/biolink.blade.php` and
`common/partials/engagement-tracking.blade.php`. `page.addInitScript` sets the flag and
it propagates to child iframes, so the preview stops beaconing. Zero production impact
(`__E2E__` never set in prod). Editor specs set the flag in their editor-open helper.

**Why:** removing the heartbeat load dropped most requests from 9–17s to 2–9s, all 7
editor specs went green (END + INSIDE were the stubborn ones), the cookie-consent
cascade vanished (30s timeouts → 3–10s passes), and total e2e dropped ~20–24m → ~15m.

**Also in the same suite (complementary test-code hygiene):** read the editor block
list as one atomic `page.evaluate` snapshot and assert via retrying
`expect.poll(...).toEqual(...)`, never a one-shot `expect(await ...).toEqual(...)`
(catches mid-re-render states). Wrap the `php artisan tinker` seed in a 3-attempt retry
(`runTinkerSeed`) — it transiently fails over distant RDS.

**How to apply:** any new heavy editor/preview e2e that renders a public biolink should
arm `window.__E2E__` before navigating, or it will re-introduce the heartbeat saturation.
