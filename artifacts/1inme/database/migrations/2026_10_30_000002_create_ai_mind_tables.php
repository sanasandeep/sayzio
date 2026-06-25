<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Mind (knowledge bases).
 *
 *   ai_minds          — labelled knowledge base owned by a user. The
 *                       built-in Sayzio default mind is owned by user_id
 *                       NULL and `is_default` true so it auto-attaches
 *                       to every account.
 *   ai_mind_sources   — polymorphic source (text / document / faq /
 *                       link / feature). Per-source ingestion status
 *                       and counters live here.
 *   ai_mind_chunks    — extracted text chunks + their embedding vector
 *                       (stored as JSON list<float>) plus a token count
 *                       so spend math stays accurate.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_minds', function (Blueprint $table) {
            $table->id();
            // Null = platform-managed mind (the Sayzio default).
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            // Built-in Sayzio default mind that auto-attaches to every user.
            $table->boolean('is_default')->default(false);
            // Admin abuse switch — a disabled mind cannot be queried or
            // ingested, but its contents are preserved.
            $table->boolean('is_disabled')->default(false);
            $table->string('disabled_reason', 500)->nullable();
            $table->unsignedInteger('chunks_count')->default(0);
            $table->unsignedInteger('sources_count')->default(0);
            $table->timestamp('last_ingested_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('is_default');
        });

        Schema::create('ai_mind_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mind_id')->constrained('ai_minds')->cascadeOnDelete();
            // text / document / faq / link / feature
            $table->string('type', 16);
            $table->string('title', 200);
            // Text body for `text`, FAQ JSON for `faq`, URL for `link`,
            // path for `document`, feature key for `feature`.
            $table->text('body')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('feature_key', 64)->nullable();
            $table->string('storage_disk', 32)->nullable();
            $table->string('storage_path', 500)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            // queued / processing / ready / failed / disabled
            $table->string('status', 16)->default('queued');
            $table->string('status_message', 500)->nullable();
            $table->unsignedInteger('chunks_count')->default(0);
            $table->unsignedInteger('refresh_minutes')->nullable();
            $table->timestamp('last_ingested_at')->nullable();
            $table->timestamp('next_refresh_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['mind_id', 'type']);
            $table->index(['type', 'next_refresh_at']);
            $table->index('status');
        });

        Schema::create('ai_mind_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mind_id')->constrained('ai_minds')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('ai_mind_sources')->cascadeOnDelete();
            $table->unsignedInteger('ord')->default(0);
            $table->text('content');
            $table->unsignedInteger('tokens')->default(0);
            // Stored as JSON list<float>. Postgres `jsonb` keeps this
            // compact and indexable for housekeeping queries; the
            // similarity scan is done in PHP for v1.
            $table->json('embedding')->nullable();
            $table->string('model', 64)->nullable();
            $table->timestamps();

            $table->index(['mind_id']);
            $table->index(['source_id', 'ord']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_mind_chunks');
        Schema::dropIfExists('ai_mind_sources');
        Schema::dropIfExists('ai_minds');
    }
};
