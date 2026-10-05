<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A section can carry an icon.
 *
 * Sana, 2026-10-05: "section headings should have numbers default, option
 * with selecting icons also" and, for the jump bar, "verticle tab with
 * icon display or number".
 *
 * Both halves need the same thing: somewhere to keep the icon a creator
 * picked for a section. Numbers are free -- a section's position IS its
 * number -- but an icon is a choice, and a choice has to be stored.
 *
 * Nullable, because every menu that exists has no icons and must keep
 * rendering exactly as it does today. An unset icon falls back to the
 * number, which is the default he asked for.
 */
return new class extends Migration
{
    /** table => the column the new one is placed after. */
    private const TABLES = [
        'restaurant_menu_categories' => 'name',
        'store_categories'           => 'name',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $after) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'icon')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($after) {
                // Short on purpose: this holds a catalogue KEY, not a class
                // name and not a path. A column wide enough for arbitrary
                // markup is an invitation to store some.
                $t->string('icon', 40)->nullable()->after($after);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'icon')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('icon');
            });
        }
    }
};
