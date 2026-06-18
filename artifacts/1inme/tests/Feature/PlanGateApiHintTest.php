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
 * End-to-end guard for the mobile "upgrade hint" flow. The Sanctum REST
 * API stamps a recommended-plan hint into the plan-gated error envelope's
 * `details` (`recommended_plan` slug + `recommended_plan_name` + `feature`)
 * so the mobile /upgrade screen can pre-highlight and scroll to the plan
 * that unlocks the blocked feature (see `1inme-mobile/lib/upgradePrompt.ts`
 * + `app/upgrade.tsx`). These tests pin the exact envelope shape the mobile
 * parser reads, so a regression in the error shape or `planGate()` param
 * passing fails CI instead of silently shipping a generic upgrade screen.
 *
 * NOTE: auth uses a real Bearer token, NOT Sanctum::actingAs — the latter
 * skips the TouchSessionToken middleware the API path relies on.
 */
class PlanGateApiHintTest extends TestCase
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
            'name'     => 'Hint ' . Str::random(4),
            'email'    => 'hint-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        return $user->fresh();
    }

    /**
     * Smart-link creation is gated on the boolean `link_smart_rules` feature.
     * A user on a plan without it must get the hint pointing at the cheapest
     * active plan that DOES include it.
     */
    public function test_smart_link_gate_stamps_recommended_plan_hint(): void
    {
        // Cheapest plan lacks the feature; the next tier unlocks it. The hint
        // must resolve to the unlocking tier, never the user's current plan.
        $free = $this->plan(['link_smart_rules' => false], 'free', 0);
        $pro  = $this->plan(['link_smart_rules' => true], 'pro', 1500);

        $user = $this->userOn($free);
        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->postJson('/api/v1/links/smart', [
            'long_url' => 'https://example.com/promo',
            'rules'    => [['if' => ['country' => ['US']], 'then' => ['url' => 'https://us.example.com']]],
        ]);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.code', 'plan_upgrade_required');
        $resp->assertJsonPath('error.details.feature', 'link_smart_rules');
        $resp->assertJsonPath('error.details.recommended_plan', $pro->slug);
        $resp->assertJsonPath('error.details.recommended_plan_name', $pro->name);
    }

    /**
     * The guided biolink wizard's /generate endpoint enforces the numeric
     * `max_links` cap. Blowing past it must surface a 403 `link_limit` error
     * whose details point at the cheapest plan that raises the cap.
     */
    public function test_wizard_generate_gate_stamps_recommended_plan_hint(): void
    {
        $basic = $this->plan(['max_links' => 1], 'basic', 0);
        $pro   = $this->plan(['max_links' => 50], 'pro', 2500);

        $user = $this->userOn($basic);

        // Fill the single-link allowance so the next create is over the cap.
        $link = new Link([
            'user_id'   => $user->id,
            'type'      => 'short',
            'alias'     => Link::generateAlias(),
            'long_url'  => 'https://example.com/existing',
            'is_active' => true,
        ]);
        $link->save();

        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'category'  => 'creator',
            'page_type' => 'influencer',
            'answers'   => ['display_name' => 'Demo Creator'],
        ]);

        $resp->assertStatus(403);
        $resp->assertJsonPath('error.code', 'link_limit');
        $resp->assertJsonPath('error.details.feature', 'max_links');
        $resp->assertJsonPath('error.details.recommended_plan', $pro->slug);
        $resp->assertJsonPath('error.details.recommended_plan_name', $pro->name);
    }

    /**
     * Fallback contract: when no higher plan unlocks the feature (e.g. the
     * only active plan is the user's own), the gate still rejects with the
     * `feature` key but OMITS the recommended-plan keys. The mobile parser
     * treats a missing `recommended_plan` as "show the generic upgrade view",
     * so older servers / dead-end gates keep working.
     */
    public function test_smart_link_gate_omits_hint_when_no_plan_unlocks_it(): void
    {
        // Only one active plan exists and it lacks the feature, so there is no
        // qualifying upgrade target.
        $free = $this->plan(['link_smart_rules' => false], 'free', 0);

        $user = $this->userOn($free);
        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->postJson('/api/v1/links/smart', [
            'long_url' => 'https://example.com/promo',
            'rules'    => [['if' => ['country' => ['US']], 'then' => ['url' => 'https://us.example.com']]],
        ]);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.details.feature', 'link_smart_rules');
        $resp->assertJsonMissingPath('error.details.recommended_plan');
        $resp->assertJsonMissingPath('error.details.recommended_plan_name');
    }
}
