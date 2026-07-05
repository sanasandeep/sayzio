---
name: Cross-page promo band opt-out
description: How single-event/RSVP-adjacent pages suppress the shared layout's "Discover Events" promo band without a route-name check.
---

`public/layouts/site.blade.php` includes `common/partials/events-hero-band.blade.php`
on every page except the events directory (`events.index`), guarded by
`@unless(request()->routeIs('events.index') || request()->attributes->get('suppress_events_hero_band'))`.

Pages that already have their own hero (e.g. `common/event-page.blade.php`, the
single-event page) opt out by setting the request attribute in their top `@php`
block, before `@extends` renders the layout:

```php
request()->attributes->set('suppress_events_hero_band', true);
```

**Why:** these pages don't have a dedicated named route worth special-casing in
the layout (they share the generic `{alias}` catch-all), and a route-name check
in the layout would need to know about every consumer. A request attribute lets
the *page* declare the opt-out locally, keeping the layout generic.

**How to apply:** if you add another page type that extends `site.blade.php` and
needs to hide the band (or any other cross-page injected chrome), set the same
kind of request attribute in that page's `@php` block rather than adding another
`routeIs()` branch to the layout.

Pages that DON'T extend `public.layouts.site` (e.g. `rsvp-form.blade.php`,
`event-ticket.blade.php` — standalone HTML documents) never see the band at all,
regardless of this flag.
