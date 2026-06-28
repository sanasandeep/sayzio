<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On-Brand AI Companion opt-out (Task #2664).
 *
 * Adds a per-persona toggle that decides whether the owner's default Brand
 * Kit voice/tone is injected into the persona's system prompt. Defaults to
 * TRUE so every existing persona is on-brand by default, with an explicit
 * opt-out in the editor. Additive + guarded so it is safe to replay against
 * the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_persona_agents')) {
            return;
        }
        if (!Schema::hasColumn('ai_persona_agents', 'use_brand_kit')) {
            Schema::table('ai_persona_agents', function (Blueprint $table) {
                $table->boolean('use_brand_kit')->default(true)->after('style_guide');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_persona_agents') && Schema::hasColumn('ai_persona_agents', 'use_brand_kit')) {
            Schema::table('ai_persona_agents', function (Blueprint $table) {
                $table->dropColumn('use_brand_kit');
            });
        }
    }
};
