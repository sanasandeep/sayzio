---
name: Site layout meta description fallback
description: How the public marketing layout populates <meta name="description">
---

The public site layout (`resources/views/public/layouts/site.blade.php`) historically
set `<meta name="description">` only from `$page->meta_description`. Marketing pages that
render via `view()` without a `$page` model (e.g. the /compare pages) therefore shipped an
empty description even though they passed `$shareDescription` (which only fed og:/twitter:
tags via `marketing-share-meta.blade.php`).

**Rule:** the plain `<meta name="description">` now falls back to `$shareDescription`
(`$page->meta_description ?? ($shareDescription ?? '')`). When adding a new marketing page,
pass `$shareTitle` + `$shareDescription` to the view and the full meta set (title via
@section('title'), description, og, twitter) populates automatically — no `$page` model needed.

**Why:** keeps SEO description in lockstep with the social share description from one source,
and avoids empty description tags on model-less marketing pages.
