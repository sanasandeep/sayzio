---
name: Profile-card identity designs
description: How the 10 ready-made profile_card identity layouts are wired (Task #1740)
---

# Profile-card identity designs (profile_card_v1..v4)

Ten one-click looks live in the `profile_identity` bundle in
`BlockVariantCatalog`, attached to all four profile_card slots. Each design
differs in LAYOUT, carried by a `_profile_layout` style token (classic_creator,
glass, cover_hero, split, floating, gradient, founder, minimal_dark, magazine,
social_profile).

**Key architecture decisions:**
- The renderer (`common/biolink-profile-card.blade.php`) dispatches on
  `$s['_style']['_profile_layout']`; empty falls back to the block-type's
  historical layout (v2=cover_hero, v3=stats, v4=badges, else classic_creator).
  Legacy `stats`/`badges` layouts are preserved in the same partial.
- profile_card types are in `$skipWrap` so the generic .block-styled wrapper
  does NOT double-wrap; the partial applies `$blockInline` to its own card root
  (+overflow-hidden to clip cover images). Card padding is `0` everywhere (in
  BlockDefaults + every variant) because the partial owns all internal spacing.
- Accent colour is intrinsic per-layout (match() in the partial), NOT a stored
  token — gold for founder, blue for social_profile, etc.
- `_profile_layout` is an opaque slug token: must be in STYLE_DEFAULTS AND the
  sanitizer slug group (with _animation/_gallery_layout/_social_set) or
  apply-variant/migrateStaleVariant strips it.
- Web only — no mobile parity. Follow-up: mirror in `lib/blockVariants.ts` +
  mobile renderer if mobile profile cards ever need these designs.

**Why:** matches the established cover_profile bundle pattern but adds structural
layout switching (cover_profile only re-colours). Verified by rendering all 12
layouts via tinker (DB-free) since isolated envs have no biolink seed data.
