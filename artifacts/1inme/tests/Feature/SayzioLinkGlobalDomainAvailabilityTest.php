<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for the platform's default branded domain `sayzio.link`.
 *
 * `sayzio.link` is seeded as an admin-global, verified+active, UNTAGGED
 * domain (no plan and no badge restrictions), so it must be attachable by
 * every account — including a user on the free plan — via the domain
 * picker (`GET /api/v1/domains/available`, backed by `Domain::availableTo`).
 *
 * Without this test a future plan-tagging accident (attaching a `domain_plan`
 * row to `sayzio.link`) would silently gate the platform's own default host
 * behind a paid plan and nothing would catch it.
 */
class SayzioLinkGlobalDomainAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const SAYZIO_LINK = 'sayzio.link';

    /** Authenticate as the given user with a real Sanctum bearer token. */
    private function asUser(User $user): self
    {
        $this->withToken($user->createToken('domain-test')->plainTextToken);
        return $this;
    }

    /** The free (default) plan, keeping the historical `free` slug. */
    private function freePlan(): Plan
    {
        return Plan::firstOrCreate(
            ['slug' => 'free'],
            [
                'name'          => 'Starter',
                'monthly_price' => 0,
                'annual_price'  => 0,
                'status'        => 'active',
                'sort_order'    => 0,
                'features'      => [],
            ]
        );
    }

    private function freePlanUser(): User
    {
        return User::factory()->create(['plan_id' => $this->freePlan()->id])->fresh();
    }

    /** The seeded sayzio.link global domain (created by its seed migration). */
    private function sayzioLink(): Domain
    {
        $domain = Domain::query()
            ->withoutGlobalScope('workspace')
            ->where('domain', self::SAYZIO_LINK)
            ->first();

        $this->assertNotNull(
            $domain,
            'sayzio.link should be seeded as a global domain by its migration.'
        );

        return $domain;
    }

    public function test_sayzio_link_is_available_to_a_free_plan_user(): void
    {
        $user = $this->freePlanUser();
        $sayzio = $this->sayzioLink();

        $ids = Domain::availableTo($user)->pluck('id')->all();
        $this->assertContains(
            $sayzio->id,
            $ids,
            'sayzio.link must be attachable by a free-plan user.'
        );
    }

    public function test_available_endpoint_lists_sayzio_link_for_a_free_plan_user(): void
    {
        $user   = $this->freePlanUser();
        $sayzio = $this->sayzioLink();

        $resp = $this->asUser($user)->getJson('/api/v1/domains/available');
        $resp->assertOk();

        $items = $resp->json('data.items');
        $ids   = array_column($items, 'id');
        $this->assertContains(
            $sayzio->id,
            $ids,
            'GET /domains/available must return sayzio.link for a free-plan user.'
        );

        $item = collect($items)->firstWhere('id', $sayzio->id);
        $this->assertSame(self::SAYZIO_LINK, $item['domain']);
        $this->assertTrue($item['is_global'], 'sayzio.link is an admin-global domain.');
    }

    public function test_sayzio_link_is_not_plan_gated_or_restricted(): void
    {
        $sayzio = $this->sayzioLink();

        // Untagged: no plan or badge restrictions attached.
        $this->assertSame(0, $sayzio->plans()->count(), 'sayzio.link must carry no plan tags.');
        $this->assertSame(0, $sayzio->badges()->count(), 'sayzio.link must carry no badge tags.');

        // A plan-restricted global domain (tagged to a paid plan) is genuinely
        // gated away from the free user — proving the entitlement query does
        // filter — while sayzio.link, being untagged, still comes through.
        $paidPlan = Plan::create([
            'name'          => 'Pro ' . Str::random(4),
            'slug'          => 'pro-' . Str::random(6),
            'monthly_price' => 900,
            'annual_price'  => 9000,
            'status'        => 'active',
            'sort_order'    => 1,
            'features'      => [],
        ]);
        $restricted = Domain::create([
            'user_id'     => null,
            'domain'      => 'pro-only-' . Str::random(5) . '.test',
            'type'        => 'redirect',
            'is_verified' => true,
            'is_active'   => true,
        ]);
        $restricted->plans()->sync([$paidPlan->id]);

        $user = $this->freePlanUser();
        $ids  = Domain::availableTo($user)->pluck('id')->all();

        $this->assertContains(
            $sayzio->id,
            $ids,
            'untagged sayzio.link stays open to the free user.'
        );
        $this->assertNotContains(
            $restricted->id,
            $ids,
            'a plan-restricted global domain must be gated away from a free-plan user.'
        );
    }

    public function test_available_reports_primary_and_default_host_unchanged(): void
    {
        $user = $this->freePlanUser();

        // A separate admin-chosen primary global domain — seeding sayzio.link
        // (is_primary=false) must not disturb primary/default selection.
        $primary = Domain::create([
            'user_id'     => null,
            'domain'      => 'go.sayzio.test',
            'type'        => 'redirect',
            'is_verified' => true,
            'is_active'   => true,
            'is_primary'  => true,
        ]);

        $resp = $this->asUser($user)->getJson('/api/v1/domains/available');
        $resp->assertOk();

        // The admin-chosen primary is still the selected default, not sayzio.link.
        $resp->assertJsonPath('data.primary_domain_id', $primary->id);
        $resp->assertJsonPath('data.default_host', \App\Modules\Common\Support\PlatformHosts::primary());

        // sayzio.link is present but is NOT the primary.
        $sayzioItem = collect($resp->json('data.items'))->firstWhere('domain', self::SAYZIO_LINK);
        $this->assertNotNull($sayzioItem, 'sayzio.link should still be listed alongside the primary.');
        $this->assertFalse($sayzioItem['is_primary'], 'sayzio.link must not be the platform primary.');
    }
}
