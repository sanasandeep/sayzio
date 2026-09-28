<?php

use App\Modules\User\Models\MenuItemMark;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks on a dish: veg, non-veg, egg, seafood, spicy, hot, cold, gravy,
 * no garlic.
 *
 * Sana, 2026-09-28: "for spicy, serving and all.. its fixed value.. only
 * customers will see in menu if its too spicy or less... hot or cold......
 * gravy or dry or semi gravy... no garlic.. no onions... these options can
 * be managed in admin... not all item need to have these configs".
 *
 * ---- Why this is NOT a choice group -----------------------------------
 *
 * Choices ask the customer a question: "how hot would you like it?" A mark
 * states a fact about the dish: "this IS hot." The first belongs in the
 * ordering sheet and changes the bill; the second belongs on the menu row
 * and changes nothing. Building spice as a choice, which is what shipped
 * first, asks a diner to pick a spice level the kitchen was never going to
 * vary.
 *
 * ---- Why the vocabulary is admin-managed and global --------------------
 *
 * Sana: "show all possible in admin data". A green square means vegetarian
 * on every Indian menu there is, and it only keeps meaning that if it is
 * the same square everywhere. Letting each of 375,000 owners invent their
 * own would make the icons decorative within a week. So the list lives
 * here, admin edits it, and an owner picks from it.
 *
 * ---- Why the item stores JSON rather than a join table -----------------
 *
 * The argument that made choice GROUPS rows -- "Spice level" on thirty
 * dishes is thirty copies to keep in step -- does not apply: the
 * vocabulary is not copied, only referenced. What an item holds is a short
 * bounded list of references, read with the item on the same query, no
 * join and no N+1. One column per item table, the way `options` already
 * is.
 *
 * ---- Colour is not decoration ------------------------------------------
 *
 * The square-and-dot mark carries its whole meaning in its colour: green
 * vegetarian, red non-vegetarian, amber egg. Same drawing three times. So
 * colour is a column on the mark, not a theme decision on the page.
 */
return new class extends Migration
{
    private const ITEM_TABLES = ['restaurant_menu_items', 'store_products'];

    public function up(): void
    {
        if (! Schema::hasTable('menu_item_marks')) {
            Schema::create('menu_item_marks', function (Blueprint $t) {
                $t->id();
                // What the item's JSON refers to. Stable: renaming the
                // label must not orphan every dish carrying it.
                $t->string('key', 32)->unique();
                $t->string('label', 40);
                // Diet marks first, preferences last: the order marks are
                // drawn in on a dish is this, not the order they were
                // ticked, so two dishes never read differently.
                $t->string('group', 24)->default(MenuItemMark::GROUP_OTHER);
                // A MenuOptionIcon key, or null to draw a small text chip
                // instead. "No garlic" has no legible 14px glyph and does
                // not need one.
                $t->string('icon', 24)->nullable();
                $t->string('color', 7)->nullable();
                // Spice is one flame at one, two or three.
                $t->boolean('is_graded')->default(false);
                $t->unsignedTinyInteger('max_grade')->default(3);
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['is_active', 'sort_order']);
            });
        }

        foreach (self::ITEM_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'marks')) {
                    // [{"key":"veg"},{"key":"spicy","grade":2}]
                    $t->json('marks')->nullable();
                }
            });
        }

        $this->seed();
    }

    /**
     * The starting vocabulary, only into an empty table. An admin who has
     * pruned this list should not find it back after a deploy.
     */
    private function seed(): void
    {
        if (! Schema::hasTable('menu_item_marks') || DB::table('menu_item_marks')->exists()) {
            return;
        }

        $now = now();
        $rows = [];
        $order = 0;

        foreach (self::STARTING_VOCABULARY as [$key, $label, $group, $icon, $color, $graded]) {
            $rows[] = [
                'key'        => $key,
                'label'      => $label,
                'group'      => $group,
                'icon'       => $icon,
                'color'      => $color,
                'is_graded'  => $graded,
                'max_grade'  => 3,
                'sort_order' => $order += 10,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('menu_item_marks')->insert($rows);
    }

    /** key, label, group, icon, colour, graded */
    private const STARTING_VOCABULARY = [
        ['veg',          'Vegetarian',      'diet',        'diet-mark', '#0a8f3c', false],
        ['nonveg',       'Non-vegetarian',  'diet',        'diet-mark', '#c2261b', false],
        ['egg',          'Contains egg',    'diet',        'diet-mark', '#d18700', false],
        ['seafood',      'Seafood',         'diet',        'fish',      '#1e6fbf', false],
        ['vegan',        'Vegan',           'diet',        'sprig',     '#0a8f3c', false],

        ['spicy',        'Spicy',           'spice',       'flame',     '#d94f1e', true],

        ['served-hot',   'Served hot',      'temperature', 'steam',     '#8a7a66', false],
        ['served-cold',  'Served cold',     'temperature', 'snowflake', '#2563eb', false],

        ['gravy',        'Gravy',           'texture',     'drop',      '#8a7a66', false],
        ['semi-gravy',   'Semi-gravy',      'texture',     null,        null,      false],
        ['dry',          'Dry',             'texture',     null,        null,      false],

        ['no-garlic',    'No garlic',       'preference',  null,        null,      false],
        ['no-onion',     'No onion',        'preference',  null,        null,      false],
        ['jain',         'Jain',            'preference',  null,        null,      false],

        ['nuts',         'Contains nuts',   'allergen',    null,        null,      false],
        ['dairy',        'Contains dairy',  'allergen',    'milk',      '#8a7a66', false],
        ['gluten',       'Contains gluten', 'allergen',    'wheat',     '#8a7a66', false],
    ];

    public function down(): void
    {
        foreach (self::ITEM_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'marks')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('marks'));
            }
        }

        Schema::dropIfExists('menu_item_marks');
    }
};
