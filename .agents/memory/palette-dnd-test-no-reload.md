---
name: Biolink palette DnD spec expects a navigation that no longer happens
description: Why tests/Browser/biolink-editor-palette-dnd.spec.ts times out on waitForNavigation regardless of palette markup changes
---

`tests/Browser/biolink-editor-palette-dnd.spec.ts` drops a palette tile and then
`await page.waitForNavigation(...)`, expecting a full page reload on success.

But the palette-create path (`paletteCreateBlock` in `biolink-editor.blade.php`)
inserts the new block **in place** and does NOT reload — only the
move-existing-block (`onAdd` → `doMoveBlock`) and card-add paths call
`location.reload()`. So the drop tests time out on the navigation wait.

**Why this matters:** if you change the "Add blocks" palette markup/Alpine and
this spec fails on `waitForNavigation`, you almost certainly did NOT break it —
the failure is pre-existing and independent of the palette grouping/search/
collapse code, which never touches the drop pipeline.

**How to apply:** the palette tiles' drop contract is `.palette-block-item`
elements (flat direct children of `#paletteList`) carrying `data-block-type`;
keep that intact and drops keep working. To actually fix the spec, drop the
`waitForNavigation` and assert the in-place insert instead.
