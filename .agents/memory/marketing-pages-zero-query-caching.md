---
name: Marketing pages zero-query warm path
description: How /pricing, /features, /creators, /demos, /blogs achieve zero DB queries on warm anonymous hits, and the gotchas hit while doing it.
---

# Marketing pages zero-query warm path

All public marketing surfaces (home, /pricing, /features, /creators, /demos,
/blogs) cache their DB reads as **plain attribute arrays** rehydrated via
`Model::hydrate()` on read — never serialized Eloquent models (file cache →
`__PHP_Incomplete_Class` 500s). Production runs `DB_PERSISTENT=false`, so each
query pays a ~3s cross-region SSL reconnect; the warm anonymous path must run
**zero** queries.

**Rules that made it work:**
- Version every cache key (`...:v1`, `...:v2`) — old keys may be poisoned with
  serialized models (the creators "trending" carousel v1 key live-500'd this way).
- Relations survive as sibling arrays: store `['attrs' => ..., 'prices' => [...]]`
  and `setRelation()` after hydrate (pricing catalog pattern).
- Joined columns (e.g. `t.gained`) survive fine inside `getAttributes()`.
- Cache only the **default anonymous** variant of filterable pages (guest, no
  q/tag/tier, default sort, page 1); everything else falls through to live queries.
- Only cache positive lookups where call-sites rely on `firstOrCreate`/`firstOrFail`
  semantics (`Cache::remember` treats `null` as a miss, which gives this for free).
- Admin edits go live via model-event `Cache::forget` where cheap (SitePage per-slug,
  incl. old slug on rename) or the 5-min TTL elsewhere.

**Why:** repeated prod incidents — `__PHP_Incomplete_Class` 500s and multi-second
TTFB — all traced to serialized models in the file cache + per-query SSL reconnects.

**How to apply:** any new marketing/public anonymous surface must use this
pattern; profile with a kernel-handle script that counts `DB::listen` queries —
but note listeners ACCUMULATE across passes in one process (warm-pass counts
double unless you account for it).

**Proactive warming:** the scheduled `home:warm-caches` job (HomePageCache::warm())
also refreshes the /pricing catalogue via `PricingPageCache::warm()` (builder
lives in `PricingPageCache`, shared with the controller's lazy fallback — never
fork it back). Public marketing controllers must resolve the visitor via
`$request->user('web')`, not `user()` (admin-guard session → Admin → TypeError
in `PricingResolver::currencyForUser(?User)`).

**Perf-measurement gotcha:** ad-hoc `php -S` smoke tests have NO opcache, so a
big page (900KB /pricing) shows ~1s TTFB purely from recompiling PHP per
request; add `-d zend_extension=opcache -d opcache.enable=1 -d opcache.enable_cli=1`
before concluding a page is slow (0.06s with opcache).

**Known stale test:** `SitemapIndexTest::test_sitemap_index_lists_both_sitemaps_as_valid_xml`
asserts exactly 2 `<sitemap>` entries but `SitemapController::index()` has since
grown to 5 (creators/resumes/links) — pre-existing failure, not data-dependent.

**Proactive warming:** the `home:warm-caches` command (every 4 min + at boot)
now also runs `MarketingPageCache::warm()` — pricing catalog, all SitePage
slugs (one query), creators default index/trending(0,0)/popular tags, demos,
blogs index — with `HomePageCache::WARM_TTL` puts. Builders live on the
request-path controllers (`build*` methods) so warmer and lazy rebuild can't
drift; sections are fault-isolated (log + summary error, never block serving).
