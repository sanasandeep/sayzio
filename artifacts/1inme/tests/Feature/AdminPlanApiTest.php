<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mobile (Sanctum bearer-token) parity for the back-office plan editor:
 *   1. The admin plan listing includes the admin-only `is_internal` flag
 *      and still lists internal plans (NOT filtered by ->public()).
 *   2. The public /plans catalog excludes internal plans.
 *   3. Admins can set/clear `is_internal` on create and update.
 *   4. The duplicate endpoint deep-copies a plan (features + price rows +
 *      addons) and forces the copy internal + inactive.
 *   5. Non-admin callers are forbidden.
 *
 * Authenticated requests use a real personal access token (not
 * Sanctum::actingAs) so the TouchSessionToken middleware works.
 */
class AdminPlanApiTest extends TestCase
{
    use RefreshDatabase;

    /** A web User bridged (by email) to an active super-admin back-office Admin. */
    private function makeAdminUser(): User
    {
        $email = 'op' . uniqid() . '@example.com';
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        Admin::create([
            'name'     => 'Operator',
            'email'    => $email,
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
        return User::factory()->create(['email' => $email]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
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

    /** A minimal valid create/update payload (prices are minor units). */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'                    => 'API Plan',
            'description'             => 'made via api',
            'monthly_price'           => 999,
            'annual_price'            => 9990,
            'monthly_price_secondary' => 74900,
            'annual_price_secondary'  => 749000,
            'trial_days'              => 7,
            'grace_days'              => 3,
            'refund_window_days'      => 14,
            'status'                  => 'active',
            'sort_order'              => 1,
        ], $overrides);
    }

    public function test_admin_index_lists_internal_plans_with_flag(): void
    {
        $admin    = $this->makeAdminUser();
        $visible  = $this->makePlan(['is_internal' => false]);
        $internal = $this->makePlan(['is_internal' => true]);

        $resp = $this->withToken($this->token($admin))->getJson('/api/v1/admin/plans');
        $resp->assertOk();

        $plans = collect($resp->json('data.plans'));
        $this->assertTrue($plans->contains('id', $internal->id), 'internal plan must be visible to admin');
        $this->assertTrue($plans->contains('id', $visible->id));

        $row = $plans->firstWhere('id', $internal->id);
        $this->assertArrayHasKey('is_internal', $row);
        $this->assertTrue($row['is_internal']);
    }

    public function test_public_catalog_excludes_internal_plans(): void
    {
        $visible  = $this->makePlan(['is_internal' => false]);
        $internal = $this->makePlan(['is_internal' => true]);

        $resp = $this->getJson('/api/v1/plans');
        $resp->assertOk();

        $ids = collect($resp->json('data.items'))->pluck('id')->all();
        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($internal->id, $ids);
    }

    public function test_admin_can_create_an_internal_plan(): void
    {
        $admin = $this->makeAdminUser();

        $resp = $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/plans', $this->payload(['name' => 'Comp Unlimited', 'is_internal' => true]));

        $resp->assertCreated();
        $resp->assertJsonPath('data.is_internal', true);

        $plan = Plan::where('name', 'Comp Unlimited')->first();
        $this->assertNotNull($plan);
        $this->assertTrue($plan->is_internal);
        // Price rows synced from minor units.
        $this->assertSame(4, Price::where('priceable_type', Plan::class)->where('priceable_id', $plan->id)->count());
    }

    public function test_admin_can_set_and_clear_internal_on_update(): void
    {
        $admin = $this->makeAdminUser();
        $plan  = $this->makePlan(['is_internal' => false]);

        // Set.
        $this->withToken($this->token($admin))
            ->putJson("/api/v1/admin/plans/{$plan->id}", $this->payload(['name' => $plan->name, 'is_internal' => true]))
            ->assertOk()
            ->assertJsonPath('data.is_internal', true);
        $this->assertTrue($plan->fresh()->is_internal);

        // Clear (omitting is_internal => boolean() false, matching the web form).
        $this->withToken($this->token($admin))
            ->putJson("/api/v1/admin/plans/{$plan->id}", $this->payload(['name' => $plan->name]))
            ->assertOk()
            ->assertJsonPath('data.is_internal', false);
        $this->assertFalse($plan->fresh()->is_internal);
    }

    public function test_admin_duplicate_creates_internal_inactive_copy_with_prices(): void
    {
        $admin = $this->makeAdminUser();
        $plan  = $this->makePlan([
            'name'        => 'Pro',
            'status'      => 'active',
            'is_internal' => false,
            'is_popular'  => true,
            'features'    => ['max_links' => 42],
        ]);
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

        $resp = $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/plans/{$plan->id}/duplicate");

        $resp->assertCreated();
        $resp->assertJsonPath('data.is_internal', true);
        $resp->assertJsonPath('data.status', 'inactive');

        $copy = Plan::where('name', 'Pro (Copy)')->first();
        $this->assertNotNull($copy);
        $this->assertTrue($copy->is_internal);
        $this->assertSame('inactive', $copy->status);
        $this->assertFalse($copy->is_popular);
        $this->assertNotSame($plan->slug, $copy->slug);
        $this->assertSame(42, (int) ($copy->features['max_links'] ?? 0));

        $copyPrices = Price::where('priceable_type', Plan::class)->where('priceable_id', $copy->id)->get();
        $this->assertSame(4, $copyPrices->count());
        $this->assertSame(
            1999,
            (int) $copyPrices->where('currency', 'USD')->where('billing_cycle', 'monthly')->first()->amount_minor_units
        );
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();
        $token = $this->token($user);

        $this->withToken($token)->getJson('/api/v1/admin/plans')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/admin/plans', $this->payload())->assertForbidden();
        $this->withToken($token)->putJson("/api/v1/admin/plans/{$plan->id}", $this->payload())->assertForbidden();
        $this->withToken($token)->postJson("/api/v1/admin/plans/{$plan->id}/duplicate")->assertForbidden();
    }
}
