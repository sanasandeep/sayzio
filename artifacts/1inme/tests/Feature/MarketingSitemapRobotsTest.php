<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\MarketingSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        // Every marketing path from the single source of truth appears.
        foreach (MarketingSeo::sitemapPaths() as $entry) {
            $loc = url($entry['path']);
            $this->assertStringContainsString('<loc>' . $loc . '</loc>', $body);
        }

        // Representative code-driven, compare, and use-case URLs.
        $res->assertSee('<loc>' . url('/') . '</loc>', false);
        $res->assertSee('<loc>' . url('/compare/linktree') . '</loc>', false);
        $res->assertSee('<loc>' . url('/for/creators') . '</loc>', false);

        // At least one lastmod (from the about row) is emitted.
        $this->assertStringContainsString('<lastmod>', $body);
    }

    public function test_robots_references_sitemap_and_blocks_app_paths(): void
    {
        $res = $this->get('/robots.txt');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $res->assertSee('Sitemap: ' . url('/sitemap.xml'), false);
        $res->assertSee('Disallow: /user/', false);
        $res->assertSee('Disallow: /admin/', false);
        $res->assertSee('Disallow: /api/', false);
    }
}
