---
name: Event page recommendation lazy-load
description: Why the RSVP/ticketed event page's "Similar events"/"More from this host" widgets are fetched client-side instead of computed inline, and the gotcha this creates for tests.
---

The RSVP page and shared ticketed event page (`RedirectController::eventPageExtras()`) must always render fast and complete — a cache+lock wrapper around the slow recommendation queries (hashtag LIKE scan, same-host lookup) is NOT sufficient, because the first request per cache TTL window (or the lock-holder) still pays the full query cost synchronously before the page can render, reopening the exact blank/502 symptom under a cold cache.

**Fix:** `eventPageExtras()` only ever does a cache read (plus one cheap inline interest-count query) and never computes the recommendations itself. On a cache miss it returns them empty immediately with `extrasPending = true`. A separate `GET /{alias}/event-extras` route (`eventPageExtrasFragment()`) does the actual lock-guarded compute+cache (60s TTL, 10s lock) off the render path and returns a rendered HTML fragment (`event-page-recommendations.blade.php`); the page's inline JS lazy-fetches it after load when `extrasPending` was true.

**Why:** "never block or blank the core page for an optional widget" beats "make the common case fast" — a wrapper that's merely cached-and-locked still has a slow-path that runs on the request thread.

**How to apply:** any Feature test asserting recommendation content (e.g. "More from this host") appears on the FIRST `GET /{alias}` or `/{alias}/rsvp` load will now fail — that content only appears inline once the cache is warm. Warm it first via `GET /{alias}/event-extras`, or assert on that fragment endpoint directly instead of the page.
