<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-categories for both menu types.
 *
 * Sana, 2026-09-23: "cats and sub cats", pointing at his own printed
 * Priyumm Tiffins card -- where "Tiffins" is a heading and "Idli / Dosa /
 * Vada" sit under it, which is how every real menu card in the world is
 * organised and which this product could not express at all.
 *
 * One nullable self-reference is the whole change. A category with a null
 * `parent_id` is a section; one with a parent is a sub-section inside it.
 * Nesting is capped at ONE level in the controllers, not here: a menu card
 * with three levels of indent is not a menu card any more, and the cap is a
 * product decision that belongs where the product decisions are.
 *
 * Additive and nullable, so every existing category stays exactly what it
 * is today -- a top-level section -- with no backfill.
 */
return new class extends Migration
{
    /** table => the column the new one is placed after. */
    private const TABLES = [
        'restaurant_menu_categories' => 'menu_id',
        'store_categories'           => 'menu_id',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $after) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'parent_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($after) {
                $t->unsignedBigInteger('parent_id')->nullable()->after($after)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'parent_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('parent_id');
            });
        }
    }
};
