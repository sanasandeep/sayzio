---
name: Primary brand domain canonical vs hard redirect
description: When consolidating SEO signals onto one brand domain (sayzio.app vs 1in.me), marketing pages get a hard 301; user-content pages (biolinks/resumes/creator profiles) only get a soft canonical/og:url rewrite, never a redirect.
---

Sayzio is deliberately reachable on two brand domains: `sayzio.app` (primary)
and `1in.me` (kept as the short-link domain). `PlatformHosts` (in
`app/Modules/Common/Support/PlatformHosts.php`) is the single source of truth
for both:

- `PlatformHosts::canonicalUrl()` — request-based, rewrites the host to the
  primary brand domain ONLY when the current request host is itself a
  recognised brand domain (primary or non-primary). Dev/preview hosts and
  custom user domains pass through unchanged (`request()->fullUrl()`
  equivalent). Use this for canonical `<link>`, `og:url`, and JSON-LD `url`
  tags — it's a *soft* signal, never a redirect.
- A `RedirectToPrimaryBrandDomain` middleware (alias `brand.primary`) does a
  hard 301 from the non-primary brand domain to the primary one. Apply this
  ONLY to routes where there's no legitimate reason to keep serving the
  non-primary host — marketing pages (home, /about, /pricing, /blogs/*, etc.)
  and directory pages (/creators).

**Why:** `1in.me` is the intentional short-link/browsing domain — a biolink,
resume, or creator profile visited via `1in.me/foo` must keep resolving on
`1in.me` (redirecting it to `sayzio.app/foo` would break the whole point of a
short link). But for SEO consolidation, that same page should still declare
`sayzio.app` as its canonical/og:url so search engines and social scrapers
converge signals onto one host. So: user-content pages get `canonicalUrl()`
only (no `brand.primary` middleware); marketing/directory pages get both.

**How to apply:** When adding a new public marketing page or route, check
whether its host should ever legitimately be non-primary. If not, add
`->middleware('brand.primary')` to the route AND use `canonicalUrl()` in its
canonical/og:url tags. If the route is meant to also work as a short-link/
user-content surface, add `canonicalUrl()` to the tags but skip the
middleware. Also watch for **CORS JSON endpoints** sharing a route prefix
with human-facing pages (e.g. a blog's `/feed.json` used cross-origin by a
separate marketing site) — never put those under `brand.primary`, since a 301
strips the CORS preflight-approved headers and breaks the fetch.

Also: the site's actual home route (`/`) is registered in
`routes/modules/user.php` (loaded via `ModuleServiceProvider` under the `web`
middleware group), NOT in `routes/web.php` — easy to miss when sweeping for
routes that need `brand.primary`/canonical fixes.

**Sitemap eligibility must mirror the public renderer's own indexability
gates, not just visibility/active flags.** When building a sitemap of
user-generated public content (resumes, biolinks, creator profiles), it's
not enough to filter on `is_public`/`visibility=public`/`is_active` — you
must also exclude anything the page itself would render as
non-crawlable: password-protected pages (no content visible to an
anonymous crawler) and owner-set `noindex` robots meta (resumes:
`resumes.allow_indexing` boolean; biolinks:
`links.settings->biolink->meta->robots` JSON string containing
`noindex`, plus `links.is_password_protected`). A code reviewer will
treat "sitemap lists a page the app itself marks noindex/locked" as a
functional SEO regression, not a nitpick — grep the public blade view for
its own `noindex`/password gate and replicate the exact condition in the
sitemap query.
