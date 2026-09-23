<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Default starter" templates, for a Link in Bio that has just been created.
 *
 * A new biolink used to land on a completely empty canvas: "No blocks yet",
 * and a public page reading "This Link in Bio page is being set up." Nothing
 * showed a new user what a page is even made of.
 *
 * One number decides whether a template is also a starter:
 *
 *   0  (the default) template appears in the gallery only, exactly as today.
 *   >0 template is ALSO a candidate for new pages, and this is its weight in
 *      the weighted random draw -- a 3 is picked three times as often as a 1.
 *
 * A column rather than a new table on purpose: the admin Templates screen
 * already knows how to build a snapshot from a live link, validate it,
 * thumbnail it and apply it. A separate "starter kits" table would have
 * duplicated all of that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_templates', function (Blueprint $table) {
            $table->unsignedSmallInteger('starter_weight')->default(0)->after('sort_order');
        });

        // Partial index: the starter draw only ever asks for rows above zero,
        // and on a mature library that is a handful out of hundreds.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement(
                'CREATE INDEX page_templates_starter_idx ON page_templates (starter_weight) WHERE starter_weight > 0'
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS page_templates_starter_idx');
        }

        Schema::table('page_templates', function (Blueprint $table) {
            $table->dropColumn('starter_weight');
        });
    }
};
