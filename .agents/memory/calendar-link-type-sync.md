---
name: Followable calendar link type — Google push sync
description: How the followable `calendar` link type's two-way Google sync and plan gating reuse existing machinery.
---

# Followable calendar link type — Google push sync

The `calendar` links.type is a user-owned publishable event collection (distinct from the `ics`
import link type). It reuses the existing CalendarAccount / CalendarEventProvider / CalendarEventMirror
machinery rather than introducing a parallel sync stack.

## Plan gating
- Push-to-Google sync reuses the EXISTING `calendar_sync` plan-feature key — do NOT mint a new key.
  `getPlanFeature('calendar_sync', false)` gates the editor UI, the web `user.calendars.sync` route
  (in-controller check), and the mobile `capabilities.calendar_sync` flag in UserResource.
- Module/cap keys: `module_calendar`, `max_calendars`, `max_calendar_events`.

## Mirror keying
- `ics` import links key CalendarEventMirror rows on `link_id`.
- Followable-calendar push keys the SAME table on `calendar_event_id` (migration made `link_id`
  nullable + added nullable `calendar_event_id` + index). A push row has `source='push'`, `link_id=null`.
- **Why:** lets one table serve both import (pull) and followable-calendar (push) without a second table.

## Two-way (pull-back) for followable calendars
- True two-way = push (Sayzio→Google) PLUS `CalendarSyncService::pullCalendar()` (Google→Sayzio).
  `pullCalendar` is RECONCILE-ONLY: it only updates/deletes events Sayzio already pushed (matched by
  `source='push'` mirror's `external_event_id`), never imports the owner's private Google events as new
  followable events. Sayzio-only fields (payment_url/lat/lng/hashtags/params) are never overwritten —
  Google doesn't carry them.
- Conflict policy: `syncCalendar()` (manual button) runs pull-then-push so a Google edit wins over a stale
  local copy; the scheduled `calendars:pull-published` (every 15 min, push_enabled google accts, plan-gated)
  is pull-only and is what makes "manage from Google, followers see updates" work automatically.
- Delete-on-pull is guarded: only deletes a local event when the listing succeeded with zero errors AND the
  event's start_at falls inside the listed [from,to] window — mirrors syncAccount's "don't wipe on transient
  outage" safety.

## Surfaces that must stay in lockstep for a calendar filter change
- Public page: `RedirectController::handleCalendarPage` query + `$filters` array + `common/calendar-page.blade.php` inputs.
- My Calendar (web): `CalendarController::myCalendar` + `user/calendars/my-calendar.blade.php`.
- My Calendar (API): `MyCalendarController::feed`. Per-calendar filter only honoured when the id is in the
  user's owned-or-followed set (`$calendarIds->contains((int)$id)`), else ignored.

## Route names (easy to get wrong)
- Connected-accounts page: `user.calendar.index` (settings-prefixed), NOT `user.calendar.accounts`.
- Upgrade page: `user.upgrade`.
