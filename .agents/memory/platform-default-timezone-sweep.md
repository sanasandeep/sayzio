---
name: Platform default timezone (UTC→IST) sweep
description: How Sayzio's "personal timezone, else platform default" fallback is centralized, and where hardcoded UTC fallbacks tend to hide.
---

`App\Support\PlatformTimezone` (`artifacts/1inme/app/Support/PlatformTimezone.php`) is the single source of truth for "user's chosen timezone, else the platform default" (default = Asia/Kolkata, overridable via `config('app.platform_default_timezone')`). `config('app.timezone')` stays `UTC` — that's the storage/framework timezone, not the display fallback, and must never be used as a display-fallback default.

**Why:** a plain grep for `'UTC'` literals is not enough to find every fallback site — hardcoded `?: 'UTC'` / `?? 'UTC'` / `config('app.timezone') ?: 'UTC'` patterns are scattered across models (create-time `$attributes` defaults, settings-array reads), controllers (record-creation defaults, ad-hoc per-request fallbacks), console commands, calendar sync (push/pull payload building), and blade views — not just the "obvious" user-profile/calendar files. Several were only found via a second broader sweep after the first pass (grep for `timezone.*UTC` case-insensitively across `*.php`), including: `ServiceBookingController` (both Api and User) create-default, `Link::isWithinActiveWindow/nextActiveWindowOpening` (per-link settings-array fallback), `WhatsAppAgentTools::createEvent` (agent-created calendar events), `CalendarSyncService` (4 payload-building spots), `BiolinkThemeApiController`/`BiolinkThemeController` (theme schedule timezone), `BiolinkBlock::matchesTimeSlot` (display-rule schedule evaluation), and `service-booking-status.blade.php`.

**How to apply:** when centralizing a per-user-with-fallback concept like this, do a first grep for the naive pattern, then a second broader sweep (`timezone.*UTC|UTC.*timezone` or similar) across the whole app layer — model `$attributes`, controllers, services, console commands, and blade views all need checking independently. Raw external-data parsing (e.g. Google Calendar API responses, ICS ingestion normalization) is legitimately out of scope and should keep its `'UTC'` fallback — only *user-preference* fallbacks should route through the helper.

**The sweep missed the form controls.** The app layer was done; the Blade
`<select>` defaults were not, so Create Event still opened on "UTC" — the
one value guaranteed to be wrong for whoever is filling the form in. The
sites were `create-ics` (`old('timezone', 'UTC')`), `edit-ics`
(`old('timezone', $ics->timezone ?: 'UTC')`), `links/directory/editor`
(`$s['timezone'] ?? 'UTC'`) and `links/catalog/editor`, which hardcoded
`'Asia/Kolkata'` directly — the right answer by the wrong route, and so
invisible to a grep for `UTC`. Meanwhile `ai/mind`, `planner/plan` and
`create-calendar` already called the helper, so the convention existed
and simply had not been applied everywhere.

**Form defaults now go one step further than the backend fallback:** the
timezone the browser reports, then the user's saved one, then the
platform default. `user/partials/timezone-autodetect.blade.php` does the
first part, opted into per control with `data-tz-autodetect`, and only on
NEW-record forms — a stored timezone is a decision the organiser already
made and attendees already read, so an edit form must never let a
browser in another country silently change it. The attribute is emitted
only when `old('timezone')` is empty, so a validation redisplay keeps
what the person actually submitted.

`EventFormsStartInYourTimezoneTest` holds both halves, including a
source-level check that no timezone control hardcodes a fallback again —
because the next one of these will be added by copying an existing
control, not by reading this note.
