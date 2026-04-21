<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wallet & coins schema.
 *
 *   wallets               — one row per user; integer coin balance.
 *   wallet_transactions   — append-only ledger of every coin movement.
 *   coin_packages         — admin-managed catalog of buyable coin packs.
 *   coin_packages.prices  — uses the existing polymorphic `prices` table.
 *
 * Adds `coin_cost` to `addons` so admin can mark addons coin-redeemable.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->unsignedInteger('low_balance_threshold')->default(100);
            $table->timestamp('low_balance_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // purchase / spend / adjustment / refund
            $table->string('type', 20);
            // signed integer: positive = credit, negative = debit.
            $table->bigInteger('delta_coins');
            $table->bigInteger('balance_after');
            // Idempotency guard for retried webhooks / double-clicks.
            $table->string('idempotency_key', 190)->nullable()->unique();
            $table->string('reason', 500)->nullable();
            // Optional cross-references for reporting / refunds.
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('coin_package_id')->nullable();
            $table->foreignId('addon_id')->nullable();
            $table->foreignId('subscription_addon_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('coin_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('coin_amount');
            $table->unsignedInteger('bonus_coins')->default(0);
            $table->string('status', 20)->default('active');
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('addons', function (Blueprint $table) {
            // Nullable: only addons admin has priced in coins are
            // coin-redeemable. Existing dollar-priced addons are
            // unaffected.
            $table->unsignedInteger('coin_cost')->nullable()->after('annual_price_secondary');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn('coin_cost');
        });
        Schema::dropIfExists('coin_packages');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
