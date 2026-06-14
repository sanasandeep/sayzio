---
name: Marketing SEO two-kind resolution
description: How public marketing-page SEO meta (title/description/keywords) resolves and where admins edit it.
---

`MarketingSeo` (app/Modules/Common/Support) is the single source of truth for public marketing-page SEO. Two page kinds, two override stores:

- **Code-driven pages** (home, pricing, premium-features, creators, analytics, audience, integrations, api-docs, resume-builder, compare-index + compare-{rival}): no `site_pages` row. Defaults in `codeDrivenDefaults()`; admin override is the AppSetting map under key `marketing_seo` (per-field, blank = use default). Edited on the unified `/admin/marketing-seo` screen.
- **Content pages** (features, about, legal, AI suite, use-cases, etc.): backed by a `site_pages` row — the row IS the override. Keyword defaults (net-new) in `sitePageKeywordDefaults()`. Edited via the existing Site Pages editor; the unified screen only deep-links to it.

**Why:** keeps one resolver (`resolveForView`) for the public layout while respecting that content pages already had row-based admin editing.

**How to apply:** new marketing page → add to `codeDrivenDefaults()` (+ pass `seoKey` from the route/controller) OR add a `site_pages` slug to `sitePageLabels()`/`sitePageKeywordDefaults()`. OG/Twitter reuse the resolved title+description automatically. Note: home (`resources/views/home.blade.php`) and creators (`common/creators-directory.blade.php`) have their OWN `<head>` and call `resolveForView(['seoKey'=>...])` inline — they do NOT use `public/layouts/site.blade.php`.
