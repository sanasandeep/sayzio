---
name: Biolink per-block fonts load from _style
description: Public biolink font collector must read settings['_style'] (canonical) not just legacy 'style'
---
Per-block styling on biolink blocks is stored under `settings['_style']`; the public page's Google-Fonts collector historically only read a legacy `settings['style']` key, so any per-block `font_family` (e.g. baked template heading fonts) rendered with the inline `font-family` but the font itself never loaded — silently falling back to sans.

**Why:** the inline style builder and the font-<link> collector are separate code paths in the public biolink Blade; only the collector had the stale key.

**How to apply:** when adding a block or template that sets `_style.font_family`, verify the family appears in the combined `fonts.googleapis.com/css2?family=...` href on the public page, and that the family exists in FontCatalog (unknown families are dropped from the href).
