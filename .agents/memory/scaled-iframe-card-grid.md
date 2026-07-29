---
name: Scaled live-iframe card grids
description: Pattern + pitfalls for rendering many scaled-down live iframes in a card grid (admin templates "Live previews" toggle)
---

Pattern: render the page at a fixed CSS width (e.g. 420px) in an iframe and scale it into the card with `transform: scale(cardWidth/420); transform-origin: top left`, height = cardHeight/scale. Iframe gets `pointer-events-none` with an overlay button for the full-preview modal; `loading="lazy"` defers offscreen renders.

**Why:** static thumbnails go stale; a live render is the actual template.

**How to apply / pitfalls:**
- Compute scale with a per-card **ResizeObserver**, not `x-init` + `@resize.window`: cards hidden by filters at mount have `clientWidth = 0` (scale 0 → blank) and only a window resize would fix them. ResizeObserver also avoids N window listeners. Guard `if (w > 0)` before writing scale. No explicit disconnect needed when Alpine `x-if` tears the node down (observer is GC'd).
- Hover overlays above the iframe must be `pointer-events-none` with `[&>form]:pointer-events-auto` (or similar) so their buttons still work.
- e2e: lazy iframes navigate late — poll `page.frames()` for the preview URL (`expect(...).toPass()`), a one-shot `page.frame({url})` lookup races and returns null.
