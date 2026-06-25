<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user default Mind selection for AI features (Persona, Coach).
 *
 * Stores which of the user's own Minds and whether the platform default
 * Mind should be pre-selected on the Persona / Coach forms so the user
 * doesn't have to re-pick on every visit.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ai_mind_defaults')) {
            Schema::create('ai_mind_defaults', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('feature', 32);
                $table->json('mind_ids');
                $table->boolean('include_platform')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'feature']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_mind_defaults');
    }
};
