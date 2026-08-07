---
name: Geocoding autocomplete proxy
description: Map-picker autocomplete must go through /user/geo/suggest, never browser→Nominatim; paste parsing is host-gated and destination-aware.
---

# Geocoding autocomplete proxy

Public Nominatim bans client-side autocomplete (~1 req/sec total). The shared map picker (`resources/js/map-pin-picker.js` `suggestPlaces()`) therefore calls the Laravel proxy `GET /user/geo/suggest` (GeoSuggestController: auth + `throttle:30,1`, 7-day cache keyed on lowercased query, global 1-req/sec upstream gate via `RateLimiter::attempt` — note attempt() normalizes falsy callback returns to `true`, so capture results by reference). Busy gate/failed upstream → empty `{suggestions:[]}`, never cached.

**How to apply:** any new surface adding search-as-you-type location lookup must reuse `suggestPlaces()`/this proxy — a code reviewer will reject direct browser Nominatim autocomplete. One-shot reverse/forward geocodes (pin drag, single search) may stay client-side.

Other picker rules learned via review:
- `handleLocationPaste` only intercepts recognized map hosts (google.*, goo.gl, apple.com, openstreetmap.org, osm.org); other URLs fall through to default paste untouched.
- `/maps/dir/<origin>/<dest>` parsing must pick the DESTINATION (textual → name, coordinate → lat/lng; `/data=!…` suffix excluded; `!3d!4d` pin wins).
- All suggestion dismissals (Escape, outside click, choose, paste) go through `dismissSuggestions()` which clears the debounce AND bumps `_suggestReq` so in-flight responses can't repopulate.
- Unit coverage: `node scripts/test-map-pin-picker.mjs` (vm-loads the file); feature coverage: `tests/Feature/GeoSuggestProxyTest.php` (needs `use RefreshDatabase;` or ephemeral test DB has no tables).
- The `.mpp-suggest` dropdown CSS is copy-pasted per host page (edit-ics, biolink-editor, calendars/editor) — follow-up task exists to centralize.
