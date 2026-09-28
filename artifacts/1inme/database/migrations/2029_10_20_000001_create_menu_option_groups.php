<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Choices on an item.
 *
 * Sana, 2026-09-28: "like spicy levels, served like hot cold.. variations
 * like sizes and toppings options with limitions settings (like select 2)
 * ... options like addons with variable quatity".
 *
 * ---- Those are all one object ------------------------------------------
 *
 * They read like four features and they are one. A group of choices with
 * three numbers on it:
 *
 *   Spice level       pick exactly 1, no price change
 *   Size              pick exactly 1, each choice moves the price
 *   Toppings          pick 0 to 2, each choice adds
 *   Add-ons           pick 0 to any, each choice up to N times
 *
 * required / min / max / how many times one choice can be taken. Four
 * numbers, and every request above falls out of them. Building "sizes" and
 * "toppings" and "add-ons" as three different things would be three
 * editors, three shapes in the cart and three ways for the bill to be
 * wrong.
 *
 * ---- Why groups are not JSON on the item -------------------------------
 *
 * The cheap build is an `options` JSON column on the item. It works until
 * a menu has forty items and "Spice level" belongs on thirty of them: that
 * is thirty copies to type and thirty to edit the day "Extra hot" is
 * added, and they will not stay in step. That is the same ceiling the
 * three-hardcoded-charges design had before charges became rows.
 *
 * So a group is defined once on the menu and attached to as many items as
 * it applies to. An item that needs something of its own gets a group
 * attached to only itself -- no worse than the JSON version, and the
 * general case stops being thirty copies.
 *
 * ---- One set of tables for both menu types -----------------------------
 *
 * A restaurant menu and a store menu are separate tables, and every single
 * thing built for one of them this month has had to be built again for the
 * other, late, after someone noticed. These tables carry which kind of
 * menu they belong to instead, so the store gets this the same day the
 * restaurant does.
 *
 * ---- What an order remembers -------------------------------------------
 *
 * Nothing here. The chosen options land as JSON on the order line, beside
 * the `name` and `unit_price` that line already snapshots, for exactly the
 * same reason: a group can be renamed, re-priced or deleted, and an order
 * from last Tuesday has to keep saying what was actually ordered and what
 * was actually charged. A foreign key to a live options table would let
 * today's edit rewrite last week's receipt.
 */
return new class extends Migration
{
    /** The two order-line tables that gain the snapshot. */
    private const ORDER_ITEM_TABLES = ['restaurant_order_items', 'store_order_items'];

    public function up(): void
    {
        if (! Schema::hasTable('menu_option_groups')) {
            Schema::create('menu_option_groups', function (Blueprint $t) {
                $t->id();
                // 'restaurant' or 'store' -- which menu table `menu_id` points into.
                $t->string('menu_type', 16);
                $t->unsignedBigInteger('menu_id');
                $t->string('name', 80);
                // Shown above the choices: "Choose your spice level".
                $t->string('hint', 160)->nullable();

                // The four numbers that make this one object instead of four.
                $t->boolean('is_required')->default(false);
                $t->unsignedSmallInteger('min_select')->default(0);
                // Null means no ceiling -- "add as many as you like".
                $t->unsignedSmallInteger('max_select')->nullable();
                // How many times ONE choice can be taken. 1 is a checkbox,
                // more than 1 is a quantity stepper.
                $t->unsignedSmallInteger('max_per_option')->default(1);

                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['menu_type', 'menu_id']);
            });
        }

        if (! Schema::hasTable('menu_options')) {
            Schema::create('menu_options', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('group_id')->index();
                $t->string('name', 80);
                // Signed: a smaller size may take money OFF the base price.
                $t->decimal('price_delta', 10, 2)->default(0);
                $t->unsignedInteger('sort_order')->default(0);
                // Today's "no more paneer" without deleting the choice.
                $t->boolean('is_sold_out')->default(false);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        // Which items a group applies to. A plain join table rather than a
        // column on the item, because the whole point is many-to-many.
        if (! Schema::hasTable('menu_item_option_groups')) {
            Schema::create('menu_item_option_groups', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('group_id')->index();
                // 'restaurant_item' or 'store_product'.
                $t->string('owner_type', 24);
                $t->unsignedBigInteger('owner_id');
                // The order the groups appear in on THIS item: sides before
                // spice on one dish, the other way round on another.
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();

                $t->index(['owner_type', 'owner_id']);
                $t->unique(['group_id', 'owner_type', 'owner_id'], 'menu_item_option_group_unique');
            });
        }

        foreach (self::ORDER_ITEM_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                // The snapshot: [{group, name, delta, quantity}], as chosen.
                if (! Schema::hasColumn($table, 'options')) {
                    $t->json('options')->nullable()->after('quantity');
                }
                // What those choices added to this line, kept beside the
                // line total rather than recomputed from the JSON by every
                // screen that shows an order.
                if (! Schema::hasColumn($table, 'options_total')) {
                    $t->decimal('options_total', 10, 2)->default(0)->after('options');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::ORDER_ITEM_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['options', 'options_total'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('menu_item_option_groups');
        Schema::dropIfExists('menu_options');
        Schema::dropIfExists('menu_option_groups');
    }
};
