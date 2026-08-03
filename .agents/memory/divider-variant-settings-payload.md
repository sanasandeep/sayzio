---
name: Divider block & variant settings payloads
description: How divider/spacer richer settings work and how block variants can now apply content settings, not just _style.
---

# Divider variants carry a `settings` payload

**Rule:** Block variant catalog entries may include a `'settings' => [...]` array. `applyVariant`/`applyVariantToAll` merge it into the block's content settings **through `sanitizeSettings($type, ...)`** after stamping `_style`. Divider looks live entirely in content settings (style/thickness/width/align/ornament_*), so this is how "picking a variant applies the matching settings" works — and mobile parity comes free because mobile renders from the same content settings (no mobile catalog mirror needed for these).

**Why:** `applyVariant` otherwise only replaces `_style`, and `_style` keys must exist in `BiolinkBlock::STYLE_DEFAULTS` + BlockStyleSanitizer, which is the wrong home for per-type content knobs.

**How to apply:** New "content-driven" variant families (divider-like blocks) should use the `settings` payload; keep `style` minimal (`display_mode: content, padding: 0`). Dividers are excluded from `commonVariants()` in `forType()` and get a designs-only Block Styling section (`$designsOnly` in block-style-settings.blade.php) while staying in the no-_style-panel behavior.

Also: divider/spacer now have a `sanitizeSettings` branch (style allowlist incl. gradient/dots/zigzag/wave/double, thickness 1–12, width 10–100, align, ornament icon/text/color/size, spacer height 4–200); defaults are dropped so untouched legacy payloads stay minimal and render byte-identical.

# Variant gallery window-chrome sketch was structurally unbalanced

The `@if($isWindow)` wrapper that opens the mini title bar + inner div in the static thumbnail sketch had NO matching `@endif` (closed by juxtaposed later blocks only by accident until it wasn't). BladeViewsCompileTest catches this; the fix is an explicit `@endif` right after the inner `<div class="flex-1 ...">` opens. When "unexpected endforeach expecting endif" appears in that file, suspect this window-chrome pattern first.
