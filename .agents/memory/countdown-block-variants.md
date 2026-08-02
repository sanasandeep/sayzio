---
name: Countdown block variants
description: Gotchas from expanding the countdown biolink block to 10 style variants + rich configs
---

- **typeOneOffs key shadowing:** BlockVariantCatalog dedupes by first occurrence, so a per-type variant key that matches a COMMON catalog key (e.g. `gradient_pop`, `soft_pastel`) is silently shadowed. Use type-suffixed keys (`gradient_pop_cd`, `soft_pastel_cd`).
  **Why:** two countdown variants initially never applied; no error surfaced.
  **How to apply:** whenever adding typeOneOffs, grep the COMMON variant keys first.
- **Never append hex-alpha suffixes ('b3'/'99') to sanitized color values** — sanitizers accept rgba(...) and named colors, and hex math produces invalid CSS (invisible text). Use CSS `opacity` on the element instead. Same trap: gradient strings in `bg_color` are invalid as `color:` values — guard with a solid-color check before reusing bg as text color.
- **Mobile parity:** an opaque block card in [handle].tsx covers the BlockView wrapper's gradient layer — variant bg_color (solid/gradient/translucent) must be painted INSIDE the block component (reuse the wrapper's gradient-stop regex + expo-linear-gradient); translucent "glass" bgs need a solid contrasting base composited underneath.
- CTA button colors are explicit keys `_countdown_cta_bg`/`_countdown_cta_text` (fallback: solid digit color + luminance-picked ink) — never derive button text color from box/bg values that may be white or gradients.
- Countdown-specific style keys: `_countdown_digit_color`, `_countdown_label_color`, `_countdown_box_bg` (STYLE_DEFAULTS + BlockStyleSanitizer $colorKeys + web renderer + mobile [handle].tsx in lockstep). Regression coverage: CountdownBlockSettingsTest renders every variant and asserts valid CSS.
