---
name: Font Awesome loader guard
description: Validation gate protecting the Safari-safe non-blocking FA loader partial and banning raw FA links in public views.
---
Rule: public-facing blades must load Font Awesome via `@include('common.partials.fontawesome')`, never a raw `<link>`. The partial must keep three pieces in lockstep: the media="print" swap link tagged `data-fa-async`, the inline safety-net script (flips pending print links on DOMContentLoaded AND window load — Safari doesn't fire link onload for cached media=print stylesheets), and the `<noscript>` fallback.

**Why:** Safari rendered every fa-* glyph blank when the print-swap link's onload never fired (cached stylesheet); only reproducible with cache, so manual QA misses it.

**How to apply:** guard = `check:fontawesome-loader` in scripts (validation step `fontawesome-loader`). Scope is public roots only (common/, public/, errors/, partials/, components/, home/, delivery-projects/ + top-level standalone pages); admin/, user/, portal/ intentionally use plain blocking FA links and must stay OUT of scope. Meta-test poisons via a temp public blade + a stripped partial (restored in finally).
