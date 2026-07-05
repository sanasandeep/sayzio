---
name: Converting a standalone dark page to public.layouts.site
description: Pattern for migrating a standalone HTML/Blade page (own head, own dark theme) onto the shared marketing layout with SEO/share-meta and light/dark support.
---

`public.layouts.site` computes SEO (`MarketingSeo::resolveForView`) and OG/Twitter
share meta (`marketing-share-meta.blade.php`) from plain `$shareTitle` /
`$shareDescription` / `$shareImage` variables read out of the same render scope —
set them with a top-level `@php` block placed BEFORE `@section('content')` (see
`public/privacy-request.blade.php`); they don't need to be passed to `@extends`
or set inside the section.

**Why:** a standalone page's own `<head>`/fonts/`@vite` block becomes fully
redundant once it extends the shared layout — duplicating them causes double
asset loads and lets stale styling drift from the shared theme.

**How to apply:**
1. Replace `<!DOCTYPE html>...<head>...</head><body>` with `@extends('public.layouts.site')`, move page CSS into `@push('head')` and page-only `<script>` into `@push('scripts')` (these stacks work anywhere in the child view, not just inside `@section`).
2. The layout defaults to the visitor's light/dark preference (`html.light-mode` class), so a page that was hardcoded dark needs an audit: keep existing hardcoded colors as the DARK (unprefixed) base, and add `html.light-mode .your-class {...}` counterparts for every custom class — don't blanket-invert, follow the existing marketing light-mode legibility pattern (see `marketing-light-mode-legibility.md`).
3. If the page includes a Bootstrap-styled shared partial reused by another (light) page, keep restyling scoped to a wrapper class in the consumer only (see `event-page-shared-rich-content-theming.md`) — never edit the shared partial.
4. Verify with app-preview screenshots in the default (usually light, since headless Chromium reports `prefers-color-scheme: light`) mode; dark mode can be spot-checked by fetching the page with `curl -b "1inme_theme=dark"` and confirming the base (unprefixed) CSS values are what was already correct pre-migration.
