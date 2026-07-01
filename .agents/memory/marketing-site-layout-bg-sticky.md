---
name: Marketing site layout background + sticky header
description: Why public.layouts.site must share home's deep --bg/aurora, and how its sticky header works.
---

# Marketing site layout (`public.layouts.site`) background + sticky header

The non-home marketing pages render through `public/layouts/site.blade.php` and
the SHARED header partial `public/partials/header.blade.php`. Two coupled facts:

## Background must mirror the home page, not a flat slate
The shared header's mega-menu panels (`bg-[#1e2330]`) and the section floating
cards (`bg-[#11101c]`) plus the footer (`bg-[#08020f]`) are all designed against
the home page's DEEP base `--bg:#0a0a14` + aurora glow. A flat mid-slate body
(the old `#1e2330`) makes the #11101c cards look like darker holes and leaves a
visible seam at the #08020f footer, and mismatches home.

**Rule:** the site layout carries the SAME `--bg` palette (`#0a0a14` dark /
`#f8fafc` light) and the SAME `.aurora` element as `home.blade.php`. If you touch
home's background, keep site in sync (and vice versa). Light mode aurora opacity
is reduced (≈0.18). Aurora colors (#3d6bff/#5c83ff/#6e61ff/#2342c7) are fine —
only `7c3aed/8b5cf6/a78bfa` are the retired-purple brand-color blocklist.

## In-flow sticky header needs `display:contents` on the Alpine wrapper
The header's `sticky top-0` <nav> lives inside an Alpine `x-data` <div>. That
wrapper is a layout box exactly as tall as the nav, which traps the sticky nav so
it unsticks instantly. The header passes `$fixed` (home = true → position:fixed,
out of flow; site = default false → sticky). For the sticky case ONLY, the
wrapper gets `display:contents`, dissolving its box so the nav's containing block
becomes <body> and it pins across the whole scroll. Keep the `display:contents`
gated on `!$fixed` so home's fixed header keeps a normal box.

## Verification gotcha
The cookie-consent banner LOCKS page scroll until dismissed — a Playwright check
that scrolls before clicking "Accept all" sees `scrollY` stuck at 0 and gives a
false sticky result. Dismiss consent first, then scroll, then assert nav rect.
