---
name: Dialer everyday tool architecture
description: How the web + mobile dialer share logic and where caller-ID enrichment lives
---

The dialer (favorites/speed-dial, grouped recents, frequent strip, T9, spam/block
flags, call-log mini-CRM, callback reminders) keeps web + REST + mobile in parity
through one shared read helper.

- **Single source of truth:** `app/Modules/User/Support/DialerData.php` holds every
  read/transform (favorites, groupedRecents, frequent, activityFor, transformLog,
  transformFavorite). Both `User\Controllers\DialerController` (web) and
  `Api\Controllers\DialerController` (REST) call it — never re-derive shapes per
  controller, change them here once.

- **History shape is `{ recents, frequent }`**, not a flat list. `GET /dialer/history`
  returns grouped recents (deduped by E.164, with `calls` count, `last_human`,
  outcome/note/tag, is_spam/is_blocked) plus the frequently-contacted strip.

- **Caller-ID enrichment only comes from `POST /dialer/lookup`.** The mobile `Contact`
  type (`lib/api/contacts.ts`) has NO biolink field — `listContacts` won't tell you if
  a number maps to a 1INME user. Only the lookup endpoint returns `biolink`/`is_spam`/
  `is_blocked`/`is_favorite`/`activity`.

- **Mobile recents are layered:** local AsyncStorage recents (numbers dialed before a
  server roundtrip, or non-E.164 rejects) merge with server `recents`; only local rows
  are removable on-device.

- **Callbacks:** `dialer.callback_due` notification, swept by the
  `dialer:send-callback-reminders` command (everyFiveMinutes). Stamped via
  `callback_at` / `callback_notified_at` columns on `dialer_lookups`.

- Mobile has no datetimepicker dep → callback uses fixed quick-pick presets
  (1h/3h/tomorrow/3d), not a calendar.
