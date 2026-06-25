<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistence for the data-aware "Ask Coach" chatbot.
 *
 *   ask_coach_threads   — one chat history per user (per workspace).
 *   ask_coach_messages  — append-only turns; meta JSON carries the
 *                         tool snapshots, citations, deep-link actions
 *                         and inline insight payload the renderer needs.
 *   ask_coach_messages.feedback — thumbs up/down on assistant turns,
 *                         used by the admin quality-loop report.
 *
 * Kept separate from companion_* on purpose: Coach is the platform-
 * managed self-support persona with a different runtime, separate
 * admin reporting, and its own per-plan kill switch.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ask_coach_threads')) {
            Schema::create('ask_coach_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->string('title', 160)->default('New chat');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'workspace_id', 'last_message_at']);
            });
        }

        if (!Schema::hasTable('ask_coach_messages')) {
            Schema::create('ask_coach_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')->constrained('ask_coach_threads')->cascadeOnDelete();
                // 'user' | 'assistant'
                $table->string('role', 16);
                $table->text('content');
                // meta is the kitchen sink: credits_spent, model, tools_used,
                // citations[], insights[], actions[], etc. Keeping it JSON
                // lets us evolve the renderer without schema churn.
                $table->json('meta')->nullable();
                // Quality-loop signal: 'up' / 'down' / null on assistant turns.
                $table->string('feedback', 8)->nullable();
                $table->string('feedback_note', 500)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['thread_id', 'id']);
                $table->index(['feedback']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ask_coach_messages');
        Schema::dropIfExists('ask_coach_threads');
    }
};
