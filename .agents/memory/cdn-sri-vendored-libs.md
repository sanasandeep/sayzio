---
name: CDN SRI breaks vendored libs
description: Why third-party browser libs in 1inme are self-hosted instead of CDN+integrity
---

Third-party browser libraries in the 1inme Laravel app must be self-hosted under
`public/js/vendor/` (and `public/css/vendor/` for stylesheets), referenced via
`asset()` with **no** `integrity`/`crossorigin` attributes.

**Why:** cdnjs (and similar CDNs) can re-serve a file whose bytes no longer match
the `integrity` hash baked into the page. When that happens the browser blocks the
resource ("Failed to find a valid digest in the 'integrity' attribute … The
resource has been blocked") and the feature silently dies with no PHP error.
This took down the Leaflet contact-page map (and previously caused intermittent
dead Alpine clicks — see cdn-alpine-spof.md).

**How to apply:** when adding any front-end vendor lib, download the pinned
version into `public/{js,css}/vendor/` and link it locally. Leaflet works fine
self-hosted with a custom `L.divIcon` and no layers control, so its CSS image
refs (marker-icon.png, layers.png) are never requested — no need to vendor those
images.
