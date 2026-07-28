---
name: Image-block hero decorations (_photo_*)
description: How biolink image-block hero decorations are wired and the parity/versioning pitfalls when adding new photo styles.
---

# Image-block hero decorations

The hero photo styles (concentric arch frame, half-overlapping title banner, torn collage + accents, arch-band profile layout) are carried in `settings['_style']['_photo_*']` keys on image blocks, plus a `_profile_layout=arch_band` token on profile cards.

**Rule:** any new photo/decoration style needs the FULL lockstep set — sanitizer rules, STYLE_DEFAULTS, catalog variant + VERSION bump, editor form fields, web renderer, AND the mobile public renderer (`artifacts/1inme-mobile/app/biolink/[handle].tsx`). Mirroring keys in `lib/blockVariants.ts` alone is NOT enough — code review rejects the task if the mobile renderer doesn't visually consume the keys.

**Why:** the platform's done-criteria for biolink styling is web + mobile visual parity, not just selection-UI parity. A web-only renderer passed all local checks but was rejected at review.

**How to apply:**
- Keys live in `_style` (not `_image_style`) so curated variants can carry them via applyVariant.
- `_photo_frame=concentric_arch` forces its own arch clip; standalone `_photo_mask` only applies when Image Styling's `mask_shape` is none.
- Mobile approximations are acceptable (RN has no clip-path): arch = huge top border radii, torn = page-background zigzag SVG overlays, accents = react-native-svg copies of the web SVG paths.
- Before "bumping" `BlockVariantCatalog::VERSION`, check the UPSTREAM value (`git show main-repl/main:...`) — a parallel task may already have taken your target number, making your bump a no-op.
