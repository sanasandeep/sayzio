<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3060 — AI Digital Performer / Marketing Strategist.
 *
 * Stores a saved marketing strategy generated for a creator from a goal
 * + parameters + a chosen set of their own data sources. The parsed,
 * structured plan (organic + paid plays built around Sayzio features)
 * lives in `strategy`; the raw inputs and the assembled data context are
 * kept alongside so a strategy can be re-opened and chat-refined later.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe): no FKs
 * (ownership is enforced in the controller via user_id / workspace_id).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketing_strategies')) {
            return;
        }

        Schema::create('marketing_strategies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('title', 180)->default('Marketing Strategy');
            $table->text('goal')->nullable();
            $table->string('status', 16)->default('ready'); // ready | failed
            $table->json('sources')->nullable();          // toggled data-source flags
            $table->json('parameters')->nullable();        // budget / timeframe / channels / tone…
            $table->json('context_snapshot')->nullable();  // assembled data fed to the model
            $table->json('strategy')->nullable();          // parsed structured plan
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('credits_spent')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'marketing_strategies_user_idx');
            $table->index('workspace_id', 'marketing_strategies_ws_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_strategies');
    }
};
