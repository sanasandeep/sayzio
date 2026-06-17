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

## Signed-in dashboard equivalent (User/Admin/Super Admin)

The dashboard does NOT load `marketing-anim.css`. Its shared CSS + light-mode
remap is the inlined partial
`artifacts/1inme/resources/views/common/partials/theme-styles.blade.php`
(theme-aware via CSS vars `--text-primary` etc.; loaded by user/admin layouts and
the auth/login views). Two layers, same idea:

1. **Global utility remap** — a JS-injected `<style id="light-mode-overrides">`
   already remaps text-white(/opacity), gray/slate/zinc/neutral/stone-1..6,
   bg-white/black, bg-slate/zinc-7..9. A **static** `<style>` block (just before
   its closing tag) now also darkens LIGHT ACCENT TEXT shades `*-100..400`
   (violet/purple→#6d28d9, fuchsia→#a21caf, pink→#be185d, rose→#be123c,
   red→#b91c1c, emerald/green→#047857, teal→#0f766e, amber/yellow→#b45309,
   orange→#c2410c, sky→#0369a1, cyan→#0e7490, blue→#1d4ed8, indigo→#4338ca) plus
   text-gray-100/200→var(--text-muted). All under `html.light-mode` w/ `!important`
   (beats Tailwind CDN). It's inlined per-request — NO `?v=N` cache bump needed.
   **Why safe to darken accent text:** audited the dashboard — light accent text
   sits only on SOFT same-hue tints (`bg-emerald-500/10`) that stay pale on white;
   ZERO pairings with solid/gradient saturated bg, so no white-on-color labels
   are harmed. Dark mode untouched.

2. **Per-page scoped `<style>` counterparts** — most already exist
   (`social-proofs/edit .bz-label`, `links/show .stat-tile-*`). Inline per-variant
   hex colors (e.g. `performance-coach.blade.php` `.pc-trend`) are best fixed by
   swapping the inline `color:` for a Tailwind accent class so the global remap
   handles both modes. White-on-purple/gradient badges (followers `.pill-active`,
   dashboard `.dash-tab-active`, integrations `.tab-active`) are correct as-is.
