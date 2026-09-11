<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\MarketingSeo;
use App\Modules\Common\Support\MarketingSitemap;
use App\Modules\Common\Support\PlatformHosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature coverage for the public marketing /sitemap.xml and /robots.txt
 * endpoints (SitemapController). Guards against:
 *  - the sitemap returning valid XML with a <loc> for every code-driven and
 *    site_pages-backed marketing URL (sourced from MarketingSeo so the two
 *    stay in lockstep), including the /compare/* and /for/* pages
 *  - a per-row <lastmod> being emitted (the prior bug called toAtomString on
 *    a raw string and 500'd; this asserts the endpoint renders cleanly)
 *  - robots.txt referencing the sitemap and disallowing app/admin/API paths
 */
class MarketingSitemapRobotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_all_marketing_pages_as_valid_xml(): void
    {
        // A site_pages-backed row so the lastmod branch is exercised.
        SitePage::firstOrCreate(['slug' => 'about'], ['title' => 'About']);

        $res = $this->get('/sitemap.xml');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $body = $res->getContent();

        // Well-formed XML.
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $body);
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($body), 'sitemap should be valid XML');

        // Every marketing path from the single source of truth appears, on the
        // canonical brand domain. These were url() until the sitemap index was
        // found listing its children on the legacy domain: a cached, shared,
        // crawler-facing URL has one correct answer and must not depend on
        // which host (or which warmer run) happened to build it.
        foreach (MarketingSeo::sitemapPaths() as $entry) {
            $loc = PlatformHosts::brandUrl($entry['path']);
            $this->assertStringContainsString('<loc>' . $loc . '</loc>', $body);
        }

        // Representative code-driven, compare, and use-case URLs.
        $res->assertSee('<loc>' . PlatformHosts::brandUrl('/') . '</loc>', false);
        $res->assertSee('<loc>' . PlatformHosts::brandUrl('/compare/linktree') . '</loc>', false);
        $res->assertSee('<loc>' . PlatformHosts::brandUrl('/for/creators') . '</loc>', false);

        // At least one lastmod (from the about row) is emitted.
        $this->assertStringContainsString('<lastmod>', $body);
    }

    public function test_sitemap_response_is_cached(): void
    {
        Cache::forget(MarketingSitemap::CACHE_KEY);

        $this->assertNull(Cache::get(MarketingSitemap::CACHE_KEY));

        $this->get('/sitemap.xml')->assertOk();

        // After a request the rendered XML is warmed into the cache.
        $this->assertNotNull(Cache::get(MarketingSitemap::CACHE_KEY));
    }

    /**
     * This test has been failing for a while, and it was the test that was
     * wrong -- the flush works.
     *
     * It warmed the cache and then called firstOrCreate() on the `about`
     * slug, expecting the model event to clear it. But `about` is seeded, so
     * firstOrCreate FOUND the row and returned it without writing anything.
     * No write, no `saved` event, no flush, and a red test describing a bug
     * that did not exist. A failing test nobody can fix is worse than no
     * test: it trains everyone to read the CI summary as noise.
     *
     * An actual save, on a row that definitely exists, is what this was
     * always meant to assert.
     */
    public function test_saving_a_site_page_flushes_the_sitemap_cache(): void
    {
        $page = SitePage::firstOrCreate(['slug' => 'about'], ['title' => 'About']);

        // Warm the cache AFTER the row exists, so the only write under test
        // is the save below.
        $this->get('/sitemap.xml')->assertOk();
        $this->assertNotNull(
            Cache::get(MarketingSitemap::CACHE_KEY),
            'the sitemap did not cache, so this test cannot observe a flush'
        );

        // A real write. Saving a marketing page row invalidates the cached
        // sitemap via the model event.
        $page->title = 'About (edited at ' . now()->toIso8601String() . ')';
        $page->save();

        $this->assertNull(
            Cache::get(MarketingSitemap::CACHE_KEY),
            'editing a marketing page left the old sitemap cached, so the change '
            . 'would not reach search engines until the TTL expired'
        );
    }

    public function test_indexnow_key_file_serves_the_stored_key(): void
    {
        $key = MarketingSitemap::indexNowKey();
        $this->assertNotNull($key);

        $res = $this->get('/' . $key . '.txt');
        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame($key, trim($res->getContent()));

        // A non-matching (but well-formed) key returns 404.
        $this->get('/' . str_repeat('0', 32) . '.txt')->assertNotFound();
    }

    public function test_robots_references_sitemap_and_blocks_app_paths(): void
    {
        $res = $this->get('/robots.txt');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        // Pinned to the brand domain: a crawler that reaches robots.txt on any
        // host we answer on should be sent to the one canonical sitemap index.
        $res->assertSee('Sitemap: ' . PlatformHosts::brandUrl('/sitemap_index.xml'), false);
        $res->assertSee('Disallow: /user/', false);
        $res->assertSee('Disallow: /admin/', false);
        $res->assertSee('Disallow: /api/', false);
    }
}
