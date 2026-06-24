<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanctum (bearer-token) coverage for the mobile RevenueCat billing
 * catalogue (GET /api/v1/billing/plans), which feeds the mobile billing
 * screen. The JSON each plan carries a per-currency `prices[CUR].monthly.intro`
 * DISPLAY block (first/normal/amount_off formatted, percent_off, label)
 * assembled by {@see RevenueCatBillingController::plans} via
 * {@see PricingResolver::introFor}. This guards against a regression in how
 * that controller assembles/formats the intro blocks (dropping the key,
 * wrong currency, only-on-Plan gating) shipping silently to mobile users.
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which injects a mock that breaks the TouchSessionToken
 * middleware — every authed request would 500). See sanctum-api-tests.md.
 */
class RevenueCatBillingPlansIntroTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => 'Test ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * A plan priced in both currencies with a 25% first-term intro discount
     * on all cycles. USD monthly = $20.00 (2000c), INR monthly = ₹400.00
     * (40000 paise).
     */
    private function makeIntroPlan(): Plan
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4), 'description' => 'Pro',
            'monthly_price' => 20.00, 'annual_price' => 200.00, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
            'intro_discount' => [
                'enabled' => true,
                'type'    => 'percent',
                'percent' => 25,
                'label'   => 'New customer offer',
            ],
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 2000],
            ['USD', 'annual', 20000],
            ['INR', 'monthly', 40000],
            ['INR', 'annual', 400000],
        ]);
        return $plan;
    }

    private function makePlainPlan(): Plan
    {
        $plan = Plan::create([
            'name' => 'Plain', 'slug' => 'plain-' . Str::random(4), 'description' => 'Plain',
            'monthly_price' => 10.00, 'annual_price' => 100.00, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 2, 'features' => [],
            // No intro_discount config at all.
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 1000],
            ['USD', 'annual', 10000],
            ['INR', 'monthly', 20000],
            ['INR', 'annual', 200000],
        ]);
        return $plan;
    }

    public function test_plans_requires_authentication(): void
    {
        $this->getJson('/api/v1/billing/plans')->assertStatus(401);
    }

    public function test_plan_with_active_intro_returns_populated_block_for_both_currencies(): void
    {
        $introPlan = $this->makeIntroPlan();
        $user = $this->makeUser();

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($user)])
            ->getJson('/api/v1/billing/plans')
            ->assertOk();

        // Locate this plan inside the returned catalogue by id.
        $plans = collect($res->json('data.plans'));
        $plan = $plans->firstWhere('id', $introPlan->id);
        $this->assertNotNull($plan, 'intro plan missing from /billing/plans response');

        // USD monthly intro: 25% off $20.00 => $5.00 off, $15.00 first term.
        $usd = $plan['prices']['USD']['monthly']['intro'];
        $this->assertNotNull($usd, 'USD monthly intro block dropped');
        $this->assertSame('$15.00', $usd['first_formatted']);
        $this->assertSame('$20.00', $usd['normal_formatted']);
        $this->assertSame('$5.00', $usd['amount_off_formatted']);
        $this->assertSame(25, $usd['percent_off']);
        $this->assertSame('New customer offer', $usd['label']);

        // INR monthly intro: 25% off ₹400.00 => ₹100.00 off, ₹300.00 first term.
        $inr = $plan['prices']['INR']['monthly']['intro'];
        $this->assertNotNull($inr, 'INR monthly intro block dropped');
        $this->assertSame('₹300.00', $inr['first_formatted']);
        $this->assertSame('₹400.00', $inr['normal_formatted']);
        $this->assertSame('₹100.00', $inr['amount_off_formatted']);
        $this->assertSame(25, $inr['percent_off']);
        $this->assertSame('New customer offer', $inr['label']);
    }

    public function test_plan_without_intro_returns_null_intro_block(): void
    {
        $plainPlan = $this->makePlainPlan();
        $user = $this->makeUser();

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($user)])
            ->getJson('/api/v1/billing/plans')
            ->assertOk();

        $plan = collect($res->json('data.plans'))->firstWhere('id', $plainPlan->id);
        $this->assertNotNull($plan, 'plain plan missing from /billing/plans response');

        $this->assertNull($plan['prices']['USD']['monthly']['intro']);
        $this->assertNull($plan['prices']['USD']['annual']['intro']);
        $this->assertNull($plan['prices']['INR']['monthly']['intro']);
        $this->assertNull($plan['prices']['INR']['annual']['intro']);
    }

    public function test_addons_never_carry_an_intro_block(): void
    {
        // An addon configured exactly like an intro plan would be — yet the
        // controller must NEVER attach an intro block (intro is Plan-only).
        $addon = Addon::create([
            'name'                    => 'Boost ' . Str::random(4),
            'slug'                    => 'boost-' . Str::random(4),
            'description'             => 'x',
            'type'                    => 'recurring',
            'status'                  => 'active',
            'sort_order'              => 1,
            'monthly_price'           => 5.00,
            'annual_price'            => 50.00,
            'monthly_price_secondary' => 100.00,
            'annual_price_secondary'  => 1000.00,
        ]);
        PricingResolver::upsertManyFromMinor($addon, [
            ['USD', 'monthly', 500],
            ['USD', 'annual', 5000],
            ['INR', 'monthly', 10000],
            ['INR', 'annual', 100000],
        ]);

        $user = $this->makeUser();

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($user)])
            ->getJson('/api/v1/billing/plans')
            ->assertOk();

        $addons = collect($res->json('data.addons'));
        $this->assertNotEmpty($addons, 'no addons returned');

        foreach ($addons as $a) {
            $this->assertArrayHasKey('prices', $a);
            foreach (['USD', 'INR'] as $cur) {
                $this->assertNull($a['prices'][$cur]['monthly']['intro'], "addon {$cur} monthly carried an intro");
                $this->assertNull($a['prices'][$cur]['annual']['intro'], "addon {$cur} annual carried an intro");
            }
        }
    }
}
