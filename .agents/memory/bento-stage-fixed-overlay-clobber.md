---
name: bento-stage child-selector clobbers fixed overlays
description: A shared bento-grid CSS rule can silently downgrade a nested fixed-position modal to position:relative via a specificity tie-break, breaking it without touching JS/Alpine state.
---

`bento-styles.blade.php` (used by Dashboard/Links/Stats and any other
`.bento-stage` page) sets `.bento-stage > * { position: relative; z-index: 1; }`
to keep bento-tile stacking contexts correct. This has the SAME CSS
specificity as Tailwind's `fixed`/`z-[n]` utilities, and since bento
styles are pushed via `@push('styles')` and render later in `<head>`
than the compiled Tailwind bundle, the tie-break favors `.bento-stage > *`.

**Why this matters:** if a fullscreen modal/overlay is ever included as
a *direct child* of `.bento-stage` (instead of a sibling after it
closes), its `position: fixed` silently becomes `position: relative`
and its `z-index` gets stomped to 1. The overlay's own toggle logic
(e.g. Alpine `open` state) still works correctly — only the CSS
positioning breaks — so it renders in-flow, often far below the fold,
looking exactly like "the button that opens it does nothing." Every
automated check of JS/Alpine state passes; only `getComputedStyle()`
or a real viewport screenshot reveals the bug. Confirmed on the
"Customize dashboard" modal.

**How to apply:** any modal/overlay meant to be `fixed`/full-viewport
must be included as a SIBLING after `.bento-stage`'s closing `</div>`,
never nested inside it. If a dashboard "button does nothing" bug shows
correct Alpine/JS state in every test, check `getComputedStyle(modalEl).position`
before further JS debugging — the click handler is probably fine.
