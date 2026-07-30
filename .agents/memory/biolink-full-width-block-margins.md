---
name: Biolink full-width block margins
description: Public biolink pages have no horizontal container padding; side spacing is a per-block wrap margin, and explicit 0 means edge-to-edge.
---

# Biolink full-width block margins

The public biolink container has NO horizontal padding. Default side spacing (~16px web / 24px mobile) comes from a `.biolink-container > *` margin rule (web) / a per-block wrap `View` (mobile). A block's `margin_left/right` style overrides it on the WRAP element, and `'0'` is a real value (full-width), so all margin emission must test `!== ''`, never truthiness/`!empty`.

**Why:** creators need edge-to-edge blocks; container padding made 0-margin impossible.

**How to apply (lockstep surfaces):**
- `BiolinkBlock::buildInlineStyle($style, $skipHorizontalMargins)` — top-level render passes `true` (side margins live on the wrap, not the inner element; avoid double-apply).
- Web renderer: wrap gets `margin-left/right:Npx` appended when the style value `!== ''`.
- Live preview: `margin_left/right` patch targets the block ROOT wrap (`''` clears back to the default rule), not `styleTarget()`.
- Editor: "Full width" toggle sets/clears both side margins to `'0'`.
- Mobile `[handle].tsx`: `blockWrapMargins(block)` wrapper; `styles.content` has no `paddingHorizontal`; header/pairings use a dedicated `headerPad`.
- Card children and slides mode intentionally get no wrap margins.
- Blocks with `margin_left > 0` now measure from the page edge (accepted behavior change).
