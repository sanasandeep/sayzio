---
name: External CDN single-point-of-failure for Alpine.js
description: Self-hosting Alpine on the 1inme Laravel app — a reliability hardening, NOT the fix for the "menus don't work" complaint (that was the cookie-consent wall; see consent-backdrop-blocks-nav.md).
---

# Alpine.js (and other CDN libs) as an intermittent-failure source

On the 1inme Laravel app, the marketing/public/auth/admin/dashboard pages are
Blade + Tailwind(CDN) + Alpine.js. ALL nav dropdowns, the mobile menu, theme
toggle, and the auth-modal buttons are pure Alpine (`x-data`/`@click`/`x-show`).
If Alpine fails to load, every one of those silently stops responding — with NO
console error (the script just never arrived). Plain `<a href>` links still
navigate, so the symptom reads as "menus and buttons don't work, sometimes."

**IMPORTANT — this was NOT the cause of the recurring "menu clicks not working"
complaint.** That symptom was the full-page cookie-consent modal backdrop eating
all clicks (see `consent-backdrop-blocks-nav.md`). Alpine was verified fully
booted (`window.Alpine` object, `[x-cloak]`=0, nav `_x_dataStack` bound) while
the menus still "didn't work." Don't chase Alpine/CDN first for that symptom —
check `document.elementFromPoint()` over the nav for an overlay before anything.

**Self-host is still worth keeping (reliability hardening):** Alpine was loaded
from an external CDN with a floating version range —
`https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js` (and one file used
`//unpkg.com/alpinejs`). A floating `@3.x.x` adds a redirect/version-resolution
lookup, and any CDN slowness/throttle/block leaves Alpine uninitialized.

**Fix / rule:** self-host interaction-critical JS. Pinned Alpine lives at
`public/js/vendor/alpine.min.js` and every Blade references it via
`{{ asset('js/vendor/alpine.min.js') }}` with `defer`. Keep `defer`. Pinning a
version (vs a range) also stops surprise CDN/version drift.
**Why:** anything the page can't function without must not depend on a 3rd-party
CDN being up at first paint.
**How to apply:** when adding a Blade page that needs Alpine, point at the local
asset, not a CDN. If you bump the Alpine version, replace the file in
`public/js/vendor/` (it is NOT gitignored, so it ships to production).

**Still on external CDNs (lower severity — styling/feature, not dead clicks):**
Tailwind (`cdn.tailwindcss.com`, also logs a prod warning), FontAwesome
(cdnjs), chart.js, sortablejs, maplibre, jspdf, jszip, fullcalendar. Same SPOF
class; self-host if they start causing flakiness.
