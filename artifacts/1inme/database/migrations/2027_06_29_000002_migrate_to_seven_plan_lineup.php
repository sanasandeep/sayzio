<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time data migration that flips the platform from the legacy 5-plan
 * lineup (Free / Starter / Pro / Premium-business / Enterprise) to the new
 * 7-plan lineup (Starter / Creator / Professional / Business / Agency /
 * Developer / Enterprise API).
 *
 * Strategy (idempotent — safe to re-run after a killed migrate over the
 * shared RDS; see the migrate-orphans memo):
 *  1. Converge the new lineup by running the idempotent PlansAndAddonsSeeder.
 *     It repurposes the historical `free` row into the free "Starter" default
 *     and the `business` row into the new Business plan IN PLACE (so existing
 *     free and Premium-business subscribers keep the same plan_id — that is
 *     their remap), and inserts the genuinely-new rows (creator, professional,
 *     agency, developer, enterprise-api).
 *  2. Remap subscribers off the legacy-only plans onto their closest new plan:
 *        starter(old paid) -> creator
 *        pro               -> professional
 *        enterprise        -> enterprise-api
 *     (users.plan_id + subscriptions.plan_id), then archive each legacy row.
 *  3. Enforce a single is_default (the free Starter plan) so default-plan
 *     resolution (Plan::defaultPlan()) is unambiguous.
 *  4. Backfill the Starter 1-year free window for everyone already sitting on
 *     the default plan so the yearly re-confirmation reminder has a deadline.
 *
 * This is a forward-only data migration; down() is intentionally a no-op
 * (we never destroy the new plans or un-remap subscribers on rollback).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        // 1. Ensure the 7 plan rows (+ their USD/INR prices) exist so we have
        //    ids to remap subscribers onto. We deliberately call only the
        //    fast plan pass here — the slower addon-catalog convergence is left
        //    to the full idempotent seeder (run from post-merge / deploy),
        //    since over the distant RDS the full seeder takes ~2 min, which is
        //    too long to hold inside a migration.
        (new PlansAndAddonsSeeder())->seedPlans();

        // Resolve the canonical new-plan ids by slug.
        $idBySlug = DB::table('plans')->pluck('id', 'slug');

        $freeId = $idBySlug['free'] ?? null;

        // 2. Remap legacy-only plans to their closest new plan, then archive.
        $remap = [
            'starter'    => 'creator',
            'pro'        => 'professional',
            'enterprise' => 'enterprise-api',
        ];

        foreach ($remap as $legacySlug => $newSlug) {
            $legacyId = $idBySlug[$legacySlug] ?? null;
            $newId    = $idBySlug[$newSlug] ?? null;
            if (!$legacyId || !$newId || $legacyId === $newId) {
                continue;
            }

            DB::table('users')->where('plan_id', $legacyId)->update(['plan_id' => $newId]);

            if (Schema::hasTable('subscriptions')) {
                DB::table('subscriptions')->where('plan_id', $legacyId)->update(['plan_id' => $newId]);
            }

            DB::table('plans')->where('id', $legacyId)->update([
                'is_archived' => true,
                'status'      => 'inactive',
                'is_default'  => false,
                'updated_at'  => now(),
            ]);
        }

        // 3. Enforce a single default plan (free Starter). The seeder already
        //    marks it default; here we make sure no archived legacy plan still
        //    carries the flag.
        if ($freeId) {
            DB::table('plans')->where('id', '!=', $freeId)->where('is_default', true)
                ->update(['is_default' => false, 'updated_at' => now()]);
            DB::table('plans')->where('id', $freeId)->update([
                'is_default'  => true,
                'is_archived' => false,
                'status'      => 'active',
                'updated_at'  => now(),
            ]);
        }

        // 3b. Force the canonical lineup labels + ordering on the seven active
        //     plans. The recurring overlay seeder deliberately never overwrites
        //     an existing plan name (to preserve curator edits), but this
        //     one-time lineup flip repurposes the legacy `free` (was "Free")
        //     and `business` (was "Premium") rows in place — so the migration
        //     itself must rename them deterministically and fix their display
        //     order, otherwise /admin/plans would still show the old labels.
        //     Pricing/features stay whatever the seeder/curator set.
        $canonical = [
            'free'           => ['name' => 'Starter',        'sort_order' => 0],
            'creator'        => ['name' => 'Creator',        'sort_order' => 1],
            'professional'   => ['name' => 'Professional',   'sort_order' => 2],
            'business'       => ['name' => 'Business',        'sort_order' => 3],
            'agency'         => ['name' => 'Agency',          'sort_order' => 4],
            'developer'      => ['name' => 'Developer',       'sort_order' => 5],
            'enterprise-api' => ['name' => 'Enterprise API',  'sort_order' => 6],
        ];
        foreach ($canonical as $slug => $attrs) {
            DB::table('plans')->where('slug', $slug)->update(array_merge($attrs, [
                'is_archived' => false,
                'status'      => 'active',
                'updated_at'  => now(),
            ]));
        }

        // 4. Backfill the Starter 1-year free window for current default-plan
        //    users (reminder deadline only — never a lockout).
        if ($freeId && Schema::hasColumn('users', 'starter_free_window_ends_at')) {
            DB::table('users')
                ->where(function ($q) use ($freeId) {
                    $q->where('plan_id', $freeId)->orWhereNull('plan_id');
                })
                ->whereNull('starter_free_window_ends_at')
                ->update(['starter_free_window_ends_at' => now()->addYear()]);
        }
    }

    public function down(): void
    {
        // Forward-only: rolling back would re-orphan subscribers and destroy
        // the new lineup. No-op on purpose.
    }
};
