<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #6737 — Marketing Plan Calculator.
 *
 * Named, saved 12-month marketing plans (the interactive replacement for
 * the "Sayzio-Powered Digital Marketing Plan" spreadsheet). All the plan's
 * assumptions live in a single JSON payload so the shape can evolve
 * without further migrations; computation happens client-side.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe): no FKs
 * (ownership enforced in the controller via user_id / workspace_id).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketing_plan_calcs')) {
            return;
        }

        Schema::create('marketing_plan_calcs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('name', 160);
            $table->json('payload')->nullable(); // assumptions: budget, weights, channels[], tools[], currency…
            $table->timestamps();

            $table->index(['user_id', 'workspace_id'], 'marketing_plan_calcs_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_plan_calcs');
    }
};
