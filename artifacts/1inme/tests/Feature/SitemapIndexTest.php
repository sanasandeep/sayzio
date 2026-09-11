<?php

namespace Tests\Feature;

use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\PlatformHosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature coverage for the sitemap index at /sitemap_index.xml — the single
 * entry point search engines crawl, which ties together the marketing
 * (/sitemap.xml), blog (/blogs/sitemap.xml), creators, resumes, and links
 * sitemaps.
 *
 * Guards against a future refactor silently breaking discovery by:
 *  - dropping any sitemap entry from the index
 *  - stopping robots.txt from pointing at the index
 *  - breaking the individual sitemaps the index references
 *
 * URLs are asserted with PlatformHosts::brandUrl() rather than url(). The
 * index is cached and shared, and url() answers with whatever host built it
 * -- or APP_URL when nothing did, which is how the live index came to list
 * all five of its children on the legacy 1in.me domain. There is one correct
 * answer here and it does not depend on who asked.
 */
class SitemapIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_index_lists_all_sitemaps_as_valid_xml(): void
    {
        // Seed both sources so the lastmod branches are exercised.
        SitePage::firstOrCreate(['slug' => 'about'], ['title' => 'About']);

        $res = $this->get('/sitemap_index.xml');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $body = $res->getContent();

        // Well-formed XML with the sitemapindex root element.
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $body);
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($body), 'sitemap index should be valid XML');
        $this->assertSame('sitemapindex', $doc->documentElement->localName);

        // Every canonical child sitemap is referenced: marketing, blogs,
        // creators, resumes, links.
        $expected = [
            PlatformHosts::brandUrl('/sitemap.xml'),
            PlatformHosts::brandUrl('/blogs/sitemap.xml'),
            PlatformHosts::brandUrl('/sitemap-creators.xml'),
            PlatformHosts::brandUrl('/sitemap-resumes.xml'),
            PlatformHosts::brandUrl('/sitemap-links.xml'),
        ];
        foreach ($expected as $loc) {
            $this->assertStringContainsString('<loc>' . $loc . '</loc>', $body);
        }

        // Exactly these entries — no silent additions/drops.
        $this->assertCount(count($expected), $doc->getElementsByTagName('sitemap'));
    }

    public function test_robots_advertises_the_sitemap_index(): void
    {
        $res = $this->get('/robots.txt');

        $res->assertOk();
        $res->assertSee('Sitemap: ' . PlatformHosts::brandUrl('/sitemap_index.xml'), false);
    }

    public function test_referenced_marketing_sitemap_still_returns_valid_xml(): void
    {
        $res = $this->get('/sitemap.xml');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($res->getContent()), 'marketing sitemap should be valid XML');
        $this->assertSame('urlset', $doc->documentElement->localName);
    }

    public function test_referenced_blog_sitemap_still_returns_valid_xml(): void
    {
        BlogPost::query()->delete();

        $res = $this->get('/blogs/sitemap.xml');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($res->getContent()), 'blog sitemap should be valid XML');
        $this->assertSame('urlset', $doc->documentElement->localName);
    }
}
