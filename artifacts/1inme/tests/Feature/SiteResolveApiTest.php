<?php

namespace Tests\Feature;

use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public "is this site on Sayzio?" resolver (GET /api/v1/resolve/site).
 *
 * Verified + active custom domains owned by a user resolve to a small public
 * owner card; everything else (unknown hosts, unverified/inactive domains,
 * admin-global domains, junk input) reports on_sayzio=false.
 */
class SiteResolveApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeDomain(array $attrs = []): Domain
    {
        $user = User::factory()->create(['name' => 'Casey Creator', 'handle' => 'casey']);

        return Domain::create(array_merge([
            'user_id'     => $user->id,
            'domain'      => 'links.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ], $attrs));
    }

    public function test_verified_custom_domain_resolves_to_owner_card(): void
    {
        $this->makeDomain();

        $res = $this->getJson('/api/v1/resolve/site?host=links.example.com');

        $res->assertOk()
            ->assertJsonPath('data.on_sayzio', true)
            ->assertJsonPath('data.owner.name', 'Casey Creator')
            ->assertJsonPath('data.owner.handle', 'casey');
    }

    public function test_host_is_normalized_before_lookup(): void
    {
        $this->makeDomain();

        $res = $this->getJson('/api/v1/resolve/site?host=' . urlencode('WWW.Links.Example.com:443'));

        $res->assertOk()->assertJsonPath('data.on_sayzio', true);
    }

    public function test_unverified_domain_does_not_resolve(): void
    {
        $this->makeDomain(['is_verified' => false]);

        $this->getJson('/api/v1/resolve/site?host=links.example.com')
            ->assertOk()
            ->assertJsonPath('data.on_sayzio', false);
    }

    public function test_inactive_domain_does_not_resolve(): void
    {
        $this->makeDomain(['is_active' => false]);

        $this->getJson('/api/v1/resolve/site?host=links.example.com')
            ->assertOk()
            ->assertJsonPath('data.on_sayzio', false);
    }

    public function test_global_platform_domain_does_not_resolve(): void
    {
        Domain::create([
            'user_id'     => null,
            'domain'      => 'global.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $this->getJson('/api/v1/resolve/site?host=global.example.com')
            ->assertOk()
            ->assertJsonPath('data.on_sayzio', false);
    }

    public function test_unknown_and_junk_hosts_report_false(): void
    {
        $this->getJson('/api/v1/resolve/site?host=nowhere.example.org')
            ->assertOk()->assertJsonPath('data.on_sayzio', false);

        $this->getJson('/api/v1/resolve/site?host=')
            ->assertOk()->assertJsonPath('data.on_sayzio', false);

        $this->getJson('/api/v1/resolve/site?host=' . urlencode('not a host!'))
            ->assertOk()->assertJsonPath('data.on_sayzio', false);
    }
}
