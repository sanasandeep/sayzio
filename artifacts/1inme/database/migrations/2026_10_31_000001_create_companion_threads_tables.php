<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companion chat persistence.
 *
 *   companion_threads   — one row per saved chat (per user, per workspace).
 *                         Holds the human-friendly title and the timestamp
 *                         of the most recent message for cheap sidebar
 *                         ordering.
 *   companion_messages  — append-only turns belonging to a thread. We keep
 *                         the JSON `meta` column so per-turn details
 *                         (credits spent, token counts, model) survive a
 *                         later UI revamp without a schema migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('companion_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Nullable so a user without an active workspace context (rare,
            // but possible during sign-up flows) can still chat.
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('title', 120)->default('New conversation');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'workspace_id', 'last_message_at']);
        });

        Schema::create('companion_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('companion_threads')->cascadeOnDelete();
            // 'user' | 'assistant' (system prompts are injected at runtime,
            // not stored, so they can be tuned without rewriting history).
            $table->string('role', 16);
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companion_messages');
        Schema::dropIfExists('companion_threads');
    }
};
