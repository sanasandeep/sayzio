<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The public /premium-features page (route `site.premium-features`) renders a
 * sticky plan-by-plan comparison grid driven by
 * {@see \App\Modules\Common\Support\PremiumFeatures::catalogue()} +
 * {@see \App\Modules\Common\Support\PremiumFeatures::resolveCell()}.
 *
 * The catalogue drift-guard unit test only proves the catalogue is complete;
 * nothing exercises the actual HTTP render against real Plan rows. This boots
 * the route end-to-end so a regression in the controller, the blade, or the
 * plan shape that blanks or breaks the page is caught.
 *
 * We delete any seeded plans first so the grid is fully controlled by the
 * lineup we set here, and assertions can't be perturbed by the data-migration
 * seed.
 */
class PremiumFeaturesPageRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an active, public, priced plan at the given sort position with
     * the supplied `features` JSON.
     */
    private function makePlan(string $name, int $sortOrder, array $features, bool $popular = false): Plan
    {
        $plan = Plan::create([
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . Str::random(6),
            'description'   => $name . ' plan',
            'monthly_price' => 10.00 + $sortOrder,
            'annual_price'  => 100.00 + $sortOrder,
            'trial_days'    => 0,
            'status'        => 'active',
            'is_archived'   => false,
            'is_internal'   => false,
            'is_popular'    => $popular,
            'sort_order'    => $sortOrder,
            'features'      => $features,
        ]);

        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', (10 + $sortOrder) * 100],
            ['USD', 'annual', (100 + $sortOrder) * 100],
            ['INR', 'monthly', (10 + $sortOrder) * 100 * 80],
            ['INR', 'annual', (100 + $sortOrder) * 100 * 80],
        ]);

        return $plan;
    }

    /**
     * Start from a clean slate so the only plans on the page are the ones we
     * create here. Prices carry an FK to plans, so they go first.
     */
    private function clearSeededPlans(): void
    {
        foreach (Plan::all() as $plan) {
            $plan->prices()->delete();
        }
        Plan::query()->delete();
    }

    public function test_premium_features_page_renders_grid_for_every_plan(): void
    {
        $this->clearSeededPlans();

        // A plan with: a numeric allowance (max_links = 500), an Unlimited
        // numeric (max_biolinks = -1) and an enabled boolean (custom_domains).
        $this->makePlan('Starter', 1, [
            'max_links'      => 500,
            'max_biolinks'   => -1,
            'custom_domains' => true,
        ], popular: true);

        // A second plan so the grid genuinely renders one column per plan.
        $this->makePlan('Growth', 2, [
            'max_links'      => 5000,
            'max_biolinks'   => 10,
            'custom_domains' => false,
        ]);

        $resp = $this->get('/premium-features');
        $resp->assertOk();

        // Both plan header columns render.
        $resp->assertSee('Starter');
        $resp->assertSee('Growth');

        // A known group label from the catalogue is present.
        $resp->assertSee('Limits');

        // A known feature-row label is present.
        $resp->assertSee('Short links');
        $resp->assertSee('Link in Bio pages');

        // Numeric cell: 500 renders formatted, 5000 with a thousands separator.
        $resp->assertSee('500');
        $resp->assertSee('5,000');

        // Unlimited cell: -1 renders as "Unlimited".
        $resp->assertSee('Unlimited');

        // The "no plans" empty-state is NOT shown when plans exist.
        $resp->assertDontSee('No plans are currently published.');
    }

    public function test_premium_features_page_renders_analytics_and_alias_special_cells(): void
    {
        $this->clearSeededPlans();

        // A plan with the advanced analytics select and a per-type
        // `max_aliases_per_link` map (a non-scalar numeric branch in
        // resolveCell that renders "Custom" when any type is non-zero).
        $this->makePlan('Pro', 1, [
            'analytics'            => 'advanced',
            'max_aliases_per_link' => [
                'short'   => 5,
                'biolink' => 0,
            ],
        ], popular: true);

        // A plan with the basic analytics select and an all-zero alias map
        // (the off branch — renders "Basic" and an excluded alias cell).
        $this->makePlan('Free', 2, [
            'analytics'            => 'basic',
            'max_aliases_per_link' => [
                'short'   => 0,
                'biolink' => 0,
            ],
        ]);

        $resp = $this->get('/premium-features');
        $resp->assertOk();

        // The analytics-depth row label is present.
        $resp->assertSee('Analytics depth');

        // Assert the actual rendered cell markup, not the bare words — the
        // words "Advanced"/"Basic"/"Custom" also appear in row labels and
        // descriptions ("Advanced form analytics", "Basic plans show...",
        // "Custom domains"), so a bare assertSee would give false positives.
        // The advanced analytics select renders an emerald cell.
        $resp->assertSee('<span class="text-emerald-300 font-semibold">Advanced</span>', false);
        // The basic analytics select renders a muted (off) cell.
        $resp->assertSee('<span class="text-gray-300">Basic</span>', false);

        // The per-type alias map renders the "Custom" cell when non-zero.
        $resp->assertSee('Extra aliases per link');
        $resp->assertSee('<span class="text-white font-semibold">Custom</span>', false);
    }

    public function test_premium_features_page_renders_empty_state_with_no_plans(): void
    {
        $this->clearSeededPlans();

        $resp = $this->get('/premium-features');
        $resp->assertOk();
        $resp->assertSee('No plans are currently published.');
    }
}
