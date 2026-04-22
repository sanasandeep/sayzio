<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Companions — placement-bound chatbots that wrap an AiPersonaAgent
 * and expose it as one of three "surfaces":
 *
 *   biolink — rendered as a floating launcher / inline chat block on
 *             the user's own 1INME biolink page.
 *   embed   — embedded on a third-party website via the public
 *             /embed/companion.js bundle (origin-allow-listed).
 *   inbox   — auto-reply bot inside the unified Inbox; opt-in per
 *             viewer-DM conversation, drafts the first response so the
 *             owner can edit-and-send (or auto-send if configured).
 *
 *   ai_companions               — the placement config row
 *   ai_companion_links          — biolink ↔ companion pivot (which
 *                                 biolinks render this companion)
 *   ai_companion_conversations  — visitor session
 *   ai_companion_messages       — turn log
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('ai_persona_agents')->cascadeOnDelete();
            // Public id used in embed code & public POST endpoint —
            // separate from the auto-incrementing id so the user can
            // safely paste it into untrusted HTML.
            $table->string('public_id', 32)->unique();
            $table->string('name', 120);
            $table->string('placement', 16); // biolink|embed|inbox
            // Visual + behaviour tunables (launcher color, position,
            // greeting bubble, etc.) — JSON so we can extend without
            // migrations.
            $table->json('config')->nullable();
            // External-embed allow-list. Empty = block all (for the
            // `embed` placement). Shape: ["example.com","app.example.com"].
            $table->json('allowed_domains')->nullable();
            // Free-tier turns the user gets per calendar month before
            // each turn starts charging their AI credit balance. Owners
            // can reduce this to 0 to make every turn billable.
            $table->unsignedInteger('free_turns_per_month')->default(50);
            // Hard monthly turn ceiling (0 = unlimited). Independent of
            // credits — protects the user against runaway spend if a
            // visitor floods the widget.
            $table->unsignedInteger('hard_cap_per_month')->default(2000);
            $table->boolean('is_disabled')->default(false);
            $table->string('disabled_reason', 500)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'placement']);
            $table->index('is_disabled');
        });

        Schema::create('ai_companion_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('companion_id')->constrained('ai_companions')->cascadeOnDelete();
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['companion_id', 'link_id'], 'ai_companion_link_unique');
            $table->index('link_id');
        });

        Schema::create('ai_companion_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('companion_id')->constrained('ai_companions')->cascadeOnDelete();
            // Anonymous visitor identifier (cookie / localStorage on
            // the embed side). Lets returning visitors resume a thread
            // without auth.
            $table->string('visitor_token', 64)->index();
            $table->string('visitor_name', 120)->nullable();
            $table->string('visitor_email', 200)->nullable();
            $table->string('visitor_ip', 64)->nullable();
            $table->string('visitor_ua', 255)->nullable();
            $table->string('source_origin', 200)->nullable(); // for embed placement
            $table->unsignedSmallInteger('rating')->nullable(); // 1..5 thumbs
            $table->unsignedInteger('turns_count')->default(0);
            $table->unsignedInteger('credits_spent')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['companion_id', 'last_message_at'], 'ai_comp_conv_recent_idx');
        });

        Schema::create('ai_companion_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_companion_conversations')->cascadeOnDelete();
            $table->string('role', 16); // user|assistant|system
            $table->text('content');
            $table->json('citations')->nullable();
            $table->unsignedInteger('credits_spent')->default(0);
            $table->unsignedSmallInteger('rating')->nullable(); // per-turn thumbs
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'ai_comp_msg_idx');
        });

        // Inbox bot opt-in: per viewer-DM conversation a Companion can
        // be set so the owner's first reply is drafted automatically.
        Schema::table('viewer_dm_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('auto_reply_companion_id')->nullable()->after('status');
            $table->index('auto_reply_companion_id', 'viewer_dm_auto_reply_idx');
        });
        // Mark auto-replied messages so the inbox UI can badge them
        // without having to join a second table on every render.
        Schema::table('viewer_dm_messages', function (Blueprint $table) {
            $table->boolean('is_ai')->default(false)->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('viewer_dm_messages', function (Blueprint $table) {
            $table->dropColumn('is_ai');
        });
        Schema::table('viewer_dm_conversations', function (Blueprint $table) {
            $table->dropIndex('viewer_dm_auto_reply_idx');
            $table->dropColumn('auto_reply_companion_id');
        });
        Schema::dropIfExists('ai_companion_messages');
        Schema::dropIfExists('ai_companion_conversations');
        Schema::dropIfExists('ai_companion_links');
        Schema::dropIfExists('ai_companions');
    }
};
