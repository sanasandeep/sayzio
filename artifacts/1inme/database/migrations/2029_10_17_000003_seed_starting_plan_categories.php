<?php

use App\Modules\Admin\Support\PlanCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives today's nine public plans a starting category each, so the buckets
 * exist and are ready to take variants.
 *
 * Behaviourally this changes nothing: a category with one member resolves to
 * that member, which is what already happened when plan_category was null.
 * What it buys is a place to put the second variant, and a plan index that
 * can group by something real.
 *
 * The names are suggestions (PlanCategories::suggested()) and the column is
 * free text — rename any of them in admin whenever you like.
 *
 * Only fills a category that is NOT already set, so re-running it cannot
 * overwrite a curator's naming. Each plan it touches also becomes its
 * category's default, which PlanCategoryIntegrityTest requires.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans') || !Schema::hasColumn('plans', 'plan_category')) {
            return;
        }

        foreach (PlanCategories::suggested() as $category => $def) {
            DB::table('plans')
                ->where('slug', $def['slug'])
                ->where(function ($q) {
                    $q->whereNull('plan_category')->orWhere('plan_category', '');
                })
                ->update([
                    'plan_category' => $category,
                    'is_category_default' => true,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Forward-only. Clearing categories would orphan any variant added
        // since, and the column going back to null is indistinguishable from
        // a plan that was never categorised.
    }
};
