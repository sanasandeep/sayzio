<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Exercises the one-time 7-plan lineup data migration against real
 * subscriber rows.
 *
 * RefreshDatabase already runs every migration (including the seven-plan
 * one) on a fresh DB with zero users, so the remap logic is never touched
 * by data there. This test deliberately tears the plans/users/subscriptions
 * tables back down to the LEGACY 5-plan world (free / starter / pro /
 * business / enterprise + sample subscribers), then re-invokes the
 * migration's up() and asserts every subscriber lands on the correct new
 * plan, exactly one plan keeps is_default, the legacy-only plans are
 * archived, and the Starter free window is backfilled.
 */
class SevenPlanLineupMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** Old 5-plan lineup keyed by slug => display name. */
    private const LEGACY = [
        'free'       => 'Free',
        'starter'    => 'Starter',   // the old PAID starter tier
        'pro'        => 'Pro',
        'business'   => 'Premium',
        'enterprise' => 'Enterprise',
    ];

    /**
     * Rebuild the legacy 5-plan world from scratch and return the legacy
     * slug => plan id map.
     *
     * @return array<string,int>
     */
    private function seedLegacyWorld(): array
    {
        // Wipe everything the migration touches. Order matters for FKs:
        // subscriptions -> users -> plans.
        DB::table('subscriptions')->delete();
        DB::table('users')->delete();
        DB::table('plans')->delete();

        $now = now();
        $legacyIds = [];
        $sort = 0;
        foreach (self::LEGACY as $slug => $name) {
            $legacyIds[$slug] = (int) DB::table('plans')->insertGetId([
                'name'        => $name,
                'slug'        => $slug,
                'monthly_price' => $slug === 'free' ? 0 : 9,
                'annual_price'  => $slug === 'free' ? 0 : 90,
                'is_default'  => $slug === 'free',
                'is_archived' => false,
                'status'      => 'active',
                'sort_order'  => $sort++,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        return $legacyIds;
    }

    /** Create a user sitting on the given plan id and return the user id. */
    private function makeUser(string $email, ?int $planId): int
    {
        $now = now();

        return (int) DB::table('users')->insertGetId([
            'name'       => $email,
            'email'      => $email,
            'password'   => bcrypt('secret'),
            'status'     => 'active',
            'plan_id'    => $planId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Create an active subscription for the user on the given plan. */
    private function makeSubscription(int $userId, int $planId): int
    {
        $now = now();

        return (int) DB::table('subscriptions')->insertGetId([
            'user_id'    => $userId,
            'plan_id'    => $planId,
            'status'     => 'active',
            'billing_cycle' => 'monthly',
            'currency'   => 'USD',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Re-run the seven-plan data migration's up() against current data. */
    private function runLineupMigration(): void
    {
        $migration = require database_path(
            'migrations/2027_06_29_000002_migrate_to_seven_plan_lineup.php'
        );
        $migration->up();
    }

    public function test_existing_subscribers_remap_to_the_correct_new_plan(): void
    {
        $legacy = $this->seedLegacyWorld();

        // One user (+ active subscription on the paid tiers) per legacy plan.
        $freeUser = $this->makeUser('free@ex.com', $legacy['free']);

        $starterUser = $this->makeUser('starter@ex.com', $legacy['starter']);
        $starterSub  = $this->makeSubscription($starterUser, $legacy['starter']);

        $proUser = $this->makeUser('pro@ex.com', $legacy['pro']);
        $proSub  = $this->makeSubscription($proUser, $legacy['pro']);

        $businessUser = $this->makeUser('business@ex.com', $legacy['business']);
        $businessSub  = $this->makeSubscription($businessUser, $legacy['business']);

        $enterpriseUser = $this->makeUser('enterprise@ex.com', $legacy['enterprise']);
        $enterpriseSub  = $this->makeSubscription($enterpriseUser, $legacy['enterprise']);

        $this->runLineupMigration();

        // Resolve the canonical new-plan ids by slug.
        $idBySlug = DB::table('plans')->pluck('id', 'slug');

        // free is repurposed in place -> the free user keeps the same plan_id.
        $this->assertSame(
            $legacy['free'],
            (int) DB::table('users')->where('id', $freeUser)->value('plan_id'),
            'Free subscriber should keep the repurposed Starter (free) plan id.'
        );

        // business is repurposed in place -> the business user keeps its id.
        $this->assertSame(
            $legacy['business'],
            (int) DB::table('users')->where('id', $businessUser)->value('plan_id'),
            'Business subscriber should keep the repurposed Business plan id.'
        );

        // The genuinely-remapped tiers move to the closest new plan.
        $expected = [
            $starterUser    => 'creator',
            $proUser        => 'professional',
            $enterpriseUser => 'enterprise-api',
        ];
        foreach ($expected as $userId => $newSlug) {
            $this->assertSame(
                (int) $idBySlug[$newSlug],
                (int) DB::table('users')->where('id', $userId)->value('plan_id'),
                "User should be remapped to the {$newSlug} plan."
            );
        }

        // Active subscriptions follow the same remap.
        $this->assertSame(
            (int) $idBySlug['creator'],
            (int) DB::table('subscriptions')->where('id', $starterSub)->value('plan_id')
        );
        $this->assertSame(
            (int) $idBySlug['professional'],
            (int) DB::table('subscriptions')->where('id', $proSub)->value('plan_id')
        );
        $this->assertSame(
            (int) $idBySlug['enterprise-api'],
            (int) DB::table('subscriptions')->where('id', $enterpriseSub)->value('plan_id')
        );

        // The in-place tiers' subscriptions are untouched.
        $this->assertSame(
            $legacy['business'],
            (int) DB::table('subscriptions')->where('id', $businessSub)->value('plan_id')
        );
    }

    public function test_exactly_one_plan_keeps_is_default_and_it_is_the_free_starter(): void
    {
        $legacy = $this->seedLegacyWorld();

        // Add a second stray is_default flag on a legacy paid plan to prove
        // the migration collapses it back to a single default.
        DB::table('plans')->where('id', $legacy['pro'])->update(['is_default' => true]);

        $this->runLineupMigration();

        $defaults = DB::table('plans')->where('is_default', true)->get();
        $this->assertCount(1, $defaults, 'Exactly one plan must remain is_default.');

        $default = $defaults->first();
        $this->assertSame('free', $default->slug, 'The default must be the free Starter plan.');
        $this->assertFalse((bool) $default->is_archived, 'The default plan must not be archived.');
        $this->assertSame('active', $default->status);
    }

    public function test_legacy_only_plans_are_archived_and_relabelled_lineup_is_active(): void
    {
        $legacy = $this->seedLegacyWorld();

        $this->runLineupMigration();

        // The legacy-only tiers (starter/pro/enterprise) are archived + inactive.
        foreach (['starter', 'pro', 'enterprise'] as $legacySlug) {
            $row = DB::table('plans')->where('id', $legacy[$legacySlug])->first();
            $this->assertNotNull($row);
            $this->assertTrue((bool) $row->is_archived, "Legacy {$legacySlug} must be archived.");
            $this->assertSame('inactive', $row->status, "Legacy {$legacySlug} must be inactive.");
            $this->assertFalse((bool) $row->is_default);
        }

        // The seven canonical plans exist, are active, and carry the new labels.
        $canonical = [
            'free'           => 'Starter',
            'creator'        => 'Creator',
            'professional'   => 'Professional',
            'business'       => 'Business',
            'agency'         => 'Agency',
            'developer'      => 'Developer',
            'enterprise-api' => 'Enterprise API',
        ];
        foreach ($canonical as $slug => $name) {
            $row = DB::table('plans')->where('slug', $slug)->first();
            $this->assertNotNull($row, "Canonical plan {$slug} should exist.");
            $this->assertSame($name, $row->name, "Plan {$slug} should be relabelled to {$name}.");
            $this->assertFalse((bool) $row->is_archived, "Canonical plan {$slug} must stay active.");
            $this->assertSame('active', $row->status);
        }
    }

    public function test_starter_free_window_is_backfilled_for_default_plan_users(): void
    {
        $legacy = $this->seedLegacyWorld();

        $freeUser = $this->makeUser('free@ex.com', $legacy['free']);
        $nullPlanUser = $this->makeUser('nullplan@ex.com', null);
        $proUser = $this->makeUser('pro@ex.com', $legacy['pro']);

        // Sanity: nobody has a free window before the migration.
        $this->assertNull(
            DB::table('users')->where('id', $freeUser)->value('starter_free_window_ends_at')
        );

        $this->runLineupMigration();

        // Free-plan and null-plan users get a backfilled window deadline.
        $this->assertNotNull(
            DB::table('users')->where('id', $freeUser)->value('starter_free_window_ends_at'),
            'Free subscriber should get a backfilled Starter free window.'
        );
        $this->assertNotNull(
            DB::table('users')->where('id', $nullPlanUser)->value('starter_free_window_ends_at'),
            'Null-plan subscriber should also be treated as on the default plan.'
        );

        // A user remapped onto a paid plan (pro -> professional) is NOT given a
        // free window.
        $this->assertNull(
            DB::table('users')->where('id', $proUser)->value('starter_free_window_ends_at'),
            'Paid-plan subscriber should not get a Starter free window.'
        );
    }
}
