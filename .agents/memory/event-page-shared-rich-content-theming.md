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

**Light-mode completeness rule:** every base `.ev-rich <x>` color rule MUST have a
paired `html.light-mode .ev-rich <x>` counterpart, or that element stays its
dark-theme color on the white light-mode card and washes out. This is now
AUTOMATED: validation gate `event-light-mode`
(`scripts/src/check-event-page-light-mode.ts`, `pnpm --filter @workspace/scripts
run check:event-light-mode`, `--explain` for details) parses the page's `<style>`
block and fails if any base `.ev-rich <sel>` rule setting `color`/`border-color`
lacks its `html.light-mode .ev-rich <sel>` peer for the SAME property. Matching is
property-level and per-individual-selector (grouped comma selectors split), so a
missed `color` still fails even if `border-color` is paired. Scope is `.ev-rich`
only — the other inline-styled partials (`event-connection-tips`,
`link-type-pairings`) bake dark colors as inline styles inside the partials, so
there's no base rule in the `<style>` block to pair against (only their light
overrides live here) and they're intentionally out of guard scope. Historical
misses that motivated the guard: the tips/pairings light overrides, then
`.btn-outline-success` (Interested widget's green `#34d399` → `#059669`/`#047857`
in light, matching `ev-price-free`). Intentional un-paired rules go in the script's
`ALLOWLIST` (selector+property+reason), not by weakening the parser. Regression
tests in `check-event-page-light-mode.test.ts` (run by the `scripts-tests` gate).
