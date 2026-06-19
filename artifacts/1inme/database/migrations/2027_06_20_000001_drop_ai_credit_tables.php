<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the old "AI credits" ledger for good.
 *
 * AI usage is now billed straight from the coin wallet at call time
 * (see 2027_06_19_000001_migrate_ai_credits_to_coins). That one-time
 * migration converted every leftover credit balance into wallet coins
 * and zeroed the old balances; nothing reads `ai_credit_balances` /
 * `ai_credit_transactions` for live behaviour any more. With the
 * conversion proven stable in production, these tables (and their now
 * deleted Eloquent models) are dropped to remove dead schema.
 *
 * Drop transactions first — its FK references ai_credit_balances.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('ai_credit_transactions');
        Schema::dropIfExists('ai_credit_balances');
    }

    public function down(): void
    {
        Schema::create('ai_credit_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('lifetime_purchased')->default(0);
            $table->bigInteger('lifetime_spent')->default(0);
            $table->timestamps();
        });

        Schema::create('ai_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balance_id')->constrained('ai_credit_balances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->bigInteger('delta_credits');
            $table->bigInteger('balance_after');
            $table->string('idempotency_key', 190)->nullable()->unique();
            $table->string('feature', 32)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()
                ->constrained('wallet_transactions')->nullOnDelete();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['feature', 'created_at']);
        });
    }
};
