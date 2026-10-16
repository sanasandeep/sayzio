<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\PlanSetRoller;
use App\Modules\Common\Support\PlanVariantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The visitor's plan set: Free plus three paid plans, one per category.
 *
 * The contract is narrow and worth stating: a refresh must never change the
 * price, and pressing "show me another set" must. Everything derives from one
 * integer, the roll, so those two are the same mechanism seen from either
 * side.
 */
class PlanSetRollTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_roll_always_produces_the_same_set(): void
    {
        $this->variants('rolling-cat', 6);

        $roller = new PlanSetRoller(request());
        $first = $roller->variantIndex('rolling-cat', 6, 4);
        $again = $roller->variantIndex('rolling-cat', 6, 4);

        $this->assertSame($first, $again, 'the same roll gave two different variants');
    }

    public function test_advancing_the_roll_changes_the_variant(): void
    {
        $this->variants('rolling-cat', 6);
        $roller = new PlanSetRoller(request());

        $seen = [];
        for ($roll = 0; $roll < 6; $roll++) {
            $seen[] = $roller->variantIndex('rolling-cat', 6, $roll);
        }

        $this->assertGreaterThan(
            2,
            count(array_unique($seen)),
            'six rolls produced almost the same variant every time, so "another set" would not feel like one',
        );
    }

    public function test_a_passive_visit_never_moves_a_price(): void
    {
        $plans = $this->variants('still-cat', 4);

        $resolve = fn () => (new PlanVariantResolver(request()))
            ->representatives(Plan::whereIn('id', $plans)->get())
            ->firstWhere('plan_category', 'still-cat');

        $first = $resolve();
        $second = $resolve();
        $third = $resolve();

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id, 'refreshing re-priced a visitor who had not asked for anything');
    }

    public function test_the_set_rotates_rather_than_repeating_the_same_categories(): void
    {
        $roller = new PlanSetRoller(request());
        $all = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'];

        $firstSet = $roller->categories($all, 0);
        $secondSet = $roller->categories($all, 1);

        $this->assertCount(3, $firstSet);
        $this->assertCount(3, $secondSet);
        $this->assertSame([], array_intersect($firstSet, $secondSet),
            'the second set repeated a category from the first, so clicking felt like nothing happened');
    }

    public function test_a_category_with_one_plan_cannot_be_rolled(): void
    {
        $roller = new PlanSetRoller(request());

        foreach ([0, 1, 7, 99] as $roll) {
            $this->assertSame(0, $roller->variantIndex('solo', 1, $roll));
        }
    }

    /** @return list<int> */
    private function variants(string $category, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = Plan::create([
                'name' => ucfirst($category)." {$i}",
                'slug' => "{$category}-{$i}",
                'plan_category' => $category,
                'is_category_default' => $i === 0,
                'description' => 'Variant fixture.',
                'monthly_price' => 9 + $i,
                'annual_price' => 90 + $i,
                'trial_days' => 0,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => $i,
                'features' => [],
            ])->id;
        }

        return $ids;
    }
}
