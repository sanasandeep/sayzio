<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPricingGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_groups_and_expanded_groups_use_the_same_catalogue(): void
    {
        $response = $this->get('/pricing')->assertOk();
        $this->assertSame(['essentials'], array_column($response->viewData('planBands'), 'key'));
        // The paid three are now chosen by the visitor's roll rather than
        // hardcoded, so this pins the shape instead of the slugs: free first,
        // then three plans drawn only from the bands that are on the pricing
        // page. Teams and developers sit behind the door and must never
        // appear here.
        $shown = $response->viewData('plans')->pluck('model.slug')->all();
        $this->assertSame('free', $shown[0], 'the free plan must lead the set');
        $frontSlugs = collect(PlansAndAddonsSeeder::publicPlanGroups())
            ->filter(fn (array $def) => ! empty($def['on_pricing']))
            ->pluck('slugs')
            ->flatten()
            ->all();
        $this->assertSame(
            [],
            array_diff($shown, $frontSlugs),
            'the default set included a plan from a band that lives behind the door',
        );
        $this->assertCount(4, $response->viewData('planModels'));
        $this->assertCount(5, $response->viewData('doorBands')[0]['rows']);
        $response->assertDontSee('See all plans', false);
        $response->assertDontSee('Show me another set', false);

        // Cheapest first, free included, so the row always reads as a ladder
        // whichever categories the roll chose.
        $amounts = $response->viewData('plans')
            ->map(fn (array $row) => (int) ($row['prices']['USD']['monthly']['amount_minor'] ?? 0))
            ->all();
        $sorted = $amounts;
        sort($sorted);
        $this->assertSame($sorted, $amounts, 'the plan cards are not in ascending price order');
        $all = $this->get('/pricing?all=1')->assertOk();
        $this->assertSame(['bio', 'platform', 'teams', 'devs'], array_column($all->viewData('allPlanBands'), 'key'));
        $all->assertSee('Curate with Zio');
    }

    /**
     * Pressing Curate is the visitor asking to see more, so it opens the page
     * out to one plan from every category rather than handing back another
     * three. Pressing again keeps it open and rolls the variants.
     */
    public function test_curating_opens_the_page_out_to_one_plan_per_category(): void
    {
        $short = $this->get('/pricing')->assertOk();
        $this->assertCount(4, $short->viewData('plans'), 'the first view is Free plus three');
        $this->assertEmpty($short->viewData('planGrid'), 'the short view is a rail, not a grid');

        $this->get('/pricing/another')->assertRedirect();
        $curated = $this->get('/pricing')->assertOk();

        $categories = $curated->viewData('plans')
            ->map(fn (array $row) => $row['model']->categoryKey())
            ->all();

        $this->assertSame(
            $categories,
            array_values(array_unique($categories)),
            'the curated grid showed two plans from the same category',
        );
        $this->assertGreaterThan(
            4,
            count($categories),
            'curating returned no more plans than the short view did',
        );
        $this->assertNotEmpty($curated->viewData('planGrid'), 'the curated view should render as a grid');

        $amounts = $curated->viewData('plans')
            ->map(fn (array $row) => (int) ($row['prices']['USD']['monthly']['amount_minor'] ?? 0))
            ->all();
        $sorted = $amounts;
        sort($sorted);
        $this->assertSame($sorted, $amounts, 'the curated grid is not in ascending price order');
    }

    public function test_session_matcher_only_receives_public_plan_features(): void
    {
        $response = $this->get('/pricing')->assertOk();
        $catalogue = $response->viewData('curationCatalogue');
        $this->assertNotContains('unlimited', $catalogue->pluck('slug')->all());
        $this->assertSame('Bio', $catalogue->firstWhere('slug', 'bio-lite')['name']);
        $this->assertSame('Free', $catalogue->firstWhere('slug', 'free')['name']);
        foreach ($catalogue as $plan) {
            $this->assertSame([], array_diff(array_keys($plan['features']), [
                'max_biolinks', 'max_links', 'max_seats_per_workspace', 'custom_domains', 'paid_forms', 'api_access',
            ]));
        }
        $response->assertSee('No AI call or coins. Saved only in this tab session.');
    }

    public function test_audit_detects_saved_admin_drift_without_modifying_it(): void
    {
        $this->artisan('plans:audit-public')->assertSuccessful();
        $plan = Plan::where('slug', 'professional')->firstOrFail();
        $features = $plan->features;
        $features['module_domains'] = false;
        $plan->update(['features' => $features]);
        $this->artisan('plans:audit-public')->expectsOutputToContain('module_domains')->assertFailed();
        $this->assertFalse($plan->fresh()->features['module_domains']);
    }

    public function test_alignment_is_idempotent_and_preserves_prices_and_other_plans(): void
    {
        $creator = Plan::where('slug', 'creator')->firstOrFail();
        $business = Plan::where('slug', 'business')->firstOrFail();
        $before = $business->getAttributes();
        $features = $creator->features;
        $features['max_biolinks'] = 3;
        $features['custom_key'] = 'preserved';
        $creator->update(['features' => $features]);
        $price = $creator->monthly_price;
        $migration = require database_path('migrations/2029_10_16_000001_align_public_plan_ladder.php');
        $migration->up();
        $migration->up();
        $creator->refresh();
        $this->assertSame(25, $creator->features['max_biolinks']);
        $this->assertSame('preserved', $creator->features['custom_key']);
        $this->assertSame($price, $creator->monthly_price);
        $this->assertSame($before, $business->fresh()->getAttributes());
    }

    public function test_new_plan_definitions_match_the_canonical_ladder(): void
    {
        $seeder = new PlansAndAddonsSeeder();
        $method = new \ReflectionMethod($seeder, 'planDefinitions');
        $definitions = collect($method->invoke($seeder))->keyBy('slug');
        foreach (PlansAndAddonsSeeder::publicPlanLadder() as $slug => $features) {
            foreach ($features as $key => $value) {
                $this->assertSame($value, $definitions[$slug]['features'][$key], "$slug.$key");
            }
        }
    }

    public function test_description_repair_removes_legacy_claims_but_preserves_custom_copy(): void
    {
        $plan = Plan::where('slug', 'bio-plus')->firstOrFail();
        $plan->update(['description' => 'A power-user Link in Bio plan with custom domains, team collaboration, and expanded AI tools — for creators in India ready to grow beyond the basics.']);
        $migration = require database_path('migrations/2029_10_16_000002_correct_bio_plan_descriptions.php');
        $migration->up();
        $this->assertStringNotContainsString('team collaboration', $plan->fresh()->description);
        $plan->update(['description' => 'Our custom plan description']);
        $migration->up();
        $this->assertSame('Our custom plan description', $plan->fresh()->description);
    }
}
