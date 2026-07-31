<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for the mobile link-create per-type plan caps in
 * Api\LinkController::store(). The type-quota block used to call a
 * nonexistent $this->planLimitError(), so hitting a page-type cap
 * (conversational/slides/ai_chat/resume/paid_page/brand_kit) fataled with
 * "Call to undefined method" instead of returning the friendly 402
 * plan_upgrade_required envelope the mobile upgrade screen parses.
 * These tests pin both gate branches (module toggle off + numeric cap
 * exceeded) to the planGate() envelope shape.
 *
 * NOTE: auth uses a real Bearer token, NOT Sanctum::actingAs — the latter
 * skips the TouchSessionToken middleware the API path relies on.
 */
class ApiLinkTypeQuotaGateTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features, string $slug, int $monthlyPrice = 0): Plan
    {
        return Plan::create([
            'name'          => ucfirst($slug),
            'slug'          => $slug,
            'monthly_price' => $monthlyPrice,
            'annual_price'  => $monthlyPrice * 10,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => $features,
        ]);
    }

    private function userOn(Plan $plan): User
    {
        $user = User::create([
            'name'     => 'Quota ' . Str::random(4),
            'email'    => 'quota-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        return $user->fresh();
    }

    /**
     * Module toggle branch: a plan with module_slides=false must reject a
     * mobile slides create with 402 plan_upgrade_required, including the
     * recommended-plan hint pointing at the plan that enables the module.
     */
    public function test_module_toggle_off_returns_plan_gate_402(): void
    {
        $free = $this->plan(['module_slides' => false], 'plan-' . Str::lower(Str::random(6)), 0);
        $pro  = $this->plan(['module_slides' => true], 'plan-' . Str::lower(Str::random(6)), 1500);

        $user = $this->userOn($free);
        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->postJson('/api/v1/links', [
            'type'  => 'slides',
            'title' => 'My story',
        ]);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.code', 'plan_upgrade_required');
        $resp->assertJsonPath('error.details.feature', 'module_slides');
        $resp->assertJsonPath('error.details.recommended_plan', $pro->slug);
        $resp->assertJsonPath('error.details.recommended_plan_name', $pro->name);
    }

    /**
     * Numeric cap branch: max_slides=1 with one existing slides link must
     * reject the second create with 402 plan_upgrade_required and hint the
     * plan that raises the cap past current usage.
     */
    public function test_type_cap_exceeded_returns_plan_gate_402(): void
    {
        $basic = $this->plan(['module_slides' => true, 'max_slides' => 1], 'plan-' . Str::lower(Str::random(6)), 0);
        $pro   = $this->plan(['module_slides' => true, 'max_slides' => 50], 'plan-' . Str::lower(Str::random(6)), 2500);

        $user = $this->userOn($basic);

        $existing = new Link([
            'user_id'   => $user->id,
            'type'      => 'slides',
            'alias'     => Link::generateAlias(),
            'is_active' => true,
        ]);
        $existing->user_id = $user->id;
        $existing->save();

        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->postJson('/api/v1/links', [
            'type'  => 'slides',
            'title' => 'Second story',
        ]);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.code', 'plan_upgrade_required');
        $resp->assertJsonPath('error.details.feature', 'max_slides');
        // A fresh DB seeds the real plan lineup, so the cheapest plan that
        // raises the cap may be a seeded one — assert a hint exists rather
        // than pinning it to the fixture plan.
        $this->assertNotEmpty($resp->json('error.details.recommended_plan'));
        $this->assertNotEmpty($resp->json('error.details.recommended_plan_name'));
    }
}
