---
name: Marketing site sticky-nav offset already handled
description: public.layouts.site's .mkt-site-main mechanism already handles the floating sticky navbar offset; don't add a per-page negative margin hack.
---

`public/css/marketing-anim.css` defines `.mkt-site-main { margin-top: calc(-1 * var(--mkt-nav-h)); }` plus `.mkt-site-main > :first-child { padding-top: calc(var(--mkt-nav-h) + Npx); }`. This pulls the main content area up behind the floating glass navbar (so a hero's background can reach the very top edge) and then pads the first child back down so its content clears the bar.

**Why:** Any page that `@extends('public.layouts.site')` gets this offset automatically applied to whatever it renders first inside `@section('content')`. Adding a second, page-local negative `margin-top` (e.g. to compensate for an announcement banner) on that same first element double-offsets it and can either overlap the navbar or leave an unwanted gap.

**How to apply:** When converting a standalone page into `public.layouts.site`, don't carry over old top-offset hacks tied to variables like `--inme-anno-h`. Trust the layout's existing `.mkt-site-main` mechanism; only add page-specific spacing if visual QA shows a genuine gap/overlap after the conversion.
