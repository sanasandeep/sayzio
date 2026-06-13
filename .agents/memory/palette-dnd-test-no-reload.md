---
name: Biolink palette DnD spec expects a navigation that no longer happens
description: Why tests/Browser/biolink-editor-palette-dnd.spec.ts times out on waitForNavigation regardless of palette markup changes
---

The palette-create path (`paletteCreateBlock` in `biolink-editor.blade.php`)
inserts the new block **in place** and does NOT reload — only the
move-existing-block (`onAdd` → `doMoveBlock`) and card-add paths call
`location.reload()`. The spec's `dropPalette` helper must reflect that: it
asserts the target list (`#blockList > .block-card-wrapper` or
`.card-child-list[data-card-id] > .child-block-card`) gains one card and the
"Block added" success toast shows — NOT `waitForNavigation` (which used to time
out, since no reload ever happens).

**Why this matters:** if you change the "Add blocks" palette markup/Alpine and
this spec fails, it is NOT a navigation issue — the drop pipeline never reloads.

**How to apply:** the palette tiles' drop contract is `.palette-block-item`
elements (flat direct children of `#paletteList`) carrying `data-block-type`;
keep that intact and drops keep working.
