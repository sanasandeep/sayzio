---
name: Calendar event-slot quota UI
description: How the per-plan max_calendar_events allowance is surfaced pre-submit on both web and mobile calendar management surfaces.
---

# Calendar event-slot quota UI

The per-plan `max_calendar_events` cap is surfaced to owners BEFORE they try to add
an event, on both surfaces, so they see the limit one step earlier than the 402/error
on submit:

- **Mobile**: `artifacts/1inme-mobile/app/calendars/[id].tsx` — X/Y-used row + locked
  "Add event" via `usePlanFeatures().numericLimit`/`isQuotaReached`.
- **Web**: `CalendarController::editor()` computes `eventCap`/`eventsUsed`/
  `showEventQuota`/`eventQuotaReached` and passes them to
  `resources/views/user/calendars/editor.blade.php`, which shows "X / Y events used"
  and swaps the "Add event" button for an "Event limit reached — Upgrade" link.

**Rule:** this is display-only. The server still enforces the cap in
`CalendarController::eventCapError()` (called from `storeEvent`). Never rely on the UI
gate alone.

**Fail-open:** a negative cap (-1) is unlimited and shows nothing; the quota only
flips the button to the locked state once a finite cap is actually reached.

**Why:** mirrors the mobile behaviour added earlier for web/mobile parity so web
owners aren't surprised by a submit-time failure.
