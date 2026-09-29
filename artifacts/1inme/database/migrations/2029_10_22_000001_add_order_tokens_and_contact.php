<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token numbers on orders, and a phone number that is actually required.
 *
 * Sana, 2026-09-28: "i need token no. to be generated for each order...
 * need options in setting like: reset tokeno. by day, week, month or all
 * time.. when customer sees token no.. make it upto copied text" and
 * "when placing order: name and phone mandatory".
 *
 * ---- Why a counter TABLE and not max(token_number) + 1 -----------------
 *
 * Two people at two tables tap Place order in the same second. `SELECT
 * max(...) + 1` hands them both the same number, and the person collecting
 * food at the counter is then told there are two order 14s. A row per
 * (menu, period) incremented inside a locked transaction cannot do that:
 * the second request waits for the first.
 *
 * The period is part of the KEY rather than a thing to clear out, so
 * "reset daily" is simply a new row tomorrow. Nothing has to run at
 * midnight, nothing has to be cleaned up, and a menu that changes from
 * daily to monthly halfway through keeps both histories intact.
 *
 * ---- Whose midnight ----------------------------------------------------
 *
 * The owner's. A restaurant in Chennai closing at 23:30 wants one run of
 * numbers for that evening, and UTC would split it at 05:30 local. The
 * period key is computed in the owner's timezone, which the platform
 * already tracks and defaults to IST.
 *
 * ---- Phone gets a column, not a corner of meta -------------------------
 *
 * It was reaching us inside the `meta` json, where nothing can index it,
 * no screen can show it and the contact-linking job had to dig for it.
 * It is the thing the restaurant rings when an order goes wrong, so it is
 * a column.
 */
return new class extends Migration
{
    private const ORDER_TABLES = ['restaurant_orders', 'store_orders'];

    public function up(): void
    {
        if (! Schema::hasTable('menu_order_counters')) {
            Schema::create('menu_order_counters', function (Blueprint $t) {
                $t->id();
                // 'restaurant' | 'store'
                $t->string('menu_type', 16);
                $t->unsignedBigInteger('menu_id');
                // 'all', '2026-09-28', '2026-W40', '2026-09' -- whichever
                // the menu's reset setting produces.
                $t->string('period_key', 16);
                $t->unsignedInteger('next_value')->default(1);
                $t->timestamps();

                $t->unique(['menu_type', 'menu_id', 'period_key'], 'menu_order_counter_unique');
            });
        }

        foreach (self::ORDER_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                // Nullable: every order placed before today has none, and
                // backfilling numbers that were never called out would be
                // inventing history.
                if (! Schema::hasColumn($table, 'token_number')) {
                    $t->unsignedInteger('token_number')->nullable();
                }
                // What the number was counted against, kept beside it so a
                // screen can say "14 of today" without recomputing a
                // boundary that may since have been changed.
                if (! Schema::hasColumn($table, 'token_period')) {
                    $t->string('token_period', 16)->nullable();
                }
                if (! Schema::hasColumn($table, 'customer_phone')) {
                    $t->string('customer_phone', 32)->nullable();
                }
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                // The kitchen screen's "find order 14" and the guest's own
                // lookup both go through this.
                $index = $table.'_token_idx';
                if (! $this->hasIndex($table, $index)) {
                    $t->index(['menu_id', 'token_period', 'token_number'], $index);
                }
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn ($i) => ($i['name'] ?? null) === $index);
        } catch (\Throwable) {
            return false;
        }
    }

    public function down(): void
    {
        foreach (self::ORDER_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $index = $table.'_token_idx';
                if ($this->hasIndex($table, $index)) {
                    $t->dropIndex($index);
                }
                foreach (['token_number', 'token_period', 'customer_phone'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('menu_order_counters');
    }
};
