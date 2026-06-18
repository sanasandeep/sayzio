---
name: Profile-card identity designs (_profile_layout)
description: How to add a layout-driven profile-card design to the biolink editor, and the key-collision gotcha.
---

Profile-card "identity designs" are layout-driven, not just recolours: each catalog
entry in the `profile_identity` bundle (BlockVariantCatalog) carries a
`_profile_layout` token in its `style`. The public renderer
`resources/views/common/biolink-profile-card.blade.php` dispatches on that token
(`$s['_style']['_profile_layout']`) to a per-layout `@elseif` branch, and derives a
per-layout `$accent` from a `match($layout)` near the top. Falls back to a
per-block-type default layout when no design applied. Web-only (no mobile mirror).

To add one: (1) append an entry to the `profile_identity` bundle with `padding=>'0'`
and a `_profile_layout` token + `preview`; (2) bump `BlockVariantCatalog::VERSION`
so the re-apply migration picks it up; (3) add an `@elseif($layout === 'token')`
branch + an `$accent` case in the blade. `_profile_layout` is already whitelisted in
`BiolinkBlockController::sanitizeBlockStyle` and `BiolinkBlock::STYLE_DEFAULTS` — no
enumeration to extend there.

**Why the key matters:** `forType()` concatenates `commonVariants()` + bundle
entries then dedups by `key`, keeping the FIRST occurrence. `commonVariants` already
defines keys `ticket_stub`, `polaroid`, `terminal`. If a new profile entry reuses one
of those as its `key`, it gets silently dropped from the gallery. Prefix identity
entries `identity_*` (e.g. `identity_ticket`) and keep the `_profile_layout` token
separate from the `key`.

**How to apply:** any new profile-card design follows the Task #1740 pattern exactly;
verify by rendering `common.biolink-profile-card` via tinker with full + name-only
sample data (gallery thumbnails are live-rendered, so a render error = blank card).
Caveat / JetBrains Mono fonts are already loaded site-wide.
