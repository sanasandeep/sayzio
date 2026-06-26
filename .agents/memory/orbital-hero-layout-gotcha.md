---
name: ml-auto collapses absolute-only grid items
description: Why a visual block whose children are all position:absolute can collapse to ~0 width.
---
An auto margin (`ml-auto` / `mx-auto` on a grid item) overrides `justify-self: stretch`, so the item sizes to its *content* width instead of filling the track.

**Why:** A stage/visual whose children are ALL `position:absolute` (rings, planets, hub) has ~0 intrinsic width. Combined with `w-full` (which then resolves against the shrink-wrapped parent), the whole block collapses to near-zero and everything piles up at one edge — looked like the visual was pushed off-screen right.

**How to apply:** For an absolute-children visual, give the stage a DEFINITE width (`w-[480px] max-w-full`) and center/align it with a flex wrapper (`flex justify-center lg:justify-end`), not `mx-auto`/`ml-auto` on the grid item.
