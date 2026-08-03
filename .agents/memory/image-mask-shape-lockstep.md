---
name: Image block mask shapes — web/mobile lockstep
description: Adding a mask/crop shape to the image block requires 5 lockstep surfaces; mobile has its own polygon mirror or new shapes render as plain rectangles.
---

# Image block mask shapes

Adding a new `_image_style.mask_shape` value needs lockstep edits or it silently degrades:
1. `BiolinkBlock::MASK_CLIP_PATHS` + `buildImageInlineStyle()` (polygon/ellipse approximations only — never `path()`; radius-only shapes like pill/rounded/circle are special-cased in the builder, not the const).
2. Controller sanitizer `mask_shape` enum allowlist (otherwise the value is stripped on save).
3. Editor `$maskShapes` dropdown in `image-style-settings` partial.
4. `BlockVariantCatalog` `mask_*` image one-offs (chrome-only — the variant does NOT set the mask itself) + VERSION bump, mirrored in mobile `lib/blockVariants.ts`.
5. Mobile renderer `MASK_POLYGONS` map in `app/biolink/[handle].tsx` — a TS mirror of the PHP percent-space polygons rendered via react-native-svg ClipPath. **Web-only additions fall back to a plain rectangle on mobile.** Oval = SVG Ellipse; circle/rounded/square/pill = border-radius.

**Why:** the mask value flows editor → sanitizer → inline CSS → mobile SVG clip; each hop has its own allowlist/mirror and fails silently.

Other gotchas: image block link = `settings[_link][url]` (whole image wrapped in the tracked `redirect.block` anchor on web; mobile wraps in a Pressable → handleTap). `biolink_blocks` ordering column is `sort_order`, not `position` (and `position` is a PG reserved word).
