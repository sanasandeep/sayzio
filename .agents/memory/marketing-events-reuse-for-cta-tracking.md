---
name: Reuse marketing-events pipeline for anonymous CTA tracking
description: How to add server-side click tracking for a new anonymous public CTA without a new table/endpoint.
---

# Reuse the marketing-events pipeline for new anonymous CTA click tracking

For any new anonymous/public click-tracking need (no auth, fire-and-forget),
reuse the existing marketing-events pipeline instead of a new table/endpoint:

- `MarketingEvent` model + `marketing_events` table + `MarketingEventController::track`
  (route name `marketing-events.track`, CSRF-exempt, allow-listed, throttled).
- Admin report at `admin.marketing-events.index` (`MarketingEventStatsController`)
  auto-renders a section per `source` from the ALLOWED map — no view change needed.

**How to apply (all lockstep):**
1. Add the new `source => [targets...]` to `MarketingEventController::ALLOWED`
   (validation rejects anything not listed).
2. Add matching `SOURCE_LABELS` + `TARGET_LABELS` entries in
   `MarketingEventStatsController` (falls back to the raw slug if omitted).
3. Client: `sendBeacon`/`fetch(keepalive)` a FormData `{source,target}` to
   `route('marketing-events.track')`; guard with `if (window.__E2E__) return;`.

**Why:** two dimensions (e.g. surface + item type) fit cleanly by encoding one in
`source` and one in `target` (used for event-connection tips: source
`event_tips_directory`/`event_tips_event`, target = the tip's link `type`, kept in
lockstep with `SitePagesContent::eventConnectionTips()`). No migration, and the
admin aggregate view is free.
