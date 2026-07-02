---
name: My Calendar ICS subscription feed
description: How the aggregated "My Calendar" live ICS feed is authenticated and served.
---

# My Calendar ICS subscription feed

The aggregated "My Calendar" (events across every calendar a user owns OR
follows) is subscribable as a live ICS feed by external calendar apps, distinct
from the per-`Calendar` `public.calendars.ics` feed.

- **Auth = per-user token, not session or signed URL.** `users.my_calendar_feed_token`
  (nullable, unique) is lazily minted by `User::myCalendarFeedToken()` and rotated
  by `regenerateMyCalendarFeedToken()`. A signed URL couldn't be individually
  revoked; the stored token can (Reset link on `/user/my-calendar`).
- **Route is public + multi-segment**: `public.calendars.mine.feed` =
  `/calendars/feed/my/{token}/calendar.ics`. Multi-segment keeps it clear of the
  single-segment `/{alias}` catch-all (same trick as `public.calendars.ics`).
- **Unknown/rotated token → empty-but-valid VCALENDAR at HTTP 200**, never 404.
  **Why:** calendar apps poll silently; a hard error on a stale URL is noise, an
  empty calendar just shows nothing until re-subscribed.
- **ICS body is shared** with the one-time export via
  `CalendarController::composeMyCalendarIcs()` (aggregate builder with per-event
  `X-CALENDAR` + cross-tz `TZID`). The feed mirrors the export's *default* agenda
  filter (forward-looking, `start_at >= today`), capped at 5000 events.
