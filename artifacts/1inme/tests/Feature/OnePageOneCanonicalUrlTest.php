<?php

namespace Tests\Feature;

use App\Modules\Common\Support\PlatformHosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every marketing page tells search engines exactly one URL is the original.
 *
 * Two ways that was false on the live site, measured 2026-09-11:
 *
 *   HOST.   www.sayzio.app and sayzio.app both served the whole site, neither
 *           redirected to the other, and each page's canonical pointed at the
 *           host that served it. So every page existed twice, each copy
 *           claiming to be the original. The cause was that normalize() does
 *           not strip `www.`, so the www host matched no brand domain and
 *           fell through every consolidation path there is.
 *
 *   QUERY.  sayzio.app/?utm_source=newsletter declared its canonical as
 *           sayzio.app/?utm_source=newsletter. Every campaign link created a
 *           new "original" homepage.
 *
 * Both were one line: canonicalUrl() compared the raw host against the brand
 * list, and used getRequestUri(), which carries the query string.
 *
 * The tests below are grouped by what they protect, because two of them
 * protect something that is NOT a bug and is easy to "fix" into one: a
 * customer's own domain must keep its own canonical, and a parameter that
 * changes what the page shows must survive.
 */
class OnePageOneCanonicalUrlTest extends TestCase
{
    use RefreshDatabase;

