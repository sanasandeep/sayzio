<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coin-package lineup v3 support:
 *
 *   coin_packages.best_for        — short "Best for" audience label shown on
 *                                   buy/pricing surfaces (e.g. "Trying AI").
 *   coin_packages.api_budget_pct  — hidden internal allocation: % of the
 *                                   purchase price budgeted for API costs.
 *                                   Platform margin is always 100 − this, so
 *                                   the split can never sum to ≠ 100.
 *
 *   coin_purchase_allocations     — admin-only snapshot written when a coin
 *                                   purchase completes: the dollar split of
 *                                   the collected amount at the package's
 *                                   allocation percentages at that moment.
 *                                   Unique on invoice_id (idempotent against
 *                                   webhook re-delivery).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('coin_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('coin_packages', 'best_for')) {
                $table->string('best_for', 100)->nullable()->after('description');
            }
            if (!Schema::hasColumn('coin_packages', 'api_budget_pct')) {
                $table->decimal('api_budget_pct', 5, 2)->nullable()->after('best_for');
            }
        });

        if (!Schema::hasTable('coin_purchase_allocations')) {
            Schema::create('coin_purchase_allocations', function (Blueprint $table) {
                $table->id();
                // Idempotency anchor: one allocation snapshot per invoice.
                $table->foreignId('invoice_id')->unique()->constrained('invoices')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('coin_package_id')->nullable();
                $table->unsignedBigInteger('coins')->default(0);
                $table->string('currency', 3);
                // Amount collected for the coin line items (pre-tax, minor units).
                $table->bigInteger('amount_minor');
                // Percentages snapshotted at completion time.
                $table->decimal('api_budget_pct', 5, 2);
                $table->decimal('margin_pct', 5, 2);
                // Dollar split in minor units (api + margin === amount).
                $table->bigInteger('api_budget_minor');
                $table->bigInteger('margin_minor');
                $table->timestamp('created_at')->useCurrent();

                $table->index('created_at');
                $table->index('coin_package_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_purchase_allocations');
        Schema::table('coin_packages', function (Blueprint $table) {
            if (Schema::hasColumn('coin_packages', 'api_budget_pct')) {
                $table->dropColumn('api_budget_pct');
            }
            if (Schema::hasColumn('coin_packages', 'best_for')) {
                $table->dropColumn('best_for');
            }
        });
    }
};
