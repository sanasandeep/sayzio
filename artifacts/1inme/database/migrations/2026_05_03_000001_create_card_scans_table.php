<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user log of business-card / brochure AI extractions.
 *
 * One row per upload. The user uploads a card or brochure (image or
 * PDF), we rasterize + send to OpenAI vision, store the structured
 * JSON it returns, and let the user turn that into a Contact and/or
 * a Biolink wizard draft.
 *
 * Columns:
 *   user_id          workspace owner
 *   actor_user_id    member who actually uploaded (audit)
 *   source_file_id   UserFile (vault) holding the original upload
 *   status           pending | processing | completed | failed
 *   error            short failure message if status='failed'
 *   raw_response     full vision JSON from OpenAI (debug + replay)
 *   extracted        normalized DTO surfaced in the review screen
 *   credits_spent    cached charge amount for the per-user dashboard
 *   contact_id       set when the user saved the scan as a contact
 *   wizard_draft_id  set when the user seeded a biolink wizard draft
 *   idempotency_key  unique (user, file-hash, model) — re-uploading the
 *                    same file in the same session reuses the row and
 *                    never re-charges credits.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('card_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('actor_user_id');
            $table->unsignedBigInteger('source_file_id')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('error', 500)->nullable();
            $table->json('raw_response')->nullable();
            $table->json('extracted')->nullable();
            $table->unsignedInteger('credits_spent')->default(0);
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('wizard_draft_id')->nullable();
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index('actor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_scans');
    }
};
