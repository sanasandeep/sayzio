---
name: Biolink template gallery chips spacing + load race
description: Two gotchas in the Cards "Templates & more" overlay — chip spacing overridden by a padding shorthand, and an AJAX load race that empties the grid.
---

# Cards template gallery (`editor-special-panel.blade.php` + `biolink-editor.blade.php`)

## Chip spacing can't be set with a `pb-*` utility
`.gallery-tabs` (the category chip row: All + categories) has a CSS rule using the
`padding` **shorthand** (`padding: 0 20px ...`). A Tailwind `pb-*` utility on the same
element has equal specificity and loses to the later stylesheet shorthand, so its
`padding-bottom` is reset to 0. **To add space below the chips, edit the `.gallery-tabs`
rule's shorthand (or use margin) — not a utility class on the element.**

## "All" sometimes shows no templates / no scroll = a fetch race, not a CSS bug
Each category chip calls `loadCardTemplates(true)`, which fires a `fetch` with no
cancellation. Rapid chip switching means a slower earlier response can resolve AFTER a
newer one and overwrite `cardTemplates` with stale/empty data — the grid looks empty so
there's nothing to scroll. **Fix pattern:** a monotonic request id (`_cardReqId`); the
`.then/.catch/.finally` must early-return unless `reqId === this._cardReqId` so only the
latest request mutates state (including the loading flag, so the spinner can't stick).
**Why:** the scroll chain itself is fine (special-panel flex + definite-height palette);
the symptom was data, not layout. Don't chase CSS for an intermittent empty grid.
