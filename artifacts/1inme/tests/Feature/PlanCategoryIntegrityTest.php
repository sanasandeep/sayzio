<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\PlanVariantResolver;
use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Plan categories: a bucket of near-identical variants competing for one slot
 * on /pricing. The visitor sees one and never learns the siblings exist.
 *
 * Three things have to hold or the scheme stops being honest rather than
 * merely stopping being tidy:
 *
 *  - exactly one default per category, or which variant a new visitor gets
 *    depends on row order;
 *  - a category cannot straddle two pricing bands, or the same bucket shows
 *    up twice on the page under different headings;
 *  - a visitor who has been quoted a price keeps it, even after the default
 *    moves. That is the whole point of the pin.
 */
class PlanCategoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_category_has_exactly_one_default(): void
    {
        $offenders = Plan::query()
            ->where('is_archived', false)
            ->whereNotNull('plan_category')
            ->where('plan_category', '!=', '')
            ->get(['id', 'slug', 'plan_category', 'is_category_default'])
            ->groupBy('plan_category')
            ->map(fn ($variants) => $variants->where('is_category_default', true)->count())
            ->reject(fn (int $defaults) => $defaults === 1)
            ->map(fn (int $defaults, string $category) => "{$category}: {$defaults} defaults")
            ->values()
            ->all();

        $this->assertSame([], $offenders, sprintf(
            "Every plan category needs exactly one plan flagged is_category_default:\n\n  %s\n\n"
            ."With none, which variant a new visitor is quoted depends on row order. With more\n"
            ."than one, it depends on which row the database hands back first.\n",
            implode("\n  ", $offenders),
        ));
    }

    public function test_a_category_never_straddles_two_pricing_bands(): void
    {
        $bandOfSlug = [];
        foreach (PlansAndAddonsSeeder::publicPlanGroups() as $band => $def) {
            foreach ($def['slugs'] as $slug) {
                $bandOfSlug[$slug] = $band;
            }
        }

        $straddling = [];
        foreach (Plan::query()->where('is_archived', false)->get() as $plan) {
            $band = $bandOfSlug[$plan->slug] ?? null;
            if ($band === null) {
                continue;
            }
            $straddling[$plan->categoryKey()][$band] = true;
        }

        $broken = collect($straddling)
            ->filter(fn (array $bands) => count($bands) > 1)
            ->map(fn (array $bands, string $cat) => $cat.' is in '.implode(' and ', array_keys($bands)))
            ->values()
            ->all();

        $this->assertSame([], $broken, sprintf(
            "A plan category must live in exactly one band, or the same bucket renders twice\n"
            ."under two headings:\n\n  %s\n",
            implode("\n  ", $broken),
        ));
    }

    public function test_a_visitor_keeps_the_variant_they_were_first_shown(): void
    {
        $first = $this->variant('cat-a-one', 'pricing-test', true, 1);
        $second = $this->variant('cat-a-two', 'pricing-test', false, 2);

        $resolver = fn () => (new PlanVariantResolver(request()))
            ->representatives(Plan::whereIn('id', [$first->id, $second->id])->get());

        $shown = $resolver()->firstWhere('plan_category', 'pricing-test');
        $this->assertSame($first->id, $shown->id, 'a new visitor gets the flagged default');

        $pin = DB::table('plan_variant_pins')->where('plan_category', 'pricing-test')->first();
        $this->assertNotNull($pin, 'the first render records what the visitor was shown');
        $this->assertSame($first->id, (int) $pin->plan_id);

        // The admin now promotes the other variant. Everyone who has already
        // been quoted the first one must keep it.
        $first->forceFill(['is_category_default' => false])->save();
        $second->forceFill(['is_category_default' => true])->save();

        $stillShown = $resolver()->firstWhere('plan_category', 'pricing-test');
        $this->assertSame(
            $first->id,
            $stillShown->id,
            'moving the default re-priced a visitor who had already been quoted the old variant',
        );

        $this->assertSame(
            1,
            DB::table('plan_variant_pins')->where('plan_category', 'pricing-test')->count(),
            'a pin is written once and never rewritten',
        );
    }

    public function test_plans_without_a_category_are_each_their_own_slot(): void
    {
        $a = $this->variant('solo-a', null, false, 1);
        $b = $this->variant('solo-b', null, false, 2);

        $shown = (new PlanVariantResolver(request()))
            ->representatives(Plan::whereIn('id', [$a->id, $b->id])->get());

        $this->assertCount(2, $shown, 'uncategorised plans must not be collapsed into one slot');
        $this->assertSame(
            0,
            DB::table('plan_variant_pins')->count(),
            'a single-member category needs no pin, so an ordinary visit writes nothing',
        );
    }

    private function variant(string $slug, ?string $category, bool $default, int $order): Plan
    {
        return Plan::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'plan_category' => $category,
            'is_category_default' => $default,
            'description' => 'Variant fixture.',
            'monthly_price' => 9.00,
            'annual_price' => 90.00,
            'trial_days' => 0,
            'is_default' => false,
            'status' => 'active',
            'is_archived' => false,
            'sort_order' => $order,
            'features' => [],
        ]);
    }
}
