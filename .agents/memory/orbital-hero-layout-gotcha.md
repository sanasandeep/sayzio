---
name: ml-auto collapses absolute-only grid items
description: Why a visual block whose children are all position:absolute can collapse to ~0 width.
---
An auto margin (`ml-auto` / `mx-auto` on a grid item) overrides `justify-self: stretch`, so the item sizes to its *content* width instead of filling the track.

**Why:** A stage/visual whose children are ALL `position:absolute` (rings, planets, hub) has ~0 intrinsic width. Combined with `w-full` (which then resolves against the shrink-wrapped parent), the whole block collapses to near-zero and everything piles up at one edge — looked like the visual was pushed off-screen right.

**How to apply:** For an absolute-children visual, give the stage a DEFINITE width (`w-[480px] max-w-full`) and center/align it with a flex wrapper (`flex justify-center lg:justify-end`), not `mx-auto`/`ml-auto` on the grid item.

## Stacked full-size rotor layers swallow inner-ring clicks
Multiple concentric spinning rings implemented as sibling `position:absolute; inset:0` rotor layers all cover the whole orbit box. The topmost (outer) rotor then intercepts pointer events over the center, so clicks aimed at inner-ring nodes never land (Playwright reports `<div class="...rotor...">…</div> intercepts pointer events`).

**Why:** A later-in-DOM full-size sibling paints/hit-tests above earlier siblings' children. With one rotor it works (the node is its own rotor's child, on top); with 2–3 stacked rotors the outer one shadows the inner nodes.

**How to apply:** Give the rotor layers `pointer-events: none` and put `pointer-events: auto` back on the node element (so its button + popover stay clickable). Keep this whenever the hero orbit goes multi-ring.
