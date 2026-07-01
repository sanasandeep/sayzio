<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3281 — Scorecard history.
 *
 * Each row is a point-in-time snapshot of a strategy's 0-100 scorecard
 * (Reach / Engagement / Conversion / Consistency + overall) so movement
 * is visible over time as the creator's data changes and the strategy is
 * re-scored. `reasons` keeps the one-line explanation per axis for the
 * snapshot it was captured with.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe): no FKs.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketing_strategy_scores')) {
            return;
        }

        Schema::create('marketing_strategy_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('strategy_id');
            $table->unsignedTinyInteger('reach')->default(0);
            $table->unsignedTinyInteger('engagement')->default(0);
            $table->unsignedTinyInteger('conversion')->default(0);
            $table->unsignedTinyInteger('consistency')->default(0);
            $table->unsignedTinyInteger('overall')->default(0);
            $table->json('reasons')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['strategy_id', 'id'], 'marketing_strategy_scores_strategy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_strategy_scores');
    }
};
