---
name: Mobile bg-effect catalog mirror
description: Mobile preview textures for tiles/mesh/pattern come from a TS mirror of the server PHP catalogs; unknown keys fall back to the stamped-color gradient.
---
Tiles/Mesh/Pattern biolink backgrounds render as real textures on mobile via a display-only TypeScript mirror of the server PHP catalogs (see `bgEffectCatalog` in 1inme-mobile). The server only stamps a flat color list (`bg_effect_colors`); it stays the validation source of truth.

**Why:** without the mirror, mobile can only approximate these backgrounds with a flat gradient.

**How to apply:** any web-side catalog palette/preset addition or edit must be mirrored on mobile, or that key silently degrades to the gradient fallback (intentional degrade path — keep it). E2E gate: `test:bg-effect-preview-e2e` in the mobile package.
