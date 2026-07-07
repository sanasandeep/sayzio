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
