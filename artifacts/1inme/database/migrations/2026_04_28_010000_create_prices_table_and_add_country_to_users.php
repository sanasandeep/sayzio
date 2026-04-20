<?php

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task #191: Country-based pricing.
 *
 * Adds a polymorphic `prices` table keyed by
 * (priceable_type, priceable_id, currency, billing_cycle) so the same
 * resolver code can serve both plans and addons. Amounts are stored in
 * MINOR units (cents for USD, paise for INR) to avoid float drift.
 *
 * Also adds `users.country` (ISO 3166-1 alpha-2) — this is the user's
 * BILLING country, NOT a shipping address.
 *
 * Existing `plans.monthly_price` / `plans.annual_price` decimal columns
 * are left intact as a compatibility shim. They get backfilled into the
 * new `prices` table as USD rows here. `*_secondary` columns (added in
 * the previous migration) are backfilled as INR rows when populated.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $t) {
            $t->id();
            $t->string('priceable_type');
            $t->unsignedBigInteger('priceable_id');
            $t->string('currency', 3);              // 'USD' | 'INR' (extensible)
            $t->string('billing_cycle', 16);        // 'monthly' | 'annual'
            $t->unsignedBigInteger('amount_minor_units')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(
                ['priceable_type', 'priceable_id', 'currency', 'billing_cycle'],
                'prices_priceable_currency_cycle_unique'
            );
            $t->index(['priceable_type', 'priceable_id'], 'prices_priceable_idx');
        });

        Schema::table('users', function (Blueprint $t) {
            // ISO 3166-1 alpha-2. Nullable: existing users haven't been
            // asked yet. The PricingResolver treats null as "USD by
            // default with optional anonymous switcher". This is the
            // BILLING country only — not a shipping address.
            $t->string('country', 2)->nullable()->after('language');
        });

        $this->backfill(Plan::class, 'plans');
        $this->backfill(Addon::class, 'addons');
    }

    private function backfill(string $morphClass, string $table): void
    {
        if (!Schema::hasTable($table)) return;

        $rows = DB::table($table)->get();
        $now = now();
        foreach ($rows as $row) {
            $this->upsertPrice($morphClass, $row->id, 'USD', 'monthly', $row->monthly_price ?? 0, $now);
            $this->upsertPrice($morphClass, $row->id, 'USD', 'annual',  $row->annual_price  ?? 0, $now);

            if (isset($row->monthly_price_secondary) && $row->monthly_price_secondary !== null) {
                $this->upsertPrice($morphClass, $row->id, 'INR', 'monthly', $row->monthly_price_secondary, $now);
            }
            if (isset($row->annual_price_secondary) && $row->annual_price_secondary !== null) {
                $this->upsertPrice($morphClass, $row->id, 'INR', 'annual',  $row->annual_price_secondary,  $now);
            }
        }
    }

    private function upsertPrice(string $type, int $id, string $currency, string $cycle, $major, $now): void
    {
        $minor = (int) round(((float) $major) * 100);
        DB::table('prices')->updateOrInsert(
            [
                'priceable_type' => $type,
                'priceable_id'   => $id,
                'currency'       => $currency,
                'billing_cycle'  => $cycle,
            ],
            [
                'amount_minor_units' => $minor,
                'is_active'          => true,
                'updated_at'         => $now,
                'created_at'         => $now,
            ]
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('country');
        });
        Schema::dropIfExists('prices');
    }
};
