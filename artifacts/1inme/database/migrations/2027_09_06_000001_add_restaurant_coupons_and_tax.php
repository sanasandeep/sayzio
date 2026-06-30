<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant Menu coupons + estimated GST/tax bill (Task #3067).
 *
 * Adds an owner-managed `restaurant_menu_coupons` store (code + discount
 * type/value + minimum bill to qualify + active flag) and snapshots the
 * applied coupon/discount + tax treatment + estimated total onto each
 * `restaurant_orders` row so the staff dashboard and the guest's
 * order-status view show the same breakdown. Menu-level tax settings
 * (inclusive vs added-on, rate) live in the existing `restaurant_menus`
 * `settings` JSON, so no column is needed there.
 *
 * Still no online payment — the figure is an *estimate*, not the actual
 * bill; guests pay staff directly at the table.
 *
 * Additive + idempotent so it is safe over the shared RDS.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('restaurant_menu_coupons')) {
            Schema::create('restaurant_menu_coupons', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('menu_id')->index();
                $t->string('code', 64);
                // 'percent' = % off subtotal; 'fixed' = flat amount off.
                $t->string('discount_type', 16)->default('percent');
                $t->decimal('discount_value', 10, 2)->default(0);
                // Minimum subtotal required before the coupon qualifies.
                $t->decimal('min_subtotal', 10, 2)->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                // One code per menu (codes are matched case-insensitively in
                // the app, stored upper-cased).
                $t->unique(['menu_id', 'code']);
            });
        }

        if (Schema::hasTable('restaurant_orders')) {
            Schema::table('restaurant_orders', function (Blueprint $t) {
                if (!Schema::hasColumn('restaurant_orders', 'coupon_code')) {
                    $t->string('coupon_code', 64)->nullable()->after('subtotal');
                }
                if (!Schema::hasColumn('restaurant_orders', 'discount_amount')) {
                    $t->decimal('discount_amount', 10, 2)->default(0)->after('coupon_code');
                }
                if (!Schema::hasColumn('restaurant_orders', 'tax_rate')) {
                    $t->decimal('tax_rate', 6, 3)->default(0)->after('discount_amount');
                }
                if (!Schema::hasColumn('restaurant_orders', 'tax_inclusive')) {
                    $t->boolean('tax_inclusive')->default(false)->after('tax_rate');
                }
                if (!Schema::hasColumn('restaurant_orders', 'tax_amount')) {
                    $t->decimal('tax_amount', 10, 2)->default(0)->after('tax_inclusive');
                }
                if (!Schema::hasColumn('restaurant_orders', 'total')) {
                    $t->decimal('total', 10, 2)->default(0)->after('tax_amount');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_coupons');

        if (Schema::hasTable('restaurant_orders')) {
            Schema::table('restaurant_orders', function (Blueprint $t) {
                foreach (['coupon_code', 'discount_amount', 'tax_rate', 'tax_inclusive', 'tax_amount', 'total'] as $col) {
                    if (Schema::hasColumn('restaurant_orders', $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
