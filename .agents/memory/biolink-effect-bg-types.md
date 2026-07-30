---
name: Biolink effect background types (tiles/mesh/pattern/torn)
description: Lockstep surfaces and mobile fallback contract for the non-CSS-portable background types.
---

# Biolink effect background types

Tiles / Mesh / Pattern (and standalone Torn with tear styles) are `background_type` values whose visuals are pure web CSS — mobile cannot reproduce them.

**Rules:**
- Adding/altering one of these types is a lockstep change across: catalog class (`TilesBgCatalog`/`MeshGradientCatalog`/`PatternCatalog`/`TornStyleCatalog`), controller allowlist + validation rules + `bg_effect_colors` stamp in `BiolinkBlockController`, editor panel in `biolink-background-card.blade.php`, public renderer branch in `common/biolink.blade.php`, and the mobile preview fallback.
- **Mobile contract:** the server stamps representative `bg_effect_colors` into `settings['biolink']` at save time; mobile renders a LinearGradient from those colors with a caption. Never try to resolve catalog CSS on the client.
- Legacy picker groups (`gradients`, `torn` presets) are hidden from `BgPresetCatalog::forApi()`/`pickerPresets()` but keys must keep resolving via `css()` — saved pages render unchanged. `hidden:true` flag rides along in forApi for mobile filtering.
- Torn quick-combos dispatch a `torn-style-combo` window CustomEvent; the tear-style chip group listens with `@torn-style-combo.window`. Color `<input type=color>` always submits, so seed defaults with `($v ?? '') ?: default`.

**Why:** the first implementation review flagged mobile parity as the easy-to-miss surface; without the stamped colors the mobile preview renders blank.
