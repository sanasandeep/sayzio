<?php

namespace Tests\Feature;

use App\Modules\Common\Controllers\SitemapController;
use App\Modules\Common\Support\PlatformHosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The sitemap index points at sitemaps on the canonical domain.
 *
 * Measured on the live site 2026-09-11: sayzio.app/sitemap_index.xml listed
 * all five of its child sitemaps on 1in.me. Google only follows a sitemap
 * reference across hosts when both are verified in Search Console, so the
 * whole index risked being ignored -- 51 marketing URLs plus every creator
 * profile, resume and public link.
 *
 * The cause was `url()` in a cached value. url() falls back to APP_URL when
 * there is no request to read a host from (the scheduled warmer), production
 * APP_URL still points at the legacy domain, and the result then went into a
 * cache shared by every visitor. A host-dependent value and a shared cache
 * should never have met, and that is the shape this suite guards -- not just
 * today's wrong hostname.
 */
class SitemapIndexPointsAtTheCanonicalDomainTest extends TestCase
{
    use RefreshDatabase;

    private function indexXml(): string
    {
        Cache::forget(SitemapController::INDEX_CACHE_KEY);

        return $this->get('/sitemap_index.xml')->assertOk()->getContent();
    }

    /** Every <loc> in the index, in order. */
    private function locs(string $xml): array
    {
        preg_match_all('/<loc>([^<]+)<\/loc>/', $xml, $m);

        return $m[1];
    }

    public function test_every_child_sitemap_is_on_the_primary_brand_domain(): void
    {
        $primary = PlatformHosts::primaryBrandDomain();
        $this->assertNotNull($primary);

        $locs = $this->locs($this->indexXml());
        $this->assertNotEmpty($locs, 'the sitemap index lists no sitemaps at all');

        foreach ($locs as $loc) {
            $this->assertSame(
                $primary,
                parse_url($loc, PHP_URL_HOST),
                "the sitemap index points at {$loc}, which is not on the canonical "
                . 'domain -- search engines may ignore a cross-host sitemap reference'
            );
        }
    }

    public function test_robots_points_at_the_index_on_the_primary_brand_domain(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        preg_match('/^Sitemap:\s*(\S+)$/m', $body, $m);

        $this->assertNotEmpty($m[1] ?? '', 'robots.txt no longer references a sitemap');
        $this->assertSame(
            'https://' . PlatformHosts::primaryBrandDomain() . '/sitemap_index.xml',
            $m[1],
            'robots.txt points crawlers at a sitemap index on whichever host served it'
        );
    }

    /**
     * The index is the same whichever host builds it.
     *
     * This is the actual defect. The wrong hostname was a symptom; the cause
     * was that the answer depended on who asked first and then got cached for
     * everyone. A change that fixes the hostname but leaves the value
     * host-dependent would pass the first test here and fail this one.
     */
    public function test_the_index_does_not_vary_by_the_host_that_built_it(): void
    {
        Cache::forget(SitemapController::INDEX_CACHE_KEY);
        $builtOnLegacyHost = $this->get('https://1in.me/sitemap_index.xml')->getContent();

        Cache::forget(SitemapController::INDEX_CACHE_KEY);
        $builtOnPrimaryHost = $this->get('https://sayzio.app/sitemap_index.xml')->getContent();

        Cache::forget(SitemapController::INDEX_CACHE_KEY);
        $builtOnDevHost = $this->get('http://localhost/sitemap_index.xml')->getContent();

        $this->assertSame(
            $this->locs($builtOnPrimaryHost),
            $this->locs($builtOnLegacyHost),
            'the sitemap index still depends on the host that built it, so whichever '
            . 'request warms the shared cache decides what every crawler is told'
        );
        $this->assertSame(
            $this->locs($builtOnPrimaryHost),
            $this->locs($builtOnDevHost),
            'a build with no brand host -- which is how the scheduled warmer runs -- '
            . 'produces a different index'
        );
    }

    /**
     * The URLs inside the marketing sitemap were always right; this is here
     * so a fix to the index never gets "generalised" into the page list and
     * accidentally rewrites a URL that was correct.
     */
    public function test_the_marketing_sitemap_still_lists_pages_on_the_primary_domain(): void
    {
        $locs = $this->locs($this->get('/sitemap.xml')->assertOk()->getContent());

        $this->assertNotEmpty($locs);
        foreach (array_slice($locs, 0, 10) as $loc) {
            $this->assertStringStartsWith(
                'https://' . PlatformHosts::primaryBrandDomain(),
                $loc,
                "the marketing sitemap lists {$loc}"
            );
        }
    }
}
