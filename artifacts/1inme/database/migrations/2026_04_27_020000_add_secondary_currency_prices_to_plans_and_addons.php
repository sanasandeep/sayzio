<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the second-currency price columns to plans and addons.
 *
 * The active currency wiring (which currency a given customer sees,
 * country-based selection, etc.) lands in Task #191. This migration
 * just makes sure the columns exist now so downstream tasks don't
 * have to break the schema again.
 *
 * Conventions:
 * - `monthly_price` / `annual_price` continue to hold the primary
 *   (USD) base price.
 * - `monthly_price_secondary` / `annual_price_secondary` hold the
 *   second-currency price (will be INR by default in #191).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['plans', 'addons'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                if (!Schema::hasColumn($blueprint->getTable(), 'monthly_price_secondary')) {
                    $blueprint->decimal('monthly_price_secondary', 12, 2)->nullable()->after('annual_price');
                }
                if (!Schema::hasColumn($blueprint->getTable(), 'annual_price_secondary')) {
                    $blueprint->decimal('annual_price_secondary', 12, 2)->nullable()->after('monthly_price_secondary');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['plans', 'addons'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                foreach (['monthly_price_secondary', 'annual_price_secondary'] as $col) {
                    if (Schema::hasColumn($blueprint->getTable(), $col)) {
                        $blueprint->dropColumn($col);
                    }
                }
            });
        }
    }
};
