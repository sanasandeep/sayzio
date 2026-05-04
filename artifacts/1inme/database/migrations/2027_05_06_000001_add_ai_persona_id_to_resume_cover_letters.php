<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remember which saved AI persona (voice) a cover letter was
 * generated against, so the History rail can show it and so per-
 * section regenerates keep using the same voice the creator picked
 * the first time. Nullable because "None" (resume voice only) is a
 * valid choice and because letters generated before this column
 * existed have no recorded persona.
 *
 * `nullOnDelete` so deleting a persona doesn't take its previously
 * generated letters with it — the letter just loses its voice tag.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('resume_cover_letters', function (Blueprint $table) {
            $table->foreignId('ai_persona_id')
                ->nullable()
                ->after('language')
                ->constrained('ai_personas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resume_cover_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_persona_id');
        });
    }
};
