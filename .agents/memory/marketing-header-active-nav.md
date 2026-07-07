---
name: Marketing header current-page highlight
description: How the Laravel marketing header decides which nav item is "current" and which surface owns each highlight.
---

The shared marketing header's nav arrays carry a route-pattern column (5th
element per item) matched with `request()->routeIs()`; `null` means the item
never claims the highlight.

**Ownership rules (deliberate, keep consistent):**
- The Features page highlights ONLY the top-level Features link — the Product
  mega trigger excludes `site.features` from its group-active check (the panel
  item itself still highlights when open).
- Events pages highlight ONLY the Events pill (`.mkt-events-pill-active` in
  marketing-anim.css, with an `html.light-mode` pair) — the Solutions "Events &
  RSVPs" entry has a null pattern.
- Referrals is an anchor into /features and never claims active.
- Use-case items match by slug via `request()->route('persona')` on
  `site.use-case` (path is `/for/{persona}`, NOT `/use-cases/...`).

**Why:** avoids double-highlighting two menus for one page.

**How to apply:** when adding a header nav item, set its pattern column (or
null) and remember the mobile drawer defaults open the active accordion group
via `$mobileGroupDefault`. Server-rendered active color must be folded into the
Alpine `:class` ternary on triggers, or the static class fights the binding.
