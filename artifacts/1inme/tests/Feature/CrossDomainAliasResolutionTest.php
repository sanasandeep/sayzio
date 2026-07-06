<?php

namespace Tests\Feature;

use App\Modules\Common\Support\PlatformHosts;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pins the cross-brand behavior introduced when sayzio.app was added as a
 * second canonical platform domain alongside 1in.me and made the primary.
 *
 * Two concerns are covered:
 *
 *   1. Shared-namespace alias resolution (RedirectController + Link::resolveByAlias):
 *      a link bound to one global domain resolves on the OTHER brand host,
 *      a NULL-domain (legacy) link resolves on both, and a user-owned
 *      custom-domain link never leaks across the brand hosts.
 *
 *   2. The mobile domain picker (`GET /api/v1/domains/available`) reports
 *      sayzio.app as primary_domain_id and lists both global brand domains
 *      in `availableTo` once they are verified+active.
 *
 * Both sayzio.app and 1in.me are hard-wired platform hosts via
 * PlatformHosts::PLATFORM_DOMAINS, so they are recognised as "the platform"
 * regardless of what APP_URL / Replit env advertise — this test does not
 * depend on env at all for host classification.
 */
class CrossDomainAliasResolutionTest extends TestCase
{
    use RefreshDatabase;

    /** The two canonical brand hosts, taken straight from the source of truth. */
    private const BRAND_PRIMARY   = 'sayzio.app';
    private const BRAND_SECONDARY = '1in.me';

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    /** An admin-global (no owning user), verified+active brand domain row. */
    private function globalBrandDomain(string $host, bool $primary = false): Domain
    {
        return Domain::create([
            'user_id'     => null,
            'domain'      => $host,
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
            'is_primary'  => $primary,
        ]);
    }

