<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Support\PlanWriter;
use App\Modules\Common\Support\PricingPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The public /pricing page is served from PricingPageCache (a cached
 * catalogue with a TTL / scheduled warmer). Admin plan saves must flush
 * that cache so an edit — price, features, included coin grants — shows
 * on /pricing on the very next request instead of serving stale
 * marketing info until the TTL or warm cadence catches up.
 *
 * Covers:
 *   - the end-to-end path: warm cache → admin PUT /admin/plans/{plan}
 *     with a new included_coins_monthly → GET /pricing shows the new value;
 *   - PlanWriter::createFromRequest and ::duplicate also invalidate
 *     the cached catalogue key.
 */
class PricingPageCacheFlushOnPlanSaveTest extends TestCase
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

    private function makePlan(array $features = []): Plan
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
            'features'                => array_merge(['max_links' => 5], $features),
            'status'                  => 'active',
            'sort_order'              => 0,
        ]);
    }

    /** Minimal valid PUT payload for the admin plan form. */
    private function updatePayload(Plan $plan, array $features = []): array
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
            'features'                => $features,
        ];
    }

    public function test_admin_plan_update_flushes_cache_and_pricing_shows_new_coin_grant(): void
    {
        $plan  = $this->makePlan(['included_coins_monthly' => 100]);
        $admin = $this->makeAdmin();

        // Warm the pricing catalogue cache with the OLD value.
        $this->get('/pricing')->assertOk();
        $this->assertNotNull(Cache::get(PricingPageCache::catalogKey()));

        // Admin edits the plan's monthly coin grant to a distinctive value.
        $resp = $this->actingAs($admin, 'admin')->put(
            '/admin/plans/' . $plan->id,
            $this->updatePayload($plan, ['included_coins_monthly' => 4321])
        );
        $resp->assertSessionHasNoErrors();

        // The cached catalogue was invalidated by the save…
        $this->assertNull(Cache::get(PricingPageCache::catalogKey()));

        // …and the very next /pricing render shows the new value.
        $page = $this->get('/pricing');
        $page->assertOk();
        // The pricing page renders numeric features via number_format().
        $page->assertSee('4,321', false);

        // Sanity: the persisted feature actually changed.
        $this->assertSame(4321, (int) ($plan->fresh()->features['included_coins_monthly'] ?? 0));
    }

    public function test_plan_writer_create_flushes_cached_catalog(): void
    {
        $plan = $this->makePlan();
        $this->get('/pricing')->assertOk();
        $this->assertNotNull(Cache::get(PricingPageCache::catalogKey()));

        $request = Request::create('/admin/plans', 'POST', $this->updatePayload($plan));
        app(PlanWriter::class)->createFromRequest($request);

        $this->assertNull(Cache::get(PricingPageCache::catalogKey()));
    }

    public function test_plan_writer_duplicate_flushes_cached_catalog(): void
    {
        $plan = $this->makePlan();
        $this->get('/pricing')->assertOk();
        $this->assertNotNull(Cache::get(PricingPageCache::catalogKey()));

        app(PlanWriter::class)->duplicate($plan);

        $this->assertNull(Cache::get(PricingPageCache::catalogKey()));
    }
}
