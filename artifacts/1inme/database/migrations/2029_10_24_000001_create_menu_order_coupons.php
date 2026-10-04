<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk orders, and the food coupons they produce.
 *
 * Sana, 2026-10-04: "need options while order from menu minimum and max
 * order quatity or each order item... this will create like bulk order...
 * when bulk order.... need to generate food coupons ids.... when coupoon
 * id give, order of that 1 coupon is completed or delivered... also
 * alternatively, search order by phone no also possible".
 *
 * ---- One coupon per serving --------------------------------------------
 *
 * An office orders 200 lunches on one order and 200 different people
 * collect them, one at a time, over an hour. A single code for the whole
 * line cannot answer "has this person already eaten", which is the only
 * question the counter is actually asking. So 200 rows, each redeemed on
 * its own, and the order is done when the last one is in.
 *
 * ---- Why a code column and not the row id ------------------------------
 *
 * The id is sequential, so coupon 4,001 tells its holder that 4,000 exist
 * and that 4,002 probably works too. The code is random out of an alphabet
 * with no 0/O/1/I in it, because it gets read off a phone screen, typed by
 * somebody at a serving counter, and misread otherwise.
 *
 * ---- The item columns ---------------------------------------------------
 *
 * `min_quantity` and `max_quantity` bound what one order line may ask for.
 * `coupon_from` is the quantity at which that line starts issuing coupons,
 * and null means never: a diner ordering one dosa should not be handed a
 * coupon id they have no use for.
 */
return new class extends Migration
{
    private const ITEM_TABLES = ['restaurant_menu_items', 'store_products'];

    public function up(): void
    {
        if (! Schema::hasTable('menu_order_coupons')) {
            Schema::create('menu_order_coupons', function (Blueprint $t) {
                $t->id();
                // Read off a screen and typed at a counter: short, and out
                // of an alphabet with no ambiguous characters.
                $t->string('code', 16)->unique();

                // 'restaurant' | 'store', so one table serves both and one
                // lookup finds a code whichever kind of menu issued it.
                $t->string('order_type', 16);
                $t->unsignedBigInteger('order_id');
                $t->unsignedBigInteger('order_item_id')->nullable();

                // Scoping: a code is only redeemable on the menu that
                // issued it, checked without loading the order first.
                $t->string('menu_type', 16);
                $t->unsignedBigInteger('menu_id');

                // A snapshot. The owner will rename the dish, and a coupon
                // issued last Tuesday has to keep saying what it is for.
                $t->string('item_name', 160);

                $t->string('status', 16)->default('issued');
                $t->timestamp('redeemed_at')->nullable();
                // Which staff account took it, when there was one.
                $t->unsignedBigInteger('redeemed_by')->nullable();
                $t->timestamps();

                $t->index(['order_type', 'order_id']);
                $t->index(['menu_type', 'menu_id', 'status']);
            });
        }

        foreach (self::ITEM_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'min_quantity')) {
                    $t->unsignedInteger('min_quantity')->default(1);
                }
                // Null is "no ceiling", which is what every item means
                // today and must keep meaning.
                if (! Schema::hasColumn($table, 'max_quantity')) {
                    $t->unsignedInteger('max_quantity')->nullable();
                }
                // Null is "never issue coupons".
                if (! Schema::hasColumn($table, 'coupon_from')) {
                    $t->unsignedInteger('coupon_from')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::ITEM_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['min_quantity', 'max_quantity', 'coupon_from'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('menu_order_coupons');
    }
};
