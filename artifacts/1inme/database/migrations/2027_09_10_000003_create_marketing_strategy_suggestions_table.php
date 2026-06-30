<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3060 — one-click actionable suggestions attached to a Marketing
 * Strategy. Each row is a concrete, applyable play the AI proposed:
 *
 *   type = create_link | add_block | attach_pixel | draft_post
 *
 * `payload` carries the validated, type-specific parameters used to
 * actually build the object when the creator clicks Apply. The result is
 * recorded via `applied_ref_type` / `applied_ref_id` so the UI can deep
 * link to the created object and never double-apply.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe): no FKs
 * (rows are purged via the parent model's `deleting` hook).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketing_strategy_suggestions')) {
            return;
        }

        Schema::create('marketing_strategy_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('strategy_id');
            $table->string('type', 32);  // create_link | add_block | attach_pixel | draft_post
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 16)->default('pending'); // pending | applied | dismissed | error
            $table->string('applied_ref_type', 32)->nullable();
            $table->unsignedBigInteger('applied_ref_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['strategy_id', 'id'], 'marketing_strategy_suggestions_strategy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_strategy_suggestions');
    }
};
