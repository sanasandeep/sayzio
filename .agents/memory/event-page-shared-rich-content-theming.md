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

**Light-mode completeness rule:** every base color rule MUST have a paired
`html.light-mode <same-selector>` counterpart, or that element stays its
dark-theme color on the white light-mode card and washes out. This is AUTOMATED
and GENERALIZED: validation gate `light-mode-pairing`
(`scripts/src/check-light-mode-pairing.ts`, `pnpm --filter @workspace/scripts run
check:light-mode-pairing`, `--explain` for details) checks a CONFIGURED SET of
pages via a `TARGETS` array — currently the event page, the FAQs page, the events
directory (`common/events-directory.blade.php`) and the creator events page
(`common/creator-events.blade.php`). Adding a page is a one-entry append to
`TARGETS`. It parses each page's `<style>` block(s) and fails if any base rule
setting `color`/`border-color` lacks its `html.light-mode <sel>` peer for the
SAME property. Matching is property-level and per-individual-selector (grouped
comma selectors split), so a missed `color` still fails even if `border-color`
is paired. A base selector counts as paired when a light rule matches it EXACTLY
or via an ANCESTOR-PREFIXED (suffix) form — `lightSel.endsWith(" " + baseSel)`,
i.e. the light override adds an ancestor and ends with the whole base selector
after a boundary space. This lets pages whose base classes are bare (e.g.
`.hashtag-pill`) pair with light overrides that add a `.events-page-body` (or
similar) ancestor. The leading-space boundary is deliberate: it prevents false
pairs where only a class SUFFIX coincides (`.pill` must NOT pair `.hashtag-pill`)
or where classes are compounded without a descendant combinator (`.border` must
NOT pair `.a.border`). Each target runs whole-page by default, or scoped to a
wrapper via
`scopes: [".wrapper"]` when the page also has intentional always-dark islands
(e.g. a hero over a dark image); `@keyframes` blocks are stripped before parsing
so animation percentage steps are never mistaken for color-carrying selectors.
Intentional un-paired rules go in that target's `allowlist`
(selector+property+reason), not by weakening the parser (e.g. `.ev-input:focus`
blue focus ring; FAQs blue accent borders).

**Inline-styled partials are ALSO covered on the event page** (via a target's
optional `partials` array): the event right-column `event-connection-tips`
(`.ev-connection-tips`/`.ev-connection-tip-card`) and `link-type-pairings`
(`.ltp-pairings`) partials bake dark colors as INLINE `style="…color:{{ $var }}…"`
attributes, so there's no base `<style>`-block rule to selector-pair against —
only their `html.light-mode` overrides live in the page. base→light pairing can't
reach them, so the guard falls back to a STRUCTURAL COUNT proxy (`checkPartial`):
it counts THEMED inline `color` + `border`/`border-color` declarations (values
containing a `{{ }}` blade interpolation; literal values like `inherit` are
skipped, and the partial's own `<style>` hover/transition block is NOT scanned)
in each partial and requires exactly one paired `html.light-mode` override
targeting that partial's scope classes per property. Add/remove a themed inline
color without its override (or leave an orphan override) and the per-property
count trips. **Why a count instead of pairing here:** inline styles have no CSS
selector to key an override to, so 1:1 selector-pairing is impossible; count
parity is the cheapest structural signal that every dark inline color got a light
counterpart. Intentional deltas go in that partial's own `allowlist`
(`{ property, inlineWithoutOverride, reason }`), distinct from the page-level
selector `allowlist`.

Historical misses that motivated the guard: the tips/pairings light overrides
themselves, then `.btn-outline-success` (Interested widget's green `#34d399` →
`#059669`/`#047857` in light, matching `ev-price-free`). Regression tests in
`check-light-mode-pairing.test.ts` (run by the `scripts-tests` gate).
