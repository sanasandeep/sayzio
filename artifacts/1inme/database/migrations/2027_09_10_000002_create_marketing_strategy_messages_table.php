<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3060 — chat-refinement turns for a saved Marketing Strategy.
 *
 * Append-only: each user/assistant turn of the "refine this strategy"
 * conversation. Assistant rows carry metered spend + model in `meta`.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe): no FKs
 * (rows are purged via the parent model's `deleting` hook).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketing_strategy_messages')) {
            return;
        }

        Schema::create('marketing_strategy_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('strategy_id');
            $table->string('role', 16); // user | assistant
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['strategy_id', 'id'], 'marketing_strategy_messages_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_strategy_messages');
    }
};
