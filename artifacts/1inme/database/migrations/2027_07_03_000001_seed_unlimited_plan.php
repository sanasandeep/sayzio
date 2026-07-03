<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, one-time data migration that seeds the internal "Unlimited"
 * comp plan (slug `unlimited`) into the shared production database.
 *
 * Idempotent: PlansAndAddonsSeeder::seedPlansBySlug() upserts by slug and
 * never touches any other plan row (curator edits to existing plans are
 * preserved), so this is safe to re-run after a killed/partial migrate.
 *
 * Forward-only: down() intentionally does nothing so we never destroy a
 * plan that may already have users assigned to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans') || !Schema::hasColumn('plans', 'is_internal')) {
            return;
        }

        (new PlansAndAddonsSeeder())->seedPlansBySlug(['unlimited']);
    }

    public function down(): void
    {
        // Forward-only: never delete a plan that may have users assigned.
    }
};
