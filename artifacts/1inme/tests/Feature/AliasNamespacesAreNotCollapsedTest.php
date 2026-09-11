<?php

namespace Tests\Feature;

use App\Modules\Common\Support\PlatformHosts;
use App\Modules\Common\Support\UserContentSitemap;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * sayzio.app/sana and 1in.me/sana are DIFFERENT PAGES.
 *
 * Every admin-global brand domain is its own alias namespace: an alias bound
 * to sayzio.app does not resolve on 1in.me unless separately bound there
 * (Link::resolveByAlias documents this; verified live 2026-09-11, where
 * sayzio.app/demo-type-short-link was a 200 and 1in.me/demo-type-short-link
 * was a 404). Two users can hold the same alias on two brand domains.
 *
 * That makes the whole "consolidate onto the primary brand domain" idea --
 * which is right for the marketing pages, where both hosts serve the same
 * content -- actively harmful under /{alias}. Consolidating there tells
 * Google that one person's page is a duplicate of another person's.
 *
 * Two places had collapsed the distinction:
 *
 *   THE SITEMAP.   /sitemap-links.xml selected only id, alias and updated_at
 *                  and emitted every row on the primary host. All 180 live
 *                  URLs were claimed for sayzio.app, including links in the
 *                  1in.me namespace and links on customers' own domains,
 *                  where that URL 404s or belongs to somebody else. I made
 *                  this deterministic when I pinned the sitemaps to the brand
 *                  domain; before that it followed the request host, which
 *                  was wrong in a different way.
 *
 *   THE CANONICAL. Biolink pages fell back to PlatformHosts::canonicalUrl(),
 *                  which rewrites any brand host onto the primary. A page at
 *                  1in.me/sana declared sayzio.app/sana as its original.
 *
 * The rule this suite pins: a URL is consolidated onto the primary brand
 * domain only when the SAME CONTENT is served on both hosts. Handles
 * (/@handle, /{handle}/resume) are global and qualify. Aliases do not.
 */
class AliasNamespacesAreNotCollapsedTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    /** A global brand domain row for the legacy brand host. */
    private function legacyBrandDomain(): Domain
    {
        return Domain::firstOrCreate(
            ['domain' => '1in.me'],
            ['user_id' => null, 'type' => 'global', 'is_verified' => true, 'is_active' => true]
        );
    }

    private function makeLink(User $user, string $alias, ?int $domainId): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'domain_id' => $domainId,
            'type' => 'biolink',
            'alias' => $alias,
            'title' => 'Page ' . $alias,
            'is_active' => true,
        ]);
    }

    /** Every <loc> in the links sitemap. */
    private function linkSitemapLocs(): array
    {
        UserContentSitemap::flush();
        $xml = $this->get('/sitemap-links.xml')->assertOk()->getContent();
        preg_match_all('/<loc>([^<]+)<\/loc>/', $xml, $m);

        return $m[1];
    }

    // ------------------------------------------------------------- sitemap

    /**
     * The same alias on two brand domains appears twice, on two hosts.
     *
     * This is the shape of the bug in one assertion: before the fix both rows
     * produced the identical sayzio.app URL, so the sitemap advertised one
     * page twice and never mentioned the other at all.
     */
    public function test_the_same_alias_on_two_brand_domains_lists_two_urls(): void
    {
        $legacy = $this->legacyBrandDomain();

        $this->makeLink($this->makeUser(), 'sana', null);            // sayzio.app
        $this->makeLink($this->makeUser(), 'sana', $legacy->id);     // 1in.me

        $locs = $this->linkSitemapLocs();

        $this->assertContains(
            'https://sayzio.app/sana',
            $locs,
            'the default-namespace link is missing from the sitemap'
        );
        $this->assertContains(
            'https://1in.me/sana',
            $locs,
            'a link in the 1in.me namespace is not advertised on 1in.me -- if it is '
            . 'listed on sayzio.app instead, that URL belongs to a different user'
        );
    }

    /** A link on a customer's own domain is listed on that domain. */
    public function test_a_custom_domain_link_is_listed_on_the_custom_domain(): void
    {
        $owner = $this->makeUser();
        $custom = Domain::create([
            'user_id' => $owner->id,
            'domain' => 'links.acme.example',
            'type' => 'custom',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $this->makeLink($owner, 'menu', $custom->id);

        $locs = $this->linkSitemapLocs();

        $this->assertContains(
            'https://links.acme.example/menu',
            $locs,
            "a customer's link is not listed on their own domain"
        );
        $this->assertNotContains(
            'https://sayzio.app/menu',
            $locs,
            "a customer's link is advertised at a sayzio.app URL, which 404s -- a "
            . 'wrong URL in a sitemap is worse than a missing one'
        );
    }

    /**
     * Deleting a domain moves its links to the default namespace, and the
     * sitemap follows them there.
     *
     * I wrote this test expecting the opposite -- that a link left pointing
     * at a deleted domain should be DROPPED rather than given a guessed host,
     * since guessing the primary host is exactly the bug this suite is about.
     * The database does not allow that state: links.domain_id is
     * `nullOnDelete()`, so deleting the domain sets the column to null, and a
     * null domain_id genuinely IS the default namespace. The link really does
     * resolve at sayzio.app/orphan afterwards.
     *
     * So the assertion is the other way round, and the null-domain guard in
     * urlOnDomain() stays as defence against a row whose host is unusable
     * rather than against this case.
     */
    public function test_deleting_a_domain_moves_its_links_to_the_default_namespace(): void
    {
        $owner = $this->makeUser();
        $custom = Domain::create([
            'user_id' => $owner->id,
            'domain' => 'gone.example',
            'type' => 'custom',
            'is_verified' => true,
            'is_active' => true,
        ]);
        $link = $this->makeLink($owner, 'orphan', $custom->id);

        Domain::where('id', $custom->id)->delete();
        Cache::flush();

        $this->assertNull(
            $link->fresh()->domain_id,
            'links.domain_id is no longer nullOnDelete, so a link can now point at a '
            . 'domain row that does not exist -- and the sitemap would have to decide '
            . 'what host to claim for it'
        );

        $this->assertContains(
            'https://sayzio.app/orphan',
            $this->linkSitemapLocs(),
            'a link that fell back to the default namespace is missing from the sitemap'
        );
    }

    // ----------------------------------------------------------- canonical

    /**
     * The wiring, not just the helper.
     *
     * The four helper tests below all pass against the unfixed page, because
     * they call hostScopedCanonicalUrl() directly and the bug was that the
     * VIEW called the other one. This renders a real biolink on the legacy
     * brand host and reads the tag off the page.
     */
    public function test_a_biolink_served_on_the_legacy_host_canonicalises_to_that_host(): void
    {
        $legacy = $this->legacyBrandDomain();
        $link = $this->makeLink($this->makeUser(), 'sana', $legacy->id);

        $html = $this->get('https://1in.me/' . $link->alias)->getContent();

        preg_match('/<link rel="canonical" href="([^"]+)"/', (string) $html, $m);
        $canonical = $m[1] ?? 'NONE';

        $this->assertStringStartsWith(
            'https://1in.me/',
            $canonical,
            "a biolink served on 1in.me declares {$canonical} as its original -- that is "
            . "a different page in a different alias namespace, possibly another user's"
        );
    }

    /**
     * A page under the alias namespace canonicalises to the host that served
     * it.
     */
    public function test_the_host_scoped_canonical_keeps_a_brand_host(): void
    {
        $this->assertSame(
            'https://1in.me/sana',
            $this->hostScopedFor('https://1in.me/sana'),
            'a 1in.me page is declaring a sayzio.app URL as its original, which is a '
            . "different page and possibly a different user's"
        );
    }

    /** And it still folds www onto the bare host, which IS the same page. */
    public function test_the_host_scoped_canonical_folds_www(): void
    {
        $this->assertSame(
            'https://sayzio.app/sana',
            $this->hostScopedFor('https://www.sayzio.app/sana'),
            'the www variant serves the same alias namespace and must not be a '
            . 'second canonical URL'
        );
    }

    /** And it still strips tracking parameters. */
    public function test_the_host_scoped_canonical_strips_tracking(): void
    {
        $this->assertSame(
            'https://1in.me/sana',
            $this->hostScopedFor('https://1in.me/sana?utm_source=instagram&fbclid=x'),
            'a shared biolink with campaign tags creates a new canonical URL each time'
        );
    }

    /** A custom domain keeps its own host, unchanged. */
    public function test_the_host_scoped_canonical_leaves_a_custom_domain_alone(): void
    {
        $this->assertSame(
            'https://links.acme.example/menu',
            $this->hostScopedFor('https://links.acme.example/menu')
        );
    }

    /**
     * The marketing helper still consolidates. The two must not be merged
     * into one "canonical" helper: the whole point is that they differ.
     */
    public function test_the_marketing_canonical_still_consolidates(): void
    {
        $this->assertSame(
            'https://sayzio.app/pricing',
            $this->withRequest(
                'https://1in.me/pricing',
                fn () => PlatformHosts::canonicalUrl()
            ),
            'marketing pages serve the same content on both brand hosts and must '
            . 'still consolidate onto one'
        );
    }

    // ------------------------------------------------- all five brand hosts

    /**
     * There are five brand domains, not the two in the constant.
     *
     * Sana: "1in.me / bizs.club / getbio.one / sayzio.app / sayzio.link -
     * there all are global domains, managed by admin." PlatformHosts had two
     * of them hardcoded, so the other three were invisible to every
     * consolidation path and each served the whole marketing site
     * canonicalising to itself. Verified live 2026-09-11: bizs.club/pricing
     * and getbio.one/pricing both returned the full Sayzio pricing page,
     * identically titled, each claiming to be the original.
     *
     * The list is read from the domains table, so a sixth domain added in
     * admin is covered without a deploy -- which is why this test creates
     * one rather than asserting today's five by name.
     */
    public function test_a_brand_domain_added_in_admin_consolidates_without_a_deploy(): void
    {
        Domain::firstOrCreate(
            ['domain' => 'bizs.club'],
            ['user_id' => null, 'type' => 'global', 'is_verified' => true, 'is_active' => true]
        );
        Cache::flush();

        $this->assertTrue(
            PlatformHosts::isNonPrimaryBrandDomain('bizs.club'),
            'a global domain the admin manages is not recognised as ours, so its copy '
            . 'of the marketing site competes with sayzio.app'
        );

        $this->assertSame(
            'https://sayzio.app/pricing',
            $this->withRequest(
                'https://bizs.club/pricing',
                fn () => PlatformHosts::canonicalUrl()
            ),
            'a marketing page on a brand domain still claims to be the original'
        );
    }

    /** And the www variant of one of those domains folds too. */
    public function test_the_www_variant_of_a_db_driven_brand_domain_folds(): void
    {
        Domain::firstOrCreate(
            ['domain' => 'getbio.one'],
            ['user_id' => null, 'type' => 'global', 'is_verified' => true, 'is_active' => true]
        );
        Cache::flush();

        $this->assertSame(
            'https://getbio.one/sana',
            $this->hostScopedFor('https://www.getbio.one/sana'),
            'www.getbio.one is a second canonical URL for the same alias namespace'
        );
    }

    /**
     * A brand domain keeps its OWN alias namespace, even now that the
     * canonical layer recognises it.
     *
     * This is the line the previous fix drew, restated for the three domains
     * that were just added to the brand list: recognising a host as ours
     * means its MARKETING pages consolidate. It must not start collapsing its
     * aliases onto sayzio.app, which is the exact bug this whole file exists
     * for.
     */
    public function test_recognising_a_brand_domain_does_not_merge_its_aliases(): void
    {
        Domain::firstOrCreate(
            ['domain' => 'bizs.club'],
            ['user_id' => null, 'type' => 'global', 'is_verified' => true, 'is_active' => true]
        );
        Cache::flush();

        $this->assertSame(
            'https://bizs.club/sana',
            $this->hostScopedFor('https://bizs.club/sana'),
            'an alias page on bizs.club is canonicalising onto sayzio.app, where that '
            . 'URL is a different page'
        );
    }

    /** A customer's own domain is still not one of ours. */
    public function test_a_custom_domain_is_not_treated_as_a_brand_host(): void
    {
        $owner = $this->makeUser();
        Domain::create([
            'user_id' => $owner->id,
            'domain' => 'links.acme.example',
            'type' => 'custom',
            'is_verified' => true,
            'is_active' => true,
        ]);
        Cache::flush();

        $this->assertFalse(
            PlatformHosts::isNonPrimaryBrandDomain('links.acme.example'),
            "a customer's domain is being redirected to sayzio.app, which takes their "
            . 'site down'
        );
    }

    private function hostScopedFor(string $url): string
    {
        return $this->withRequest($url, fn () => PlatformHosts::hostScopedCanonicalUrl());
    }

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
}
