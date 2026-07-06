---
name: Event host-card dual-context box styling
description: event-host-card.blade.php renders both bare (RSVP page) and inside a themed wrapper (event page) — its own card box and avatar accents must be self-sufficient and theme-neutral.
---

`common/partials/event-host-card.blade.php` is included two ways: standalone inside
`event-rich-content.blade.php` on the plain light Bootstrap RSVP page (no card
wrapper around it), and directly by `event-page.blade.php` in its dark right-hand
column (wrapped only in a `.ev-rich` label container, not a second `.ev-card`).

**Why:** giving the partial's own box a CSS class that's only defined in one
consumer's `<style>` block (e.g. an `.ev-card`-like class scoped to event-page)
would leave it unstyled on the RSVP page. Wrapping it in `.ev-card` from the
event-page side too would nest a card-in-a-card look there.

**How to apply:** the partial supplies its own box background/border via inline
theme-neutral `rgba(...)` values (works acceptably on both a white and a dark
background) instead of a page-scoped class, and reuses the pre-existing
Bootstrap classes (`text-dark`, `text-muted`, `border`, `a.border`,
`rounded-pill`) that both `event-page.blade.php`'s `.ev-rich` override block
and `rsvp-form.blade.php`'s native Bootstrap already theme correctly — so no
new light-mode-pairing entries are needed. Avatar accent rings use inline
`box-shadow` (not a `color`/`border-color` rule in a `<style>` block), which
the `light-mode-pairing` guard doesn't scan, so it renders identically in both
themes without requiring a paired override.
