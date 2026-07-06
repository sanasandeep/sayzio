<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\BillingCyclePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Cross-page persistence of the visitor's preferred billing cycle
 * (Task #765). Mirrors the shape of PricingResolverPersistenceTest —
 * the same precedence chain (query > session > cookie > default) and
 * the same long-lived-cookie story.
 */
class BillingCyclePreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function bindRequest(array $query = [], array $cookies = []): Request
    {
        $request = Request::create('/pricing', 'GET', $query, $cookies);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        return $request;
    }

    protected function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    // ────────────────────────── Service unit-ish tests ──────────────────────────

    public function test_resolve_defaults_to_monthly_for_first_time_visitors(): void
    {
        $request = $this->bindRequest();
        $this->assertSame('monthly', BillingCyclePreference::resolve($request));
    }

    public function test_resolve_uses_query_string_when_present(): void
    {
        $request = $this->bindRequest(['cycle' => 'annual']);
        $this->assertSame('annual', BillingCyclePreference::resolve($request));
    }

    public function test_resolve_falls_back_to_session_when_no_query(): void
    {
        $request = $this->bindRequest();
        session([BillingCyclePreference::SESSION_KEY => 'annual']);
        $this->assertSame('annual', BillingCyclePreference::resolve($request));
    }

    public function test_resolve_falls_back_to_cookie_when_no_session(): void
    {
        // Session is bound but empty; cookie carries the previous choice.
        $request = $this->bindRequest([], [BillingCyclePreference::COOKIE_KEY => 'annual']);
        $this->assertSame('annual', BillingCyclePreference::resolve($request));
    }

    public function test_query_wins_over_session_and_cookie(): void
    {
        $request = $this->bindRequest(
            ['cycle' => 'monthly'],
            [BillingCyclePreference::COOKIE_KEY => 'annual'],
        );
        session([BillingCyclePreference::SESSION_KEY => 'annual']);
        $this->assertSame('monthly', BillingCyclePreference::resolve($request));
    }

    public function test_session_wins_over_cookie(): void
    {
        $request = $this->bindRequest([], [BillingCyclePreference::COOKIE_KEY => 'monthly']);
        session([BillingCyclePreference::SESSION_KEY => 'annual']);
        $this->assertSame('annual', BillingCyclePreference::resolve($request));
    }

    public function test_invalid_query_value_is_ignored_and_falls_through(): void
    {
        $request = $this->bindRequest(
            ['cycle' => 'weekly'],
            [BillingCyclePreference::COOKIE_KEY => 'annual'],
        );
        $this->assertSame('annual', BillingCyclePreference::resolve($request));
    }

    public function test_invalid_cookie_value_is_ignored(): void
    {
        $request = $this->bindRequest([], [BillingCyclePreference::COOKIE_KEY => 'lifetime']);
        $this->assertSame('monthly', BillingCyclePreference::resolve($request));
    }

    public function test_remember_writes_session_and_returns_long_lived_cookie(): void
    {
        $this->bindRequest();
        $cookie = BillingCyclePreference::remember('annual');

        $this->assertSame('annual', session(BillingCyclePreference::SESSION_KEY));
        $this->assertSame(BillingCyclePreference::COOKIE_KEY, $cookie->getName());
        $this->assertSame('annual', $cookie->getValue());

        $expectedExpiry = time() + BillingCyclePreference::COOKIE_DAYS * 24 * 60 * 60;
        $this->assertEqualsWithDelta($expectedExpiry, $cookie->getExpiresTime(), 60);
    }

    public function test_remember_clamps_garbage_to_default(): void
    {
        $this->bindRequest();
        $cookie = BillingCyclePreference::remember('weekly');

        $this->assertSame('monthly', session(BillingCyclePreference::SESSION_KEY));
        $this->assertSame('monthly', $cookie->getValue());
    }

    // ────────────────────────── End-to-end controller tests ──────────────────────────

    protected function seedPlanCatalogue(): void
    {
        // The pricing/upgrade pages list active, non-archived plans.
        // A single seed row is enough to render both pages without
        // dragging in the full DatabaseSeeder. Prices live in the
        // related `plan_prices` table; an empty relation is fine for
        // these assertions (PricingResolver returns a $0 row).
        if (Plan::query()->exists()) {
            return;
        }
        Plan::create([
            'slug'        => 'free',
            'name'        => 'Free',
            'description' => 'Get started',
            'status'      => 'active',
            'is_archived' => false,
            'is_popular'  => false,
            'sort_order'  => 1,
            'features'    => [],
        ]);
    }

    public function test_pricing_page_uses_cookie_when_no_query_param(): void
    {
        $this->seedPlanCatalogue();

        $response = $this->withCookies([
            BillingCyclePreference::COOKIE_KEY => 'annual',
        ])->get('/pricing');

        $response->assertOk();
        // Alpine bootstraps the toggle state from `$cycle` — that's
        // the user-visible signal the cookie was honored.
        $response->assertSee("cycle: 'annual'", false);
    }

    public function test_pricing_page_query_param_overrides_cookie(): void
    {
        $this->seedPlanCatalogue();

        $response = $this->withCookies([
            BillingCyclePreference::COOKIE_KEY => 'monthly',
        ])->get('/pricing?cycle=annual');

        $response->assertOk();
        $response->assertSee("cycle: 'annual'", false);
    }

    public function test_pricing_page_persists_resolved_cycle_into_cookie(): void
    {
        $this->seedPlanCatalogue();

        $response = $this->get('/pricing?cycle=annual');

        $response->assertOk();
        $response->assertCookie(BillingCyclePreference::COOKIE_KEY, 'annual');
    }

    public function test_remember_cycle_endpoint_persists_choice_and_returns_204(): void
    {
        $response = $this->post('/pricing/billing-cycle', ['cycle' => 'annual']);

        $response->assertNoContent();
        $response->assertCookie(BillingCyclePreference::COOKIE_KEY, 'annual');
    }

    public function test_remember_cycle_endpoint_rejects_garbage(): void
    {
        $response = $this->postJson('/pricing/billing-cycle', ['cycle' => 'lifetime']);
        $response->assertStatus(422);
    }

    public function test_pricing_page_then_repeat_visit_keeps_choice_via_cookie(): void
    {
        $this->seedPlanCatalogue();

        // Step 1: visitor lands on /pricing?cycle=annual. The page
        // queues the cookie onto the response.
        $first = $this->get('/pricing?cycle=annual');
        $first->assertOk();
        $first->assertCookie(BillingCyclePreference::COOKIE_KEY, 'annual');

        // Step 2: visitor returns days later — fresh session, no
        // query string, just the long-lived cookie. The cycle should
        // still be annual.
        $second = $this->withCookies([
            BillingCyclePreference::COOKIE_KEY => 'annual',
        ])->get('/pricing');
        $second->assertOk();
        $second->assertSee("cycle: 'annual'", false);
    }
}
