<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Engine credits ledger.
 *
 *   ai_credit_balances     — one row per user; running credits balance
 *                            plus lifetime totals for fast admin reports.
 *   ai_credit_transactions — append-only ledger of every credit movement
 *                            (purchase / spend / refund / grant /
 *                            admin_adjustment) annotated with the AI
 *                            feature, model, and token counts.
 *
 * Mirrors the wallet ledger pattern so reporting / refund logic feels
 * familiar and supports the same idempotency-key guard.
 */
return new class extends Migration {
    public function up(): void
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
            // purchase / spend / refund / grant / admin_adjustment
            $table->string('type', 24);
            // signed: positive = credit, negative = debit.
            $table->bigInteger('delta_credits');
            $table->bigInteger('balance_after');
            $table->string('idempotency_key', 190)->nullable()->unique();
            // Which AI feature consumed the credits (mind / persona /
            // companion / coach). Null for purchases / grants.
            $table->string('feature', 32)->nullable();
            // Free-form id pointing at the row that triggered the spend
            // (e.g. mind_query_id, persona_message_id). No FK because the
            // target table varies per feature.
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            // Cross-reference to the wallet debit when credits were
            // bought with wallet coins. Null otherwise.
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

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_transactions');
        Schema::dropIfExists('ai_credit_balances');
    }
};
