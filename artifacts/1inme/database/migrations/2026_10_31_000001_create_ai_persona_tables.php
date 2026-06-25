<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Persona Agents — configurable conversational agents.
 *
 * Distinct from the simpler `ai_personas` library (Task #486) which
 * stores generator output — these tables back the live agent runtime
 * that drives widgets / inbox / Coach.
 *
 *   ai_persona_agents          — owned-by-user agent config (name,
 *                                prompt, model, knobs, attached
 *                                Minds…). The Persona is the
 *                                "voice + rules" layer; Minds give it
 *                                knowledge.
 *   ai_persona_agent_minds     — many-to-many pivot: persona ↔
 *                                ai_minds. The built-in Sayzio default
 *                                Mind is opted-in via the
 *                                `use_default_mind` flag, *not* via
 *                                this pivot, so admins can re-seed /
 *                                disable it without rewriting pivots.
 *   ai_persona_agent_versions  — append-only revision log. Every save
 *                                writes a new row; `active_version_id`
 *                                on the persona points at whichever
 *                                revision live surfaces should serve.
 *                                Rollback flips the pointer.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ai_persona_agents')) {
            Schema::create('ai_persona_agents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                // Sluggable, unique per user. Nullable so initial create
                // can generate it after the row id is known.
                $table->string('slug', 80)->nullable();
                $table->string('name', 120);
                $table->string('description', 500)->nullable();
                $table->string('avatar_url', 1024)->nullable();
                // Voice + rules.
                $table->text('system_prompt');
                $table->string('tone_preset', 32)->nullable();
                $table->text('style_guide')->nullable();
                // Model knobs.
                $table->string('model', 64);
                $table->unsignedSmallInteger('temperature_x100')->default(50); // 0.50
                $table->unsignedInteger('max_tokens')->default(600);
                $table->json('languages')->nullable();
                // Toggles + behaviour.
                $table->json('allowed_actions')->nullable();
                $table->string('fallback_behavior', 32)->default('clarify');
                $table->text('greeting')->nullable();
                $table->json('starter_questions')->nullable();
                $table->string('end_cta_label', 120)->nullable();
                $table->string('end_cta_url', 1024)->nullable();
                // Knowledge linkage.
                $table->boolean('use_default_mind')->default(true);
                // Lifecycle.
                $table->boolean('is_disabled')->default(false);
                $table->string('disabled_reason', 500)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->unsignedBigInteger('active_version_id')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->unique(['user_id', 'slug']);
                $table->index('is_disabled');
            });
        }

        if (!Schema::hasTable('ai_persona_agent_minds')) {
            Schema::create('ai_persona_agent_minds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('persona_id')->constrained('ai_persona_agents')->cascadeOnDelete();
                $table->foreignId('mind_id')->constrained('ai_minds')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['persona_id', 'mind_id']);
                $table->index('mind_id');
            });
        }

        if (!Schema::hasTable('ai_persona_agent_versions')) {
            Schema::create('ai_persona_agent_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('persona_id')->constrained('ai_persona_agents')->cascadeOnDelete();
                $table->unsignedInteger('revision');
                $table->json('config');
                $table->string('summary', 500)->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['persona_id', 'revision']);
                $table->index('persona_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_persona_agent_versions');
        Schema::dropIfExists('ai_persona_agent_minds');
        Schema::dropIfExists('ai_persona_agents');
    }
};
