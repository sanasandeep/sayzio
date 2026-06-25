<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Native reviews left by public visitors (or the creator) on a
        // biolink / standalone reviews page. Scoped to the creator (user_id)
        // so a "Reviews" block can aggregate every review for that creator,
        // optionally narrowed to the originating link.
        if (!Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('link_id')->nullable()->constrained('links')->nullOnDelete();
                $t->string('author_name')->nullable();
                $t->string('author_email')->nullable();
                $t->string('author_avatar', 1024)->nullable();
                $t->unsignedTinyInteger('rating')->nullable();   // 1..5, null for question-only
                $t->text('body')->nullable();
                // pending | approved | hidden
                $t->string('status', 16)->default('pending');
                $t->text('reply')->nullable();
                $t->timestamp('replied_at')->nullable();
                $t->boolean('is_pinned')->default(false);
                $t->boolean('is_spam')->default(false);
                $t->string('spam_reason')->nullable();
                $t->string('ip_hash', 64)->nullable();
                $t->string('fingerprint', 64)->nullable();
                $t->timestamps();

                $t->index(['user_id', 'status']);
                $t->index(['link_id', 'status']);
                $t->index(['user_id', 'rating']);
            });
        }

        // Media attachments for a native review (image / audio / video).
        if (!Schema::hasTable('review_media')) {
            Schema::create('review_media', function (Blueprint $t) {
                $t->id();
                $t->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
                $t->string('type', 16);              // image | audio | video
                $t->string('url', 1024);
                $t->json('meta')->nullable();        // {mime, size, duration, ...}
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();

                $t->index('review_id');
            });
        }

        // Per-creator (optionally per-link) custom review prompts the visitor
        // answers when leaving a structured / question-based review.
        if (!Schema::hasTable('review_questions')) {
            Schema::create('review_questions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('link_id')->nullable()->constrained('links')->nullOnDelete();
                $t->string('prompt');
                // text | rating | choice
                $t->string('type', 16)->default('text');
                $t->json('options')->nullable();     // for choice
                $t->boolean('is_required')->default(false);
                $t->boolean('is_active')->default(true);
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();

                $t->index(['user_id', 'is_active']);
            });
        }

        // A visitor's answer to a review question, tied to the submitted review.
        if (!Schema::hasTable('review_answers')) {
            Schema::create('review_answers', function (Blueprint $t) {
                $t->id();
                $t->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
                $t->foreignId('question_id')->nullable()->constrained('review_questions')->nullOnDelete();
                $t->string('prompt');                // snapshot of the prompt text
                $t->text('answer')->nullable();
                $t->timestamps();

                $t->index('review_id');
            });
        }

        // A creator's connection to a 3rd-party review provider (Google,
        // Trustpilot, ...). Credentials live in env/secrets; this row only
        // holds non-secret config + sync state.
        if (!Schema::hasTable('review_providers')) {
            Schema::create('review_providers', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('provider', 32);          // google | trustpilot | ...
                $t->string('external_ref')->nullable(); // place id / business unit id
                // connected | preview | error | disconnected
                $t->string('status', 16)->default('preview');
                $t->string('status_reason')->nullable();
                $t->json('settings')->nullable();
                $t->timestamp('last_synced_at')->nullable();
                $t->timestamps();

                $t->unique(['user_id', 'provider']);
            });
        }

        // Reviews imported from a 3rd-party provider, normalized and deduped.
        if (!Schema::hasTable('external_reviews')) {
            Schema::create('external_reviews', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('provider_id')->nullable()->constrained('review_providers')->nullOnDelete();
                $t->string('provider', 32);
                $t->string('source_id')->nullable();
                $t->string('dedup_key', 191);
                $t->string('author_name')->nullable();
                $t->string('author_avatar', 1024)->nullable();
                $t->unsignedTinyInteger('rating')->nullable();
                $t->text('body')->nullable();
                $t->string('source_url', 1024)->nullable();
                $t->json('payload')->nullable();
                $t->timestamp('reviewed_at')->nullable();
                $t->timestamps();

                $t->unique('dedup_key');
                $t->index(['user_id', 'provider']);
                $t->index(['user_id', 'rating']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_reviews');
        Schema::dropIfExists('review_providers');
        Schema::dropIfExists('review_answers');
        Schema::dropIfExists('review_questions');
        Schema::dropIfExists('review_media');
        Schema::dropIfExists('reviews');
    }
};
