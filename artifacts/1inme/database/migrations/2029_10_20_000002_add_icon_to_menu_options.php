<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An icon on a choice, and how many times it is drawn.
 *
 * Sana, 2026-09-28: "it should represent with icons.. like multiple chilis
 * for spicy.. 1 to 3 / hot and cold".
 *
 * ---- Why a key and a count, not an image -------------------------------
 *
 * `icon` holds a key out of MenuOptionIcon's catalogue -- 'flame',
 * 'snowflake' -- and the drawing lives in the code. An uploaded image
 * would have to read at 15px, on a light sheet and a dark one, beside two
 * others of the same shape, and there is no screen on which the owner
 * could find out it does not.
 *
 * `icon_repeat` is what makes "Mild / Medium / Hot" one icon at one, two
 * and three rather than three drawings that have to stay in the same
 * family. Capped at MenuOptionIcon::MAX_REPEAT, and held at 1 when there
 * is no icon so the column never carries a count for nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_options')) {
            return;
        }

        Schema::table('menu_options', function (Blueprint $t) {
            if (! Schema::hasColumn('menu_options', 'icon')) {
                // Short on purpose: these are catalogue keys, not paths.
                $t->string('icon', 24)->nullable()->after('name');
            }
            if (! Schema::hasColumn('menu_options', 'icon_repeat')) {
                $t->unsignedTinyInteger('icon_repeat')->default(1)->after('icon');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_options')) {
            return;
        }

        Schema::table('menu_options', function (Blueprint $t) {
            foreach (['icon', 'icon_repeat'] as $column) {
                if (Schema::hasColumn('menu_options', $column)) {
                    $t->dropColumn($column);
                }
            }
        });
    }
};
