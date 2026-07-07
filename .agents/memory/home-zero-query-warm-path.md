---
name: Home page warm path must be zero-DB-query
description: Why the anonymous home render must issue no DB queries in production, and the cache-arrays-then-rehydrate pattern that keeps it that way.
---

# Home page warm path must be zero-DB-query

**Rule:** the anonymous `/` render must issue ZERO DB queries on the warm path.
Every payload it needs (plan teaser, link types, featured blog posts, domain
branding) is cached for 5 min as PLAIN ARRAYS and rebuilt only on miss.

**Why:** production runs with `DB_PERSISTENT=false` against the cross-region
RDS, so even ONE stray per-request query drags a fresh ~3s SSL connect into
every render — a single uncached `blog_posts` select was the whole difference
between ~3.5s and ~0.1s warm TTFB. "It's just one small query" is never small
here.

**How to apply:** anything Eloquent that the home view needs must be cached as
attribute arrays (`getAttributes()` for the model + each needed relation) and
rehydrated on read via `Model::hydrate([...])` + `setRelation(...)` — never
serialized models (file cache → `__PHP_Incomplete_Class`). Same pattern as
`DomainBranding::currentGlobalDomain()`. Invalidate on write (e.g.
`BlogPost::flushPublicCaches()` forgets `HomeController::FEATURED_CACHE_KEY`).
Guard test: `tests/Feature/HomeFeaturedBlogPostsTest` (cache-hit test deletes
the rows then re-renders to prove no live query). To re-check the invariant,
profile with a kernel-handle script + `DB::listen` and confirm `queries=0`.

**Related gotcha (fixed):** on the public home route resolve the visitor via
`$request->user('web')`, not `$request->user()` — an active admin-guard
session makes the default guard return an `Admin`, which the `?User`-typed
`PricingResolver::currencyForUser()` rejects with a TypeError (500).
