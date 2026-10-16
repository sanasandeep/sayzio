<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan categories: a bucket of near-identical plans that compete for one slot
 * on the pricing page.
 *
 * A category holds several variants that differ only slightly in name, price
 * and minor features. A visitor is shown exactly ONE plan per category and
 * never learns the siblings exist, so the public page stays short while the
 * catalogue behind it grows.
 *
 * - `plan_category` is ADMIN-ONLY. It must never be rendered on a public
 *   surface: it is the name of the experiment, not a thing a customer buys.
 *   Null means "this plan is its own category", which is why every existing
 *   plan keeps working untouched — see Plan::categoryKey().
 * - `is_category_default` marks the variant new visitors see. Exactly one per
 *   category; PlanCategoryIntegrityTest enforces it.
 *
 * Changing the default does NOT re-price anyone who has already seen a
 * variant — plan_variant_pins holds that, created in the next migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'plan_category')) {
                $table->string('plan_category', 64)->nullable()->after('slug');
            }
            if (!Schema::hasColumn('plans', 'is_category_default')) {
                $table->boolean('is_category_default')->default(false)->after('plan_category');
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'plan_category')) {
                return;
            }
            $indexes = collect(Schema::getIndexes('plans'))->pluck('name')->all();
            if (!in_array('plans_plan_category_index', $indexes, true)) {
                $table->index('plan_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            foreach (['is_category_default', 'plan_category'] as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
