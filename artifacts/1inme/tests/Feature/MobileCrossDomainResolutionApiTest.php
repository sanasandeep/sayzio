<?php

namespace Tests\Feature;

use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\PaidPageTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage that the MOBILE-facing public resolve API keeps a link
 * reachable across both canonical brand hosts (sayzio.app / 1in.me), the same
 * way the web redirect layer does (see CrossDomainAliasResolutionTest, which
 * pins RedirectController + the domain picker).
 *
 * The Expo app resolves a public link by alias through host-scoped REST
 * endpoints — e.g. GET /api/v1/paid-page/{alias} — which call
 * Link::resolveByAlias($alias, $request->getHost()). A regression in
 * shared-namespace resolution would be invisible to the web redirect tests
 * but would break the mobile viewer, so this exercises the API surface
 * directly:
 *
 *   - A link bound to one admin-global brand domain resolves through the
 *     mobile API regardless of which brand host the request appears on.
 *   - A NULL-domain (legacy) link resolves on both brand hosts.
 *   - A user-owned custom-domain link stays scoped to its own host and is
 *     NOT reachable through the mobile API on either brand host.
 *
 * sayzio.app and 1in.me are hard-wired platform hosts via
 * PlatformHosts::PLATFORM_DOMAINS, so host classification here does not
 * depend on env at all.
 */
class MobileCrossDomainResolutionApiTest extends TestCase
{
    use RefreshDatabase;

    /** The two canonical brand hosts, taken straight from the source of truth. */
    private const BRAND_PRIMARY   = 'sayzio.app';
    private const BRAND_SECONDARY = '1in.me';

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'Creator ' . Str::random(4),
            'email'    => 'cr' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
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

    /**
     * A public Paid Page link — a standalone `paid_page` type resolved by
     * alias through the host-scoped mobile resolve API. Visibility `public`
     * so an anonymous (token-less) mobile viewer gets a 200.
     */
    private function makePaidPage(User $user, ?int $domainId, string $alias): Link
    {
        return Link::create([
            'user_id'    => $user->id,
            'domain_id'  => $domainId,
            'type'       => Link::TYPE_PAID_PAGE,
            'alias'      => $alias,
            'title'      => 'Premium',
            'is_active'  => true,
            'visibility' => 'public',
            'settings'   => ['paid_page' => ['template' => PaidPageTemplates::DEFAULT_ID]],
        ]);
    }

    /**
     * GET a JSON API path as if the mobile client is on $host. Forcing the
     * URL generator root makes Laravel's testing harness build an absolute
     * URL on that host, so the controller's $request->getHost() reports it
     * (otherwise APP_URL wins and getHost() is "localhost"). Mirrors the
     * getOnHost() helper in CrossDomainAliasResolutionTest.
     */
    private function getJsonOnHost(string $host, string $path)
    {
        URL::forceRootUrl('http://' . $host);
        try {
            return $this->getJson($path);
        } finally {
            URL::forceRootUrl(null);
        }
    }

    public function test_global_domain_bound_link_resolves_on_both_brand_hosts_via_mobile_api(): void
    {
        $user    = $this->makeUser();
        $sayzio  = $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);
        $oneInMe = $this->globalBrandDomain(self::BRAND_SECONDARY);

        // Built/created "on" sayzio.app (domain_id bound to the sayzio global row).
        $alias = 'pp-' . Str::random(6);
        $this->makePaidPage($user, $sayzio->id, $alias);

        // Reachable through the mobile resolve API on its own brand host …
        $this->getJsonOnHost(self::BRAND_PRIMARY, '/api/v1/paid-page/' . $alias)
            ->assertOk()
            ->assertJsonPath('data.page.alias', $alias);

        // … and, crucially, on the OTHER brand host too — the shared platform
        // namespace spans every admin-global domain.
        $this->getJsonOnHost(self::BRAND_SECONDARY, '/api/v1/paid-page/' . $alias)
            ->assertOk()
            ->assertJsonPath('data.page.alias', $alias);

        // Symmetry: a link bound to the 1in.me global row resolves on sayzio.app.
        $alias2 = 'pp2-' . Str::random(6);
        $this->makePaidPage($user, $oneInMe->id, $alias2);
        $this->getJsonOnHost(self::BRAND_PRIMARY, '/api/v1/paid-page/' . $alias2)
            ->assertOk()
            ->assertJsonPath('data.page.alias', $alias2);
    }

    public function test_null_domain_link_resolves_on_both_brand_hosts_via_mobile_api(): void
    {
        $user = $this->makeUser();
        $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);
        $this->globalBrandDomain(self::BRAND_SECONDARY);

        // A pre-existing/legacy link with no domain binding at all.
        $alias = 'ppnull-' . Str::random(6);
        $this->makePaidPage($user, null, $alias);

        $this->getJsonOnHost(self::BRAND_PRIMARY, '/api/v1/paid-page/' . $alias)
            ->assertOk()
            ->assertJsonPath('data.page.alias', $alias);
        $this->getJsonOnHost(self::BRAND_SECONDARY, '/api/v1/paid-page/' . $alias)
            ->assertOk()
            ->assertJsonPath('data.page.alias', $alias);
    }

    public function test_user_owned_custom_domain_link_stays_scoped_in_mobile_api(): void
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

        $alias = 'ppcust-' . Str::random(6);
        $this->makePaidPage($user, $custom->id, $alias);

        // Reachable through the mobile API on its own custom host …
        $this->getJsonOnHost('go.example.com', '/api/v1/paid-page/' . $alias)
            ->assertOk()
            ->assertJsonPath('data.page.alias', $alias);

        // … but NOT from either brand host — the shared platform namespace
        // only spans admin-global rows, never user-owned domains.
        $this->getJsonOnHost(self::BRAND_PRIMARY, '/api/v1/paid-page/' . $alias)
            ->assertNotFound();
        $this->getJsonOnHost(self::BRAND_SECONDARY, '/api/v1/paid-page/' . $alias)
            ->assertNotFound();
    }
}
