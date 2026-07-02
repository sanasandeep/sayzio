---
name: Home marketing phone-mockup block guard
description: The home #features biolink phone mockup uses a hand-authored .bb-* CSS set separate from the real renderer; a static guard catches blank/misspelled preview classes.
---

# Home marketing phone-mockup block guard

The home page `#features` section (`artifacts/1inme/resources/views/home.blade.php`)
has a "build your biolink" demo: a `.build-list` of rows tagged
`data-bl-style="X"` on the left, and a `.bb-phone` mockup on the right that
renders each block type as a small inline-CSS div using hand-authored `.bb-*`
classes (`.bb-hero`, `.bb-video`, `.bb-audio`, `.bb-cal`, ...).

**Key fact:** these `.bb-*` classes are a marketing-only CSS set, completely
separate from the real biolink renderer (`common/partials/biolink-block-render`)
and from `templates:check-designs` (which validates the real editor, NOT this
mockup). A preview div that references a missing/misspelled `.bb-*` class renders
as a zero-height blank gap with nothing to catch it.

**Guard:** `scripts/src/check-home-preview-blocks.ts` (gate `home-preview-blocks`,
npm `check:home-preview-blocks`). Fast, static, parses the blade file, no server.
- `BLOCK_PREVIEW_MAP` maps each `data-bl-style` type → its `.bb-*` preview class
  (or `null` for the "file/PDF" row, which intentionally has no phone tile).
- Fails if: a build-list type is missing from the map; a mapped `.bb-*` class is
  not rendered or has no CSS rule; or ANY `.bb-*` class used in the preview lacks
  a matching CSS rule.

**How to apply:** adding a build-list row means adding both the `.bb-*` preview
div + its CSS rule AND a `BLOCK_PREVIEW_MAP` entry, or the guard fails.
