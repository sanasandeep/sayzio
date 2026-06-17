---
name: Marketing light-mode legibility
description: Where light-mode text-color remapping lives for the 1inme Laravel marketing pages, and the two-layer fix pattern.
---

# Marketing light-mode legibility (1inme Laravel)

Light mode on the public marketing pages is driven by `html.light-mode` (toggled
via the theme button). Legibility is handled in two layers:

1. **Global utility remapping** — `artifacts/1inme/public/css/marketing-anim.css`
   under the `LIGHT MODE OVERRIDES` block. It re-colors Tailwind text utilities
   (`text-white`, `text-gray-100..600`, `text-slate-100..500`, `text-white/*`,
   muted accent `*-300` shades) and translucent surfaces/borders so they stay
   readable on the white (`#f8fafc`) background. Slate mirrors the gray scale;
   only the lighter steps (100–500) are remapped — `slate-600+` are already dark
   and live mostly inside always-white cards (e.g. resume paper). Bump the
   `?v=N` cache query on the `<link>` in `public/layouts/site.blade.php` after
   editing this file.

**Why:** `.light-mode` only overrode base body color; hardcoded Tailwind gray/
slate utilities stayed light-gray and went low-contrast on white.

2. **Per-page scoped `<style>` counterparts** — custom CSS classes (NOT Tailwind
   utilities) that hardcode dark-tuned light colors need their own
   `html.light-mode .your-class { color: ... }` rules in the page's `<style>`
   block. The global remap cannot reach them. Examples already done:
   `pricing/plans.blade.php` (`.feat-*`), `faqs.blade.php` (`.faq-cat-link`,
   `.faq-chip`), `resume-builder.blade.php` (`.rbp-stat .lbl`).

**How to apply:** when adding/auditing light-mode legibility, fix Tailwind
utilities once in marketing-anim.css; grep each marketing page's scoped `<style>`
for hardcoded light hex text colors and add `html.light-mode` counterparts.
Standalone pages that don't load `site.blade.php` (e.g. `age-gate.blade.php`,
which is always-dark `bg-slate-950` on Tailwind CDN) are out of scope.
