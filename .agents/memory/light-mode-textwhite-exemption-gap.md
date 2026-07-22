---
name: bg-*.text-white light-mode exemption misses inline/bespoke backgrounds
description: How to spot invisible text-white elements in light mode that the sitewide remap allowlist doesn't cover.
---

The sitewide light-mode CSS remap (`marketing-anim.css`) darkens bare
`.text-white` globally, then re-whitens it only for specific
`bg-<color>.text-white` Tailwind class combos (an explicit allowlist).

Any element whose colored/dark background comes from an inline
`style="background:..."` or a bespoke custom CSS class (not a `bg-*`
Tailwind utility) falls through that allowlist and gets its text force-
darkened even though the background itself is untouched — producing
dark-on-dark (invisible) or dark-on-brand-color (low contrast) text.

**How to apply:** when hunting "invisible text in light mode" bugs,
`grep -n 'text-white"'` (bare, not `text-white/NN`) across the view, then
check each hit's background source. If it's inline `style=` or a custom
class rather than a `bg-*` utility class, it needs its own
`html.light-mode <scope> <selector> { color:#fff !important; }` override —
don't assume the sitewide allowlist already covers it.

**Third gap — the `bg-primary-` exemption vs translucent tints:** the
theme-styles remap exempts `[class*="bg-primary-"]` so solid primary
buttons keep white text — but that substring also matches low-opacity
tints (`bg-primary-500/10` chips/badges) whose background is nearly white
in light mode, leaving invisible white text. Fixed with higher-specificity
overrides re-darkening `[class*="text-white"][class*="bg-primary-XXX/1|2|3"]`
(near-solid fills like `/90` deliberately excluded). Any new exemption on a
substring selector must consider opacity variants of the same utility.

**Second gap — opacity-modified accent shades:** the theme-styles remap of
light accent shades (`.text-emerald-300`, `.text-amber-300`, …) uses EXACT
class selectors, so an opacity-modified variant like `text-emerald-300/70`
is a *different* class and escapes the remap entirely — it keeps its pale
dark-tuned color on the white light surface. When auditing a page, grep for
`text-<hue>-[1-4]00/NN` specifically; fix per-element with a marker class +
`html.light-mode .marker { color: <saturated dark hue> }` override in the
page's `@push('styles')` (admin master-password convention), then add the
page to TARGETS in `scripts/src/check-light-mode-pairing.ts`.
