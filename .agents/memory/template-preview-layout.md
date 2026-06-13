---
name: Template preview blueprint layout
description: Constraints for the shared TemplatePreviewLayoutBuilder and the biolink editor's Templates overlay sizing/scrolling.
---

# Template preview blueprint (`TemplatePreviewLayoutBuilder`)

The builder turns a template's blocks into a tiny row/cell blueprint shown as a
gallery thumbnail. It is consumed by **three** surfaces that all read the same
cell shape hints:

1. Web **card** gallery — biolink editor "Templates & more" overlay (Alpine).
2. Web **page** templates picker (`templates/picker.blade.php`).
3. **Mobile** card gallery — served by `Api/Controllers/CardTemplateController`, rendered in RN.

## Row cap is per-surface, not global
`build($items, $maxRows = 6)` takes a max-rows arg. **Only the web card gallery
passes a higher cap (10)** so taller cards read as taller (variable-height
previews). The page picker and mobile render previews into **fixed-height
strips**, so extra rows would just clip — keep them at the default 6.

**Why:** a user wanted variable-height card-gallery previews; bumping the cap
globally clips/compresses the other two surfaces with no benefit.
**How to apply:** if you need taller previews on one surface, pass a bigger
`$maxRows` only from that caller; don't change the default.

## Variable-height previews live in the card markup, not the builder
The gallery card preview must NOT wrap the blueprint in a fixed `aspect-*` box —
that forces uniform heights. Let the blueprint flow naturally (min/max-height
clamp). Thumbnail-image and empty-state cases still get a fixed aspect box.

## Templates overlay sizing/scroll (biolink editor)
The "Templates & more" panel is `position:absolute; inset:0` over `.palette-panel`,
so its height (and thus internal scroll room) is borrowed from `.palette-panel`.
Two pitfalls:
- `.palette-panel` height collapses when the block-palette category sections are
  collapsed → overlay becomes too short to scroll. Fix: give `.palette-panel` a
  desktop `min-height` (e.g. `min(580px, calc(100vh - 24px))`).
- On stacked layouts (<1024px) `.palette-panel` must be `position:relative`, NOT
  `static`, or the absolute overlay escapes its containing block.

## Admin already manages templates
Admin template CRUD exists at `/admin/templates` (kind=card|page): create/edit/
toggle, snapshot capture from a live link, **custom thumbnail upload (overrides
the auto-blueprint)**, and raw snapshot-JSON editing. Don't rebuild this.
