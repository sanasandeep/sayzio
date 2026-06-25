<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The public /pricing page surfaces each plan's allowed biolink blocks in
 * two places: the per-plan cards (the "Link in bio" feature group) and the
 * "Compare features at a glance" matrix ("Content blocks" row). Both read
 * `block_types_allowed` from the plan features and resolve slugs to friendly
 * labels through the same resolver, which is backed by
 * {@see \App\Modules\User\Models\BiolinkBlock::pickerTypes()}.
 *
 * This guards three behaviours that a future change to the plan features,
 * the block registry, or the label resolver could silently break:
 *   1. `'*'` => "All blocks" (matrix) / "All biolink blocks" (card).
 *   2. An array of slugs => the correct (de-duped) count + named list.
 *   3. Unknown / legacy slugs => a humanized label, never a raw token.
 *
 * We delete any seeded plans first so the card "delta" logic (each card only
 * shows features that improve on the previous plan) is fully controlled by
 * the ordering we set here, and so assertions can't be perturbed by the
 * lineup the data migration seeds.
 */
class PricingPageBlocksPerPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an active, public, priced plan at the given sort position with
     * the supplied `block_types_allowed` feature value.
     *
     * @param  string|array  $blocks
     */
    private function makePlan(string $name, int $sortOrder, $blocks): Plan
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
            'sort_order'    => $sortOrder,
            'features'      => ['block_types_allowed' => $blocks],
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

    public function test_pricing_page_shows_correct_blocks_per_plan(): void
    {
        $this->clearSeededPlans();

        // Plan ordering matters: the per-plan cards only repeat a feature when
        // its weight improves on the previous plan, so we keep block counts
        // strictly increasing down the lineup (2 -> 3 -> unlimited). The first
        // plan has no predecessor and therefore always renders its full set.

        // 1. Legacy / duplicate slugs. `legacy_mystery_block` is NOT in the
        //    block picker, so it must degrade to a humanized label. It also
        //    appears twice to prove de-duplication (=> 2 distinct blocks).
        $this->makePlan('Spark', 1, ['legacy_mystery_block', 'legacy_mystery_block', 'heading']);

        // 2. A named array of real, picker-backed slugs (=> 3 blocks).
        $this->makePlan('Pulse', 2, ['link', 'heading', 'markdown']);

        // 3. Wildcard => all blocks unlocked.
        $this->makePlan('Apex', 3, '*');

        $resp = $this->get('/pricing');
        $resp->assertOk();

        // --- Wildcard plan ------------------------------------------------
        $resp->assertSee('All blocks');          // matrix cell
        $resp->assertSee('All biolink blocks');  // plan card

        // --- Named array plan --------------------------------------------
        $resp->assertSee('3 blocks');            // matrix count
        $resp->assertSee('3 biolink blocks');    // card count
        // Friendly labels from the live block registry (sorted, de-duped).
        $resp->assertSee('Link Button');
        $resp->assertSee('Markdown');
        // The card + matrix render the sorted, de-duped label list.
        $resp->assertSee('Heading, Link Button, Markdown');

        // --- Legacy / duplicate plan -------------------------------------
        // Unknown slug is humanized, never shown as a raw token.
        $resp->assertSee('Legacy Mystery Block');
        $resp->assertDontSee('legacy_mystery_block');
        // De-duplication: the duplicated slug collapses to a single label and
        // the count is 2, not 3.
        $resp->assertSee('2 blocks');
        $resp->assertSee('2 biolink blocks');
        $resp->assertDontSee('Legacy Mystery Block, Legacy Mystery Block');
    }
}
