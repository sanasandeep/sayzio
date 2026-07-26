---
name: Zio Browser "On Sayzio" site detection privacy gate
description: Rules for the site-resolve host lookup in the browser chrome
---

The "On Sayzio" chip resolves the active tab's hostname against the public Sayzio endpoint `GET /api/v1/resolve/site?host=` (matches verified+active user-owned custom domains only; global platform domains excluded).

**Rule:** the lookup must never run in private windows and only runs for signed-in users; results are cached per-host for the session and the request is debounced (~800ms) after navigation.

**Why:** privacy-first constraint — sending visited hostnames to sayzio.app is host telemetry; architect review blocked an ungated version. Signed-in users already have a first-party relationship; private windows must leak nothing.

**How to apply:** any new feature that phones home with browsing data (URL, host, title) needs the same gate: `isPrivate` hard-off + signed-in (or explicit opt-in).