    /** The canonical href the app renders for a given absolute URL. */
    private function canonicalFor(string $url): string
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<link rel="canonical"/',
            substr($html, 0, 60000),
            'no canonical tag rendered at all'
        );

        preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $m);

        return $m[1] ?? '';
    }

    // ---------------------------------------------------------------- host

    /**
     * The www host 301s to the apex on the marketing surface.
     *
     * This is the primary fix: a redirect settles the duplicate outright,
     * where a canonical tag only advises. The canonical still matters for
     * routes outside the brand.primary group, which is the next test.
     */
    public function test_the_www_host_redirects_to_the_bare_domain(): void
    {
        foreach ([
            'https://www.sayzio.app/pricing' => 'https://sayzio.app/pricing',
            'https://www.sayzio.app/features' => 'https://sayzio.app/features',
            'https://1in.me/pricing' => 'https://sayzio.app/pricing',
        ] as $requested => $expected) {
            $response = $this->get($requested);

            $response->assertStatus(301);
            $this->assertSame(
                $expected,
                $response->headers->get('Location'),
                "{$requested} no longer consolidates onto the primary domain, so "
                . 'every page it serves is a duplicate of the real one'
            );
        }
    }

    /**
     * And the canonical tag agrees, for anything the redirect does not cover.
     *
     * Asserted against PlatformHosts directly rather than through a request,
     * because every marketing route redirects now -- there is no page left to
     * render the tag on. The helper is still what the whole site's canonical,
     * og:url and JSON-LD `url` are built from, so this is the real guard.
     */
    public function test_the_canonical_helper_rewrites_the_www_host(): void
    {
        $this->assertSame(
            'https://sayzio.app/some-page',
            $this->canonicalUrlForRequest('https://www.sayzio.app/some-page'),
            'a page served on www would tell search engines it is the original'
        );
    }

    /** What PlatformHosts::canonicalUrl() returns for a given request URL. */
    private function canonicalUrlForRequest(string $url): string
    {
        return $this->withRequest($url, fn () => PlatformHosts::canonicalUrl());
    }

    /** Run $fn with $url bound as the current request. */
    private function withRequest(string $url, callable $fn)
    {
        $previous = request();
        try {
            app()->instance('request', \Illuminate\Http\Request::create($url));

            return $fn();
        } finally {
            app()->instance('request', $previous);
        }
    }

    public function test_the_www_host_is_treated_as_a_non_primary_brand_host(): void
    {
        // This is what makes the 301 fire, not just the canonical tag.
        $this->assertTrue(
            PlatformHosts::isNonPrimaryBrandDomain('www.sayzio.app'),
            'www.sayzio.app no longer redirects to the apex'
        );
        $this->assertTrue(
            PlatformHosts::isNonPrimaryBrandDomain('www.1in.me'),
            'the www variant of the legacy brand domain no longer consolidates'
        );

        // The primary itself must never redirect -- that is an infinite loop.
        $this->assertFalse(
            PlatformHosts::isNonPrimaryBrandDomain('sayzio.app'),
            'the primary domain is redirecting to itself'
        );
    }

    public function test_the_legacy_brand_domain_still_canonicalises_to_the_primary(): void
    {
        $this->assertSame(
            'https://sayzio.app/pricing',
            $this->canonicalUrlForRequest('https://1in.me/pricing'),
            'the 1in.me consolidation regressed'
        );
    }

    /**
     * A customer's own domain keeps its own canonical.
     *
     * This is the failure mode to fear from the host rewrite above: pointing
     * a customer's page at sayzio.app would hand their rankings to us. It is
     * also the "obvious" simplification -- rewrite every host to the primary
     * -- so it gets a test rather than a comment.
     */
    public function test_a_custom_domain_keeps_its_own_canonical(): void
    {
        $canonical = $this->canonicalUrlForRequest('https://links.somecustomer.example/pricing');

        $this->assertStringNotContainsString(
            'sayzio.app',
            $canonical,
            'a custom domain is canonicalising onto sayzio.app, which hands that '
            . "customer's page to us in search results"
        );
    }

    // --------------------------------------------------------------- query

    public function test_campaign_parameters_are_stripped_from_the_canonical(): void
    {
        foreach ([
            '/?utm_source=newsletter&utm_medium=email&utm_campaign=launch' => 'https://sayzio.app/',
            '/?fbclid=IwAR0abc123' => 'https://sayzio.app/',
            '/?gclid=Cj0KCQ' => 'https://sayzio.app/',
            '/pricing?utm_source=twitter' => 'https://sayzio.app/pricing',
            '/pricing?ref=producthunt' => 'https://sayzio.app/pricing',
        ] as $requested => $expected) {
            $this->assertSame(
                $expected,
                $this->canonicalFor('https://sayzio.app' . $requested),
                "a tracking parameter survived into the canonical for {$requested}"
            );
        }
    }

    public function test_a_tracked_link_on_the_www_host_still_lands_on_one_url(): void
    {
        // Both bugs at once -- the combination a real campaign link produces.
        $this->assertSame(
            'https://sayzio.app/pricing',
            $this->canonicalUrlForRequest('https://www.sayzio.app/pricing?utm_source=email&fbclid=abc'),
            'a tracked link to the www host still declares its own canonical'
        );
    }

    /**
     * A parameter that changes what the page shows is NOT stripped.
     *
     * The tempting version of this fix is an allow list -- keep `page`, drop
     * everything else -- and it is wrong in a way that does not show up until
     * months later. Canonicalising `?page=2` onto page 1 tells Google that
     * page 2 is a duplicate and not to index the links on it. So the filter
     * is a deny list of known tracking parameters, and this is the test that
     * stops someone inverting it.
     */
    public function test_parameters_that_change_the_page_survive(): void
    {
        $canonical = $this->canonicalFor('https://sayzio.app/blogs?page=2');

        $this->assertStringContainsString(
            'page=2',
            $canonical,
            'pagination was stripped from the canonical, which tells search engines '
            . 'that page 2 is a duplicate of page 1 and its links need not be crawled'
        );
    }

    /**
     * The canonical and og:url agree.
     *
     * They are rendered from the same variable, so this is cheap; it is here
     * because they have drifted in other codebases every time someone adds a
     * second source for one of them.
     */
    public function test_the_canonical_and_the_og_url_agree(): void
    {
        $html = $this->get('https://sayzio.app/?utm_source=newsletter')->assertOk()->getContent();

        preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $canonical);
        preg_match('/<meta property="og:url" content="([^"]+)"/', $html, $og);

        $this->assertSame(
            $canonical[1] ?? 'no-canonical',
            $og[1] ?? 'no-og-url',
            'the canonical URL and og:url disagree, so shares and search see two '
            . 'different "official" addresses for one page'
        );
    }
}
