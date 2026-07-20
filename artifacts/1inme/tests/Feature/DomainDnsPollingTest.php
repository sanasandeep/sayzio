<?php

namespace Tests\Feature;

use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DNS-propagation polling probes (web JSON branch + mobile API endpoint).
 *
 * The web verify action now answers JSON to background fetch() probes so the
 * domains page can poll every 30 s without a reload, and the mobile API has
 * a matching POST /api/v1/domains/{id}/verify. A not-yet-propagated CNAME is
 * an expected state (200 with verified=false), never an error redirect.
 *
 * Test domains use hosts guaranteed to have no CNAME pointing at the app, so
 * the live dns_get_record check deterministically fails to match.
 */
class DomainDnsPollingTest extends TestCase
{
    use RefreshDatabase;

    private function makeDomain(User $user, array $attrs = []): Domain
    {
        return Domain::create(array_merge([
            'user_id'            => $user->id,
            'domain'             => 'poll-test-' . uniqid() . '.example.com',
            'is_active'          => true,
            'is_verified'        => false,
            'verification_token' => str()->random(32),
            'cname_target'       => parse_url(config('app.url'), PHP_URL_HOST),
            'type'               => 'redirect',
        ], $attrs));
    }

    public function test_web_json_probe_returns_verified_false_while_dns_unpropagated(): void
    {
        $user = User::factory()->create();
        $domain = $this->makeDomain($user);

        $res = $this->actingAs($user)
            ->postJson(route('user.domains.verify', $domain));

        $res->assertOk()->assertJson(['verified' => false]);
        $this->assertFalse($domain->fresh()->is_verified);
    }

    public function test_web_json_probe_short_circuits_when_already_verified(): void
    {
        $user = User::factory()->create();
        $domain = $this->makeDomain($user, [
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('user.domains.verify', $domain))
            ->assertOk()
            ->assertJson(['verified' => true]);
    }

    public function test_web_non_json_verify_still_redirects_back_with_flash(): void
    {
        $user = User::factory()->create();
        $domain = $this->makeDomain($user);

        $this->actingAs($user)
            ->from(route('user.domains.index'))
            ->post(route('user.domains.verify', $domain))
            ->assertRedirect(route('user.domains.index'))
            ->assertSessionHas('error');
    }

    public function test_web_probe_forbidden_for_other_users_domain(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $domain = $this->makeDomain($owner);

        $this->actingAs($intruder)
            ->postJson(route('user.domains.verify', $domain))
            ->assertForbidden();
    }

    public function test_api_probe_returns_verified_false_with_expected_cname(): void
    {
        $user = User::factory()->create();
        $domain = $this->makeDomain($user);
        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)->postJson("/api/v1/domains/{$domain->id}/verify");

        $res->assertOk()
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.domain.id', $domain->id);
        $this->assertNotEmpty($res->json('data.expected_cname'));
    }

    public function test_api_probe_short_circuits_when_already_verified(): void
    {
        $user = User::factory()->create();
        $domain = $this->makeDomain($user, [
            'is_verified' => true,
            'verified_at' => now(),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/domains/{$domain->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.verified', true);
    }

    public function test_api_probe_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $domain = $this->makeDomain($owner);
        $token = $intruder->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/domains/{$domain->id}/verify")
            ->assertNotFound();
    }
}
