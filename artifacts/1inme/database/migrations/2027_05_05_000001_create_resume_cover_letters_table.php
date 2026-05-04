<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores AI-generated cover letters tied to a resume.
 *
 * One row per generation (saved automatically when a creator clicks
 * "Generate") so the History panel can list every prior draft, and so
 * inline edits can be saved back without re-running the AI charge.
 *
 * `resume_revision` snapshots the resume's `share_revision` at the
 * moment of generation, giving us a cheap "this letter was generated
 * against an older version of the resume" hint without forcing a full
 * resume version table on day one.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('resume_cover_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('resume_revision')->default(0);

            // Display label — defaults to "Cover letter for <company>"
            // when we can extract one, otherwise the first 80 chars of
            // the JD. Editable by the creator from the panel.
            $table->string('title', 200);

            // Tone preset chosen at generation time. Mirrored on the UI
            // chip so the creator can see at a glance how each saved
            // letter was written.
            $table->string('tone', 32)->default('professional');

            // Full job description text the creator pasted, plus the
            // short excerpt we surface in the history list.
            $table->mediumText('jd_text');
            $table->string('jd_excerpt', 240)->nullable();

            // Two-letter ISO language code (mirrors the resume's). Kept
            // explicit so cross-language regenerates don't silently
            // change the language under the creator.
            $table->string('language', 8)->default('en');

            // Structured letter body so per-section regenerate /
            // inline-edit can target greeting | body[] | sign_off
            // independently. Shape:
            //   { greeting: string, body: string[], sign_off: string }
            $table->json('content');

            // Bookkeeping for the generation that produced this row —
            // model name + total credits charged. Per-section regenerates
            // bump credits_spent so the creator sees the running total.
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('credits_spent')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'resume_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_cover_letters');
    }
};
