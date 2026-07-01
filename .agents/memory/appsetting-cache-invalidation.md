---
name: AppSetting cache invalidation
description: app_settings reads are cached 5min; only put() forgets the key — raw Eloquent mutation leaves stale config served.
---

`AppSetting::get($key)` caches the value for 300s (local file cache in dev).
Only `AppSetting::put()` (and wrappers like `CookieConsentConfig::put()`) forget
the cache key on write.

**The gotcha:** mutating `app_settings` by *raw Eloquent* —
`AppSetting::where('key',...)->delete()` / `->update()` or editing the row
directly — does NOT forget the cache. The old value keeps being served for up to
5 minutes, cross-request (file store), even after the DB row is gone.

**Why this bites:** ad-hoc testing that flips a setting (e.g.
`cookie_consent_config` layout → `takeover`) and then "restores" via a raw
`->delete()` leaves the page still rendering the flipped value from cache. That
silently pollutes other feature/browser specs that assume the default, and looks
like a mysterious flake ("row deleted but page still shows takeover").

**How to apply:** to change or reset an app setting for real, go through
`AppSetting::put()` / the typed `*::put()` wrapper, OR run
`php artisan cache:clear` after a raw mutation. Never raw-delete/update the row
and expect the change to take effect on the next request.
