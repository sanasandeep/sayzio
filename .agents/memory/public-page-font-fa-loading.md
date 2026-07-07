---
name: Public page font & Font Awesome loading strategy
description: How Google Fonts and Font Awesome are loaded on 1inme's public pages to avoid render-blocking requests.
---

Biolink pages collect every distinct font family used across the page + all
blocks, then request them via `FontCatalog::googleHrefCombined($families)` —
ONE css2 URL with repeated `family=` params — instead of one `<link>` per
family. Preconnect hints for `fonts.googleapis.com`/`fonts.gstatic.com` sit
right before it. `FontCatalog::googleHref()` (single-family) still exists for
other callers; don't reintroduce a per-family loop on public pages.

**Why:** each extra selected font used to add another render-blocking CSS
fetch in the head, directly hurting First Paint/LCP on indexable biolink
pages (the pages most likely to be a search-entry point).

Font Awesome on public pages loads via `common/partials/fontawesome.blade.php`
(the `media="print" onload="this.media='all'"` non-blocking swap + preload +
noscript fallback), included via `@include('common.partials.fontawesome')`.
**Safari gotcha:** Safari does not reliably fire link `onload` on
`media="print"` stylesheets (especially when served from cache), leaving FA
print-only forever — every `fa-*` glyph blank. The partial therefore also
carries an inline loadCSS safety net that force-flips any pending
`link[data-fa-async][media="print"]` to `media="all"` on DOMContentLoaded and
again on window load. Never rely on `onload` alone for a print-swap link.
This covers the ~20 public-facing views (home, public/*, common/biolink,
common/calendar-page, common/creators-directory, common/event-ticket,
common/form, common/gated, common/resume-public, common/rsvp-form,
common/rsvp-manage, common/splash). Authenticated/back-office views
(admin/*, user/*, portal/*) intentionally still use a plain blocking
`<link rel="stylesheet">` — they were out of scope and aren't indexed public
pages. If a new public-facing Blade view needs Font Awesome, use the shared
partial, not a raw `<link>`, to avoid reopening the render-blocking issue.

**How to apply:** when adding a new public/indexable page that needs icons
or a webfont, reuse these two patterns rather than inlining a fresh
`<link rel="stylesheet">` per font/icon-bundle.

**2026-07 update (Safari still blank despite safety net):** the DOMContentLoaded
flip alone was not enough — Safari has been seen IGNORING the media flip on an
already-parsed cached print link, and on long pages DOMContentLoaded is late.
The partial now also: (1) preloads fa-solid-900.woff2 + fa-brands-400.woff2
(`as="font" crossorigin` — FA css is font-display:block, so glyphs are blank
boxes until the woff2 arrives, and the font URLs are only discovered post-flip);
(2) runs timed activateFa fallbacks (400ms/1500ms); (3) reinsertIfDead at 3s:
if document.fonts.check shows no FA font, remove + re-insert the link fresh
with media="all" (guarded by data-fa-reinserted, one-shot). Guard
check-fontawesome-loader.ts still pins the base shape; additions are additive.

**2026-07 final resolution:** even WITH timed retries + reinsert recovery,
real Safari still blanked icons. The media=print swap was removed for good —
the partial now emits a PLAIN blocking `<link rel="stylesheet" data-fa-stylesheet>`
plus the two woff2 preloads. The guard now pins the new shape and FORBIDS any
return of `media="print"` / `data-fa-async` in the partial. Lesson: don't keep
patching the loadCSS print-swap for Safari; a plain blocking link for a small
same-origin cached stylesheet is the reliable answer.
