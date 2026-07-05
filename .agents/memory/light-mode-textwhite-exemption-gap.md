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
