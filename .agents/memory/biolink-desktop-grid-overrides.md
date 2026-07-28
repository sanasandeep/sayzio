---
name: Biolink desktop grid overrides (grid_span_md / grid_row_span_md)
description: How desktop-only block spans work on public biolink pages and every surface that must stay in lockstep.
---

Public biolink blocks can carry desktop-only grid overrides in `_style`:
`grid_span_md` (1–12 columns at ≥768px) and `grid_row_span_md` (1–6 rows,
stretches the block to full row height — used for row-spanning hero panels
beside tile grids). Mobile keeps using plain `grid_span`.

**Lockstep surfaces** (miss one and the value is silently dropped or unstyled):
1. `BiolinkBlock::STYLE_DEFAULTS` — keys must exist (empty default).
2. `BiolinkBlockController::sanitizeBlockStyle` `$numericBounds` — else editor
   saves strip the keys.
3. `biolink.blade.php` — `@media (min-width:768px)` `.md-span`/`.md-row-span`
   CSS + wrap emission that sets `--md-span`/`--md-row-span` vars.
4. `TemplatePreviewLayoutBuilder` prefers `grid_span_md` over `grid_span` so
   gallery thumbnails read as desktop blueprints.

**Why:** the "Split Hero Tiles" starter template needs a hero that sits beside
a 2×3 tile grid on desktop but stacks first on phones; one span field can't
express both.

**Other gotchas hit while building it:**
- The page-level font collector reads legacy `settings['style']` (not
  `_style`), so a layout branch needing its own font (Pacifico for
  `split_hero` in `biolink-profile-card.blade.php`) loads it with a body-level
  `<link>` inside `@once`.
- Row-span stretching needs `align-self:stretch` + `> :first-child{height:100%}`
  on the wrap, and the card itself `h-full`.
