<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short-lived conversation memory for the two-way WhatsApp AI agent
 * (Task #2759). One row per (user, WhatsApp phone) keeps a rolling
 * message history so multi-turn flows ("create a short link" → "what
 * URL?") work, plus a pending bucket for media (image/file/voice
 * transcript) the user sent ahead of the instruction that uses it.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_agent_conversations')) return;

        Schema::create('whatsapp_agent_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // E.164-ish WhatsApp sender id (digits only, as Meta delivers it).
            $table->string('wa_phone', 32)->index();
            // Rolling [{role, content}] window passed back to the model.
            $table->json('history')->nullable();
            // Media the user sent that the next instruction may reference:
            // [{kind:image|file|audio_transcript, url|text, name?}].
            $table->json('pending')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'wa_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_agent_conversations');
    }
};
