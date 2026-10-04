<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the guest wants their order.
 *
 * Sana, 2026-09-28: "if take away, need to tell slots/time... if delivery
 * option... need to tell slots/time".
 *
 * ---- One column, not a slot table --------------------------------------
 *
 * The obvious build is a `menu_slots` table with capacity per slot. That is
 * a real feature -- a kitchen that can only hand over four orders at 7:30
 * wants it -- and it is not what was asked for. Nobody has asked to cap a
 * slot, and a capacity model with no capacity in it is a join table that
 * does nothing. A timestamp on the order answers the question that was
 * actually asked, and capping comes back as a table the day somebody wants
 * it, without this column changing.
 *
 * Nullable means "as soon as possible", which is both the default and what
 * every order placed before today meant.
 */
return new class extends Migration
{
    private const ORDER_TABLES = ['restaurant_orders', 'store_orders'];

    public function up(): void
    {
        foreach (self::ORDER_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'wanted_at')) {
                    // UTC, like every other timestamp here. The kitchen
                    // screen renders it in the owner's zone.
                    $t->timestamp('wanted_at')->nullable();
                }
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                $index = $table.'_wanted_idx';
                if (! $this->hasIndex($table, $index)) {
                    // "What is due in the next half hour" is the question a
                    // kitchen screen will eventually ask of this.
                    $t->index(['menu_id', 'wanted_at'], $index);
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
                $index = $table.'_wanted_idx';
                if ($this->hasIndex($table, $index)) {
                    $t->dropIndex($index);
                }
                if (Schema::hasColumn($table, 'wanted_at')) {
                    $t->dropColumn('wanted_at');
                }
            });
        }
    }
};
