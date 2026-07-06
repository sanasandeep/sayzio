---
name: Event location map surfaces
description: Where the Leaflet event-location map is rendered across the web event surfaces and how to add it to a new one.
---
The vendored Leaflet location map for an `ics` event link is rendered **inline in the page views**, NOT in the shared `common/partials/event-rich-content.blade.php` partial:
- `common/event-page.blade.php` — public event page; map in the sticky right column, Leaflet loaded lazily via `@push('scripts')` (the page extends `public.layouts.site`).
- `common/rsvp-form.blade.php` — standalone RSVP page; map inline in the body.

**Gate:** `$hasPin = !$isOnline && $ics && $ics->latitude !== null && $ics->longitude !== null`. Online events and events without coordinates get no map. `$isOnline` comes from `$link->settings['is_online']`; coords from the `icsData` relation.

**Why rsvp-form differs:** `rsvp-form.blade.php` is a self-contained `<!DOCTYPE html>` Bootstrap document — it does NOT extend the site layout, so `@push('scripts')`/`@push('head')` do nothing there. You must inline the Leaflet CSS `<link>` (in `<head>`), the `<script src=".../js/vendor/leaflet.min.js">`, and the init IIFE (before `</body>`).

**How to apply:** to add the map to a new event surface, copy the `#ev-map` div (`data-lat`/`data-lng`/`data-label`) + the init IIFE from event-page.blade.php. Vendored assets: `public/css/vendor/leaflet.min.css`, `public/js/vendor/leaflet.min.js` (self-hosted, no CDN). Marker is a `L.divIcon` blue-gradient SVG pin; call `map.invalidateSize()` after ~80ms so it lays out correctly. Do NOT move the map into event-rich-content — that partial is shared and event-page already renders its own map, so it would duplicate.
