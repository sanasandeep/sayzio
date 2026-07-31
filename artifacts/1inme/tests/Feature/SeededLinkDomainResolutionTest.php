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
 * Regression guard for links created OUT-OF-BAND through the model layer
 * (tinker / seed scripts like scripts/seed-demo-folders.php).
 *
 * Such rows historically defaulted to domain_id NULL. On production the app's
 * own links are bound to the sayzio.app admin-global domain row, and during
 * demo seeding NULL-domain rows initially appeared not to resolve (the real
 * culprit was a type='short' bug, but the NULL-domain fallback was suspected).
 *
 * This pins the documented contract of Link::resolveByAlias +
 * AliasNamespace::scope: the DEFAULT platform domain's namespace includes
 * legacy domain_id IS NULL rows, so an active type='url' link with NO domain
 * binding MUST resolve on the primary brand host (sayzio.app) — whether or
 * not the sayzio.app global domain row exists — and equally when explicitly
 * bound to that row (what the seed script now does). If this breaks, seed
 * scripts silently create dead links.
 */
class SeededLinkDomainResolutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mimic the seed script's model-layer creation path: Link::create with
     * type='url' and no settings beyond what the script sets (plus
     * open_in_app=false so plain GETs redirect instead of rendering the
     * app-opener interstitial).
     */
    private function seedStyleLink(?int $domainId): Link
    {
        $user = User::factory()->create()->fresh();

        return Link::create([
            'user_id'   => $user->id,
            'domain_id' => $domainId,
            'type'      => 'url',
            'alias'     => 'demo-' . Str::lower(Str::random(7)),
            'title'     => 'Seeded demo link',
            'long_url'  => 'https://dest.example.com/seeded',
            'is_active' => true,
            'settings'  => ['open_in_app' => false],
        ]);
    }

    /** Issue a GET as if the visitor is on $host (see CrossDomainAliasResolutionTest). */
    private function getOnHost(string $host, string $path)
    {
        URL::forceRootUrl('http://' . $host);
        try {
            return $this->call('GET', $path);
        } finally {
            URL::forceRootUrl(null);
        }
    }

    public function test_primary_brand_host_is_sayzio_app(): void
    {
        // The rest of this class resolves on PlatformHosts::primaryBrandDomain();
        // pin what that actually is so the assertions below mean "on sayzio.app".
        $this->assertSame('sayzio.app', PlatformHosts::primaryBrandDomain());
    }

    public function test_null_domain_seeded_link_resolves_on_primary_brand_host_without_domain_row(): void
    {
        // Fresh-install shape: no sayzio.app row in `domains` at all. The
        // NULL namespace must still resolve on the brand host.
        Domain::whereNull('user_id')->where('domain', PlatformHosts::primaryBrandDomain())->delete();

        $link = $this->seedStyleLink(null);

        $this->getOnHost(PlatformHosts::primaryBrandDomain(), '/' . $link->alias)
            ->assertRedirect('https://dest.example.com/seeded');
    }

    public function test_null_domain_seeded_link_resolves_on_primary_brand_host_with_domain_row_present(): void
    {
        // Production shape: the sayzio.app admin-global row exists (and is the
        // default platform domain), yet a tinker/seed row with domain_id NULL
        // must STILL resolve there — NULL and the default domain id are the
        // same alias namespace.
        Domain::updateOrCreate(
            ['domain' => PlatformHosts::primaryBrandDomain()],
            [
                'user_id'     => null,
                'type'        => 'custom',
                'is_verified' => true,
                'is_active'   => true,
                'is_primary'  => true,
            ]
        );

        $link = $this->seedStyleLink(null);

        $this->getOnHost(PlatformHosts::primaryBrandDomain(), '/' . $link->alias)
            ->assertRedirect('https://dest.example.com/seeded');
        // Dev/preview hosts map to the same default namespace.
        $this->getOnHost('localhost', '/' . $link->alias)
            ->assertRedirect('https://dest.example.com/seeded');
    }

    public function test_seeded_link_bound_to_primary_domain_row_resolves_on_primary_brand_host(): void
    {
        // What scripts/seed-demo-folders.php now does: bind explicitly to the
        // primary brand domain row (looked up dynamically, never a hardcoded id).
        $sayzio = Domain::updateOrCreate(
            ['domain' => PlatformHosts::primaryBrandDomain()],
            [
                'user_id'     => null,
                'type'        => 'custom',
                'is_verified' => true,
                'is_active'   => true,
                'is_primary'  => true,
            ]
        );

        $link = $this->seedStyleLink($sayzio->id);

        $this->getOnHost(PlatformHosts::primaryBrandDomain(), '/' . $link->alias)
            ->assertRedirect('https://dest.example.com/seeded');
    }
}
