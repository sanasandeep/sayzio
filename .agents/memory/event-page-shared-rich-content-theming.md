---
name: Event page shared rich-content partial theming
description: common/partials/event-rich-content.blade.php is Bootstrap-styled and shared by both the dark-glass ticketed event page and the light RSVP page — restyle via a scoped wrapper class, never edit the partial itself.
---

`event-page.blade.php` (ticketed/general public event page) and `rsvp-form.blade.php`
(RSVP page) both `@include('common.partials.event-rich-content', ...)` for the
cover/gallery/hashtags/Interested-widget/Similar-events/More-from-this-host sections.
That partial uses raw Bootstrap classes (`badge`, `btn-outline-*`, `border`, `rounded-3`,
`row g-2`, `col-6`/`col-4`, `text-dark`, `text-muted`, `h-100`) with no Bootstrap CSS
loaded on the Tailwind-only dark page — so on `event-page.blade.php` these rendered
as unstyled/illegible dark-on-dark boxes, and Tailwind's `.border` utility (not
Bootstrap's) combined with `h-100`/flex made card heights blow up unpredictably.

**Why:** The partial is intentionally shared so the two public event surfaces stay
in visual sync (per its own header comment); editing it directly to add dark-theme
classes would break the RSVP page's light Bootstrap theme.

**How to apply:** Wrap the `@include` in a `<div class="ev-rich">` and add scoped
CSS overrides (`.ev-rich .badge`, `.ev-rich .text-dark`, `.ev-rich .border`, a
flexbox reimplementation of `.row.g-2 > .col-6/.col-4`, and `.ev-rich .h-100 { height:auto !important }`)
in the consuming page's own `<style>` block instead. Never restyle the shared
partial itself for one consumer's theme.
