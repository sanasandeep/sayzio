<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The discounted first-term price, the struck-through normal price and the
 * intro badge come from {@see PricingResolver::introFor} and are embedded
 * (per currency) into the page's Alpine JSON payload on BOTH the public
 * /pricing page and the in-app /user/upgrade page. This asserts those
 * surfaces render the discounted price + badge when a plan has an active
 * intro, and fall back to the normal price when it does not.
 *
 * Both currencies are always embedded in the payload (the switcher flips
 * USD/INR client-side), so the USD-formatted values are present regardless
 * of the request's geo-resolved currency — we assert against USD, whose
 * `$` symbol survives @json encoding unescaped (₹ is \u-escaped).
 */
class PricingPagesIntroRenderTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(array $attrs = []): Plan
    {
        $plan = Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.Str::random(6), 'description' => 'Pro plan',
            'monthly_price' => 20.00, 'annual_price' => 200.00, 'trial_days' => 0,
            'status' => 'active', 'is_archived' => false, 'is_internal' => false,
            'sort_order' => 1, 'features' => [],
        ], $attrs));
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 2000],
            ['USD', 'annual', 20000],
            ['INR', 'monthly', 40000],
            ['INR', 'annual', 400000],
        ]);
        return $plan;
    }

    private function introConfig(): array
    {
        return [
            'enabled' => true,
            'type'    => 'percent',
            'percent' => 25,
            'label'   => 'New customer offer',
        ];
    }

    private function owner(): User
    {
        $u = User::create([
            'name'     => 'u'.Str::random(4),
            'email'    => 'u'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    // --- /pricing (public) ------------------------------------------------

    public function test_pricing_page_surfaces_discounted_price_and_badge(): void
    {
        $this->makePlan(['intro_discount' => $this->introConfig()]);

        $resp = $this->get('/pricing');
        $resp->assertOk();

        // 25% off $20.00 => $15.00 discounted first term, $5.00 off.
        $resp->assertSee('$15.00');   // discounted first-term price
        $resp->assertSee('$20.00');   // struck-through normal price
        $resp->assertSee('New customer offer'); // intro badge label
    }

    public function test_pricing_page_falls_back_to_normal_price_without_intro(): void
    {
        // Same price, but no intro_discount configured.
        $this->makePlan();

        $resp = $this->get('/pricing');
        $resp->assertOk();

        $resp->assertSee('$20.00');             // normal price still shown
        $resp->assertDontSee('$15.00');         // no discounted price
        $resp->assertDontSee('New customer offer');
    }

    // --- /user/upgrade (authenticated owner) ------------------------------

    public function test_upgrade_page_surfaces_discounted_price_and_badge(): void
    {
        $this->makePlan(['intro_discount' => $this->introConfig()]);
        $user = $this->owner();

        $resp = $this->actingAs($user)->get('/user/upgrade');
        $resp->assertOk();

        $resp->assertSee('$15.00');
        $resp->assertSee('$20.00');
        $resp->assertSee('New customer offer');
    }

    public function test_upgrade_page_falls_back_to_normal_price_without_intro(): void
    {
        $this->makePlan();
        $user = $this->owner();

        $resp = $this->actingAs($user)->get('/user/upgrade');
        $resp->assertOk();

        $resp->assertSee('$20.00');
        $resp->assertDontSee('$15.00');
        $resp->assertDontSee('New customer offer');
    }
}
