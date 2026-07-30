---
name: Unified block background picker
description: Block/card background modes (None/Color/Gradient/Preset/Image) — schema, sanitizer, and e2e locator gotchas.
---

# Unified block background picker (link-family blocks + card containers)

- No new `_style` keys: `bg_color` holds either a color OR a full `linear|radial|conic-gradient(...)` string (sanitizer cap 500 chars); `bg_preset_key`(+`bg_preset_opacity`); `bg_image` accepts http(s) URLs OR root-relative vault paths `/f/...` (charset-locked so it can't break out of `url('…')`).
- Card containers are different: their backgrounds live in plain settings (`bg_type`/`bg_color`/`bg_gradient`/`bg_image`) and are sanitized in `sanitizeSettings` under `$type === 'card'`, not in `sanitizeBlockStyle`.
- **Why:** the render partials interpolate these values straight into inline style attributes, so both sanitizers must forbid `;{}<>"'\`` and validate `/f/` paths.
- **How to apply:** any new background surface must accept both gradient strings and `/f/` paths in lockstep across web sanitizer, `BiolinkBlock::buildInlineStyle`, and the mobile renderer (which regex-detects gradients and approximates them with LinearGradient; `/f/` paths get `getBaseUrl()` prefixed).
- E2e gotcha: the preset swatch browser now sits behind the "Preset" mode chip (selecting it auto-opens the browser); old specs that clicked the Browse toggle directly resolve a hidden button and time out. Click the chip via `getByRole('button', { name: 'Preset', exact: true })`.
- Valid block types for tests: `link`, `link_big`, `cta_button`, `card` — `button` is NOT a valid `type` at store() (422 "selected type is invalid").
