---
name: Event page right-column relocation (map + host card)
description: How the public event-page.blade.php map and host card were moved into the sticky right column, and how light-mode text overrides for baked-in-dark-theme partials work live without reload.
---

## Host/organizer card extraction
- `event-rich-content.blade.php` originally rendered the "Hosted by" card inline. It's now a separate partial `event-host-card.blade.php` (`$host`, `$organizer` props), included by default from `event-rich-content` so the RSVP page (which includes `event-rich-content` unchanged) still gets it inline.
- `event-page.blade.php` passes `hideHostCard => true` to `event-rich-content` and instead renders `event-host-card` standalone inside its sticky right column, below the map, so it's never duplicated.
- Any future consumer of `event-rich-content` that wants the host card elsewhere should follow the same `hideHostCard` + standalone-include pattern rather than re-inlining the markup.

## Light-mode text on partials that only bake dark-theme colors inline
- `event-connection-tips` and `link-type-pairings` partials style themselves with inline styles (no theme-aware classes) tuned for the dark theme they were built for.
- Rather than editing those shared partials (reused elsewhere with their own theme prop), `event-page.blade.php` overrides them via `html.light-mode .ev-connection-tips ...` / `html.light-mode .ltp-pairings ...` positional-child selectors with `!important`, matching the existing `.ev-rich` override pattern.
- This works both on the server-set theme cookie AND the client-side no-reload toggle, since it's a CSS class-scoped override, not a server-rendered prop.
- **Why:** editing the partials' own theme prop would require plumbing a new "light" variant per shared partial and risks breaking other reuse sites; CSS-scoped `html.light-mode` overrides are page-local and safe.
