---
name: Heatmap API live parity
description: How the web per-link click heatmap (static + SSE live) is mirrored on the Sanctum /api/v1 + mobile.
---

The web per-link heatmap has two surfaces: a static GeoJSON endpoint and an
SSE live stream. When bringing this to the API/mobile, the live stream is
replaced by a **pollable** endpoint, not SSE.

**Rule:** the live API endpoint takes a `lastId` cursor.
- First poll (no cursor): seed the last 5 minutes so the "active visitors"
  pill is correct the moment live mode opens.
- Later polls (`lastId` set): return every click with id > cursor, ignoring
  the 5-min window, so nothing is missed between polls (mirrors the web SSE
  tail loop). `unique_visitors` always reflects the rolling 5-min window.

**Why:** SSE over the Sanctum/mobile path is impractical; a cursor poll gives
the same "no duplicate pins, never miss a click" behavior the stream provides.

**How to apply:** reuse the web data source (`$link->clicks()` — carries the
is_bot=false scope) and replicate `formatLivePoints()`. Auth: the
`workspace.can:stats.view` middleware can't run on the API (no bound
current_workspace), so scope by `Link::where('user_id', auth id)->find($id)`
like the other API analytics endpoints. Mobile renders via WebView + Leaflet +
leaflet.heat (the existing MapPreview WebView pattern), pushing live pulses
through an imperative `addLivePoints` handle so the static heat layer isn't
rebuilt on every poll.
