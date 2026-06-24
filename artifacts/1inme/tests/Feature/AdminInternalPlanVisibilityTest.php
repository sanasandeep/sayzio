<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Services\PlanRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for "internal" (admin/staff-only) plans:
 *   1. The `public` scope excludes internal plans (the filter every
 *      public surface — /pricing, /user/upgrade, the recommender — uses).
 *   2. The PlanRecommender never recommends an internal plan, even when
 *      handed one directly.
 *   3. The admin Duplicate action deep-copies a plan (features + price
 *      rows + addons) and defensively marks the copy internal + inactive.
 */
class AdminInternalPlanVisibilityTest extends TestCase
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

    private function makePlan(array $attrs = []): Plan
    {
        return Plan::create(array_merge([
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
        ], $attrs));
    }

    public function test_public_scope_excludes_internal_plans(): void
    {
        $visible  = $this->makePlan(['is_internal' => false]);
        $internal = $this->makePlan(['is_internal' => true]);

        $ids = Plan::active()->public()->pluck('id')->all();

        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($internal->id, $ids);

        // Admin listing (no public scope) still includes the internal plan.
        $allIds = Plan::where('is_archived', false)->pluck('id')->all();
        $this->assertContains($internal->id, $allIds);
    }

    public function test_recommender_never_returns_an_internal_plan(): void
    {
        $user = User::factory()->create(['plan_id' => null]);

        $internal = $this->makePlan([
            'is_internal' => true,
            'is_popular'  => true,
            'monthly_price' => 50,
            'sort_order'  => 5,
        ]);

        $rec = PlanRecommender::for($user, collect([$internal]));

        $this->assertNotNull($rec);
        $this->assertNull($rec['recommendedPlan']);
    }

    public function test_duplicate_creates_internal_inactive_copy_with_prices(): void
    {
        $admin = $this->makeAdmin();
        $plan  = $this->makePlan([
            'name'        => 'Comp Plan',
            'status'      => 'active',
            'is_internal' => false,
            'is_popular'  => true,
            'features'    => ['max_links' => 42],
        ]);
        // Seed the authoritative price rows the duplicate should copy.
        foreach ([
            ['USD', 'monthly', 1999],
            ['USD', 'annual',  19990],
            ['INR', 'monthly', 149900],
            ['INR', 'annual',  1499000],
        ] as [$cur, $cycle, $minor]) {
            Price::create([
                'priceable_type'     => Plan::class,
                'priceable_id'       => $plan->id,
                'currency'           => $cur,
                'billing_cycle'      => $cycle,
                'amount_minor_units' => $minor,
                'is_active'          => true,
            ]);
        }

        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.duplicate', $plan));

        $resp->assertSessionHasNoErrors();

        $copy = Plan::where('name', 'Comp Plan (Copy)')->first();
        $this->assertNotNull($copy);
        $this->assertTrue($copy->is_internal);
        $this->assertSame('inactive', $copy->status);
        $this->assertFalse($copy->is_popular);
        $this->assertNotSame($plan->slug, $copy->slug);
        $this->assertSame(42, (int) ($copy->features['max_links'] ?? 0));

        // Prices were deep-copied onto the new plan.
        $copyPrices = Price::where('priceable_type', Plan::class)
            ->where('priceable_id', $copy->id)
            ->get();
        $this->assertSame(4, $copyPrices->count());
        $this->assertSame(
            1999,
            (int) $copyPrices->where('currency', 'USD')->where('billing_cycle', 'monthly')->first()->amount_minor_units
        );

        $resp->assertRedirect(route('admin.plans.edit', $copy));
    }
}
