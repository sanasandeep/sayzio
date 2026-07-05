---
name: Events directory hero/near-me/fallback conventions
description: How the /events discover page ranks its hero slider, filters near-me results, and falls back when an event has no cover image
---

- Hero slider ranking is context-dependent: when the visitor supplies lat/lng, rank by proximity (online events pushed to the end of the ranking, never dropped); with no location, rank by trending (interest count desc, then soonest start). Don't default to a flat "earliest upcoming" order — that was flagged as a code-review defect.
- `is_online` events must never be excluded by near-me/radius filtering — they have no coordinates and radius math should only apply to physical events; online events stay visible in both the results list and hero ranking.
- No-cover-image fallback across hero/grid-card/event-page is a real placeholder image asset (`public/images/events/event-cover-placeholder.svg`), not a CSS-gradient + FontAwesome-icon block — keep all three call sites using the same asset.
- Checkboxes bound to JSON-settings booleans (e.g. `is_online`, `hide_from_directory` in `links.settings`) need a `<input type="hidden" name="x" value="0">` placed immediately before the checkbox in the form, or unchecking them silently fails to persist (`$request->has()` is true only when something with that name was submitted).
