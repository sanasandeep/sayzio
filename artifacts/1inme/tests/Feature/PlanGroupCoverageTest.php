<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use Database\Seeders\PlansAndAddonsSeeder;
use Tests\TestCase;

/**
 * Every public plan has to be filed in a group on /pricing.
 *
 * Bio Lite and Bio Plus were created in the admin UI, so they were live and
 * selling while the seeder, the test database and every piece of code that
 * believed it knew the lineup had never heard of them. Grouping lives in code
 * precisely so an admin edit cannot re-shuffle the pricing page — but that
 * trade only pays if a new plan makes a noise instead of landing in the
 * fallback band forever.
 *
 * That is this test.
 */
class PlanGroupCoverageTest extends TestCase
{
    public function test_every_public_plan_is_filed_in_a_pricing_group(): void
    {
        $grouped = collect(PlansAndAddonsSeeder::publicPlanGroups())
            ->pluck('slugs')
            ->flatten()
            ->all();

        $missing = Plan::query()
            ->where('is_archived', false)
            ->where('is_internal', false)
            ->pluck('slug')
            ->reject(fn (string $slug) => in_array($slug, $grouped, true))
            ->values()
            ->all();

        $this->assertSame([], $missing, sprintf(
            "These live plans are not in any /pricing group:\n\n  %s\n\n"
            ."They will render in the catch-all \"More plans\" band until someone files\n"
            ."them in PlansAndAddonsSeeder::publicPlanGroups(). Put each one in the group\n"
            ."a buyer would look in, and set on_pricing false if it belongs behind the\n"
            ."team & developer door.\n",
            implode("\n  ", $missing),
        ));
    }

    public function test_no_group_references_a_plan_that_does_not_exist(): void
    {
        $slugs = Plan::query()->pluck('slug')->all();
        $dangling = [];

        foreach (PlansAndAddonsSeeder::publicPlanGroups() as $key => $def) {
            foreach ($def['slugs'] as $slug) {
                if (! in_array($slug, $slugs, true)) {
                    $dangling[] = "{$key} -> {$slug}";
                }
            }
        }

        $this->assertSame([], $dangling, sprintf(
            "These groups name plans that do not exist:\n\n  %s\n\n"
            ."A typo here removes a plan from /pricing without any other symptom.\n",
            implode("\n  ", $dangling),
        ));
    }

    public function test_at_least_one_group_is_behind_the_door(): void
    {
        $behind = collect(PlansAndAddonsSeeder::publicPlanGroups())
            ->reject(fn (array $def) => ! empty($def['on_pricing']))
            ->keys()
            ->all();

        $this->assertNotSame([], $behind,
            'Every group is on the front page, so the "See team & developer plans" door '
            .'renders nothing. Either mark a group on_pricing false or remove the door.',
        );
    }
}
