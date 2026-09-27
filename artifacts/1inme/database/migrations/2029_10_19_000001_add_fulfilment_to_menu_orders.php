<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How an order is handed over, where it goes, and what that added.
 *
 * Sana, 2026-09-23: "adding of address form, delivery options and other
 * settings configurable".
 *
 * Four columns on each order table:
 *   fulfilment       dine_in | takeaway | delivery -- what the guest chose
 *   customer_address where to send it, for delivery only
 *   charges_amount   what the charges came to, so the total is reconstructable
 *   charges          the snapshot of WHICH charges and how much each was
 *
 * The snapshot matters more than it looks. A charge lives in the menu's
 * settings and an owner will edit it -- raise the delivery fee, rename the
 * parcel charge, delete one. An order that stored only a total would then
 * be unexplainable a week later, and the one thing a guest queries is a
 * line they do not recognise. The order keeps its own copy.
 *
 * Every column is additive and nullable (or zero-default), so every order
 * already in these tables stays exactly what it is: no fulfilment recorded,
 * no address, no charges, and a total that still equals what it always did.
 */
return new class extends Migration
{
    private const TABLES = ['restaurant_orders', 'store_orders'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'fulfilment')) {
                    $t->string('fulfilment', 16)->nullable()->index();
                }
                if (! Schema::hasColumn($table, 'customer_address')) {
                    $t->text('customer_address')->nullable();
                }
                if (! Schema::hasColumn($table, 'charges_amount')) {
                    $t->decimal('charges_amount', 10, 2)->default(0);
                }
                if (! Schema::hasColumn($table, 'charges')) {
                    $t->json('charges')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (['fulfilment', 'customer_address', 'charges_amount', 'charges'] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->dropColumn($column);
                });
            }
        }
    }
};
