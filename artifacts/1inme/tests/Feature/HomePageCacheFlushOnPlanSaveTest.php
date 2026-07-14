<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Support\PlanWriter;
use App\Modules\Common\Support\HomePageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The anonymous homepage payload (HomePageCache::ANON_PAYLOAD_PREFIX per
 * currency) embeds the plan teaser cards — names, prices, features. Admin
 * plan saves must invalidate it alongside the /pricing catalogue, or the
 * landing page keeps showing stale plan info until its TTL / warm cadence.
 *
 * The flush is wired through PricingPageCache::flush() (one choke point
 * covering PlanWriter create/update/duplicate and the PlanController
 * archive/delete/import/revert paths), so these tests assert the home
 * payload keys are dropped by the same write paths the pricing tests cover.
 *
 * Audit note: PlanRecommender's per-user cache stores only raw usage
 * counts — plan caps are reapplied on every read — so it needs no flush
 * on plan edits (see PlanRecommender::cacheKey docblock).
 */
class HomePageCacheFlushOnPlanSaveTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function makePlan(): Plan
    {
        return Plan::create([
            'name'                    => 'Plan ' . uniqid(),
            'slug'                    => 'plan-' . uniqid(),
            'description'             => 'x',
            'monthly_price'           => 10,
            'annual_price'            => 100,
            'monthly_price_secondary' => 800,
            'annual_price_secondary'  => 8000,
            'trial_days'              => 0,
            'grace_days'              => 0,
            'refund_window_days'      => 0,
            'features'                => ['max_links' => 5],
            'status'                  => 'active',
            'sort_order'              => 0,
        ]);
    }

    /** Minimal valid payload for the admin plan form. */
    private function planPayload(Plan $plan): array
    {
        return [
            'name'                    => $plan->name,
            'description'             => $plan->description,
            'monthly_price'           => 1000,
            'annual_price'            => 10000,
            'monthly_price_secondary' => 80000,
            'annual_price_secondary'  => 800000,
            'trial_days'              => 0,
            'grace_days'              => 0,
            'refund_window_days'      => 0,
            'status'                  => 'active',
            'sort_order'              => 0,
            'features'                => [],
        ];
    }

    /** Seed a fake anon home payload into every per-currency cache key. */
    private function seedAnonPayloads(): void
    {
        foreach (HomePageCache::CURRENCIES as $currency) {
            Cache::put(HomePageCache::ANON_PAYLOAD_PREFIX . $currency, '{"stale":true}', 600);
        }
    }

    private function assertAnonPayloadsFlushed(): void
    {
        foreach (HomePageCache::CURRENCIES as $currency) {
            $this->assertNull(
                Cache::get(HomePageCache::ANON_PAYLOAD_PREFIX . $currency),
                "Home anon payload for {$currency} should be flushed by the plan save."
            );
        }
    }

    public function test_admin_plan_update_flushes_home_anon_payloads(): void
    {
        $plan  = $this->makePlan();
        $admin = $this->makeAdmin();

        $this->seedAnonPayloads();

        $resp = $this->actingAs($admin, 'admin')->put(
            '/admin/plans/' . $plan->id,
            $this->planPayload($plan)
        );
        $resp->assertSessionHasNoErrors();

        $this->assertAnonPayloadsFlushed();
    }

    public function test_plan_writer_create_flushes_home_anon_payloads(): void
    {
        $plan = $this->makePlan();
        $this->seedAnonPayloads();

        $request = Request::create('/admin/plans', 'POST', $this->planPayload($plan));
        app(PlanWriter::class)->createFromRequest($request);

        $this->assertAnonPayloadsFlushed();
    }

    public function test_plan_writer_duplicate_flushes_home_anon_payloads(): void
    {
        $plan = $this->makePlan();
        $this->seedAnonPayloads();

        app(PlanWriter::class)->duplicate($plan);

        $this->assertAnonPayloadsFlushed();
    }

    public function test_flush_anon_payloads_is_safe_when_keys_absent(): void
    {
        // No seeded keys — must not throw.
        HomePageCache::flushAnonPayloads();
        $this->assertAnonPayloadsFlushed();
    }
}