    private function makeUrlLink(User $user, ?int $domainId, string $alias, string $url): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'domain_id' => $domainId,
            'type'      => 'url',
            'alias'     => $alias,
            'long_url'  => $url,
            'is_active' => true,
            // Disable the app-opener interstitial so plain GETs redirect.
            'settings'  => ['open_in_app' => false],
        ]);
    }

    /**
     * Issue a GET as if the browser is on $host. Force the URL generator's
     * root to the same host so Laravel's testing harness reports it as the
     * request host (otherwise APP_URL wins and getHost() is "localhost").
     */
    private function getOnHost(string $host, string $path)
    {
        URL::forceRootUrl('http://' . $host);
        try {
            return $this->call('GET', $path);
        } finally {
            URL::forceRootUrl(null);
        }
    }

    /** Authenticate as $user with a real Sanctum bearer token (like the Expo app). */
    private function asUser(User $user): self
    {
        $this->withToken($user->createToken('xdomain-test')->plainTextToken);
        return $this;
    }

    // ---------------------------------------------------------------------
    // 1. Shared-namespace alias resolution across both brand hosts
    // ---------------------------------------------------------------------

    public function test_link_bound_to_one_global_domain_resolves_on_the_other_brand_host(): void
    {
        $user      = $this->makeUser();
        $sayzio    = $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);
        $oneInMe   = $this->globalBrandDomain(self::BRAND_SECONDARY);

        // Created "on" sayzio.app (domain_id bound to the sayzio global row).
        $alias = 'x-' . Str::random(6);
        $this->makeUrlLink($user, $sayzio->id, $alias, 'https://dest.example.com/sayzio');

        // Resolves on its own brand host.
        $this->getOnHost(self::BRAND_PRIMARY, '/' . $alias)
            ->assertRedirect('https://dest.example.com/sayzio');

        // And, crucially, also on the OTHER brand host (1in.me) — the shared
        // platform namespace spans every admin-global domain.
        $this->getOnHost(self::BRAND_SECONDARY, '/' . $alias)
            ->assertRedirect('https://dest.example.com/sayzio');

        // Symmetry: a link bound to the 1in.me global row resolves on sayzio.app.
        $legacyAlias = 'y-' . Str::random(6);
        $this->makeUrlLink($user, $oneInMe->id, $legacyAlias, 'https://dest.example.com/oneinme');
        $this->getOnHost(self::BRAND_PRIMARY, '/' . $legacyAlias)
            ->assertRedirect('https://dest.example.com/oneinme');
    }

    public function test_null_domain_link_resolves_on_both_brand_hosts(): void
    {
        $user = $this->makeUser();
        $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);
        $this->globalBrandDomain(self::BRAND_SECONDARY);

        // A pre-existing/legacy link with no domain binding at all.
        $alias = 'null-' . Str::random(6);
        $this->makeUrlLink($user, null, $alias, 'https://dest.example.com/legacy');

        $this->getOnHost(self::BRAND_PRIMARY, '/' . $alias)
            ->assertRedirect('https://dest.example.com/legacy');
        $this->getOnHost(self::BRAND_SECONDARY, '/' . $alias)
            ->assertRedirect('https://dest.example.com/legacy');
    }

    public function test_user_owned_custom_domain_link_never_leaks_across_brand_hosts(): void
    {
        $user = $this->makeUser();
        $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);
        $this->globalBrandDomain(self::BRAND_SECONDARY);

        // A user-owned, verified custom domain — NOT a platform/global row.
        $custom = Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'go.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $alias = 'cust-' . Str::random(6);
        $this->makeUrlLink($user, $custom->id, $alias, 'https://dest.example.com/custom');

        // Resolves on its own custom host.
        $this->getOnHost('go.example.com', '/' . $alias)
            ->assertRedirect('https://dest.example.com/custom');

        // But must NOT be visible from either brand host — the shared platform
        // namespace only spans admin-global rows, never user-owned domains.
        $this->getOnHost(self::BRAND_PRIMARY, '/' . $alias)
            ->assertViewIs('common.short-link-not-found');
        $this->getOnHost(self::BRAND_SECONDARY, '/' . $alias)
            ->assertViewIs('common.short-link-not-found');
    }

    // ---------------------------------------------------------------------
    // 2. Mobile domain picker: sayzio.app is primary, both globals listed
    // ---------------------------------------------------------------------

    public function test_picker_reports_sayzio_as_primary_and_lists_both_global_brand_domains(): void
    {
        $user   = $this->makeUser();
        $sayzio = $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);
        $oneInMe = $this->globalBrandDomain(self::BRAND_SECONDARY);

        $this->asUser($user);
        $resp = $this->getJson('/api/v1/domains/available');

        $resp->assertOk();
        // sayzio.app is the admin-chosen primary → pre-selected in create/edit.
        $resp->assertJsonPath('data.primary_domain_id', $sayzio->id);
        // The displayed default host is the canonical primary brand domain.
        $resp->assertJsonPath('data.default_host', PlatformHosts::primary());

        // Both global brand domains are attachable once verified+active.
        $ids = array_column($resp->json('data.items'), 'id');
        $this->assertContains($sayzio->id, $ids);
        $this->assertContains($oneInMe->id, $ids);

        // The sayzio row is flagged global + primary so the picker can badge it.
        $sayzioItem = collect($resp->json('data.items'))->firstWhere('id', $sayzio->id);
        $this->assertTrue($sayzioItem['is_global']);
        $this->assertTrue($sayzioItem['is_primary']);

        // 1in.me is global but not primary.
        $oneInMeItem = collect($resp->json('data.items'))->firstWhere('id', $oneInMe->id);
        $this->assertTrue($oneInMeItem['is_global']);
        $this->assertFalse($oneInMeItem['is_primary']);
    }

    public function test_picker_omits_an_unverified_global_brand_domain(): void
    {
        $user   = $this->makeUser();
        $sayzio = $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);

        // 1in.me exists but verification isn't complete yet → not attachable.
        $pending = Domain::create([
            'user_id'     => null,
            'domain'      => self::BRAND_SECONDARY,
            'type'        => 'custom',
            'is_verified' => false,
            'is_active'   => true,
        ]);

        $this->asUser($user);
        $resp = $this->getJson('/api/v1/domains/available');

        $resp->assertOk();
        $ids = array_column($resp->json('data.items'), 'id');
        $this->assertContains($sayzio->id, $ids);
        $this->assertNotContains($pending->id, $ids);
    }
}
