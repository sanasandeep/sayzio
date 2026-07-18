<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace the Pro-only `fa-calendar-star` icon with the Free-set equivalent
 * `fa-calendar-days` on any `event_categories` rows that were seeded or
 * created while the old default was still in place.
 *
 * The column default was corrected in the original create-table migration as
 * well, but this migration heals rows that already exist in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('event_categories')) {
            return;
        }

        DB::table('event_categories')
            ->where('icon', 'fa-calendar-star')
            ->update([
                'icon'       => 'fa-calendar-days',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('event_categories')) {
            return;
        }

        DB::table('event_categories')
            ->where('icon', 'fa-calendar-days')
            ->update([
                'icon'       => 'fa-calendar-star',
                'updated_at' => now(),
            ]);
    }
};
