<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3281 — Reusable Marketing Profile.
 *
 * A per-creator (per-workspace) intake that captures the durable facts a
 * strategy should be grounded in: target audience, expectations and hard
 * constraints. It pre-fills every new strategy so the creator doesn't
 * re-type the same context each time. Stored as three JSON bags so the
 * shape can evolve without further migrations.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe): no FKs
 * (ownership enforced in the controller via user_id / workspace_id).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketing_profiles')) {
            return;
        }

        Schema::create('marketing_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->json('target_audience')->nullable(); // age_range, location, interests[], platforms[], intent
            $table->json('expectations')->nullable();     // success_target, baseline, budget_ceiling, weekly_time, risk_appetite
            $table->json('constraints')->nullable();      // avoid_content[], avoid_channels[]
            $table->timestamps();

            $table->index(['user_id', 'workspace_id'], 'marketing_profiles_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_profiles');
    }
};
