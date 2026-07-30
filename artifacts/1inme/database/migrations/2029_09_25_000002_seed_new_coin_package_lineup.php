<?php

use Database\Seeders\CoinPackagesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: roll out the 8-tier coin package lineup (Starter …
 * Ultimate) by running the CoinPackagesSeeder. Additive-only and
 * idempotent — the seeder archives legacy packs, upserts the new lineup,
 * and only backfills best_for / api_budget_pct when null, so re-running
 * (or racing another environment) is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('coin_packages') || !Schema::hasColumn('coin_packages', 'api_budget_pct')) {
            return; // Schema migration not applied yet in this environment.
        }
        (new CoinPackagesSeeder())->run();
    }

    public function down(): void
    {
        // Data migration — intentionally irreversible.
    }
};
