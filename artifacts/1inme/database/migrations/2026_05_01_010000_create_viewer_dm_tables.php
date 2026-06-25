<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('viewer_dm_conversations')) {
            Schema::create('viewer_dm_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('viewer_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedSmallInteger('viewer_msg_count')->default(0);
                $table->unsignedSmallInteger('owner_msg_count')->default(0);
                $table->boolean('owner_replied')->default(false);
                $table->unsignedInteger('owner_unread_count')->default(0);
                $table->unsignedInteger('viewer_unread_count')->default(0);
                $table->string('status', 20)->default('active'); // active|blocked|archived
                $table->timestamp('blocked_at')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->string('last_message_preview', 240)->nullable();
                $table->string('last_sender', 8)->nullable(); // viewer|owner
                $table->timestamps();

                $table->unique(['link_id', 'viewer_user_id'], 'viewer_dm_unique_pair');
                $table->index(['owner_user_id', 'status', 'last_message_at'], 'viewer_dm_owner_idx');
                $table->index(['viewer_user_id', 'last_message_at'], 'viewer_dm_viewer_idx');
            });
        }

        if (!Schema::hasTable('viewer_dm_messages')) {
            Schema::create('viewer_dm_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('viewer_dm_conversations')->cascadeOnDelete();
                $table->string('sender_type', 8); // viewer|owner
                $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at'], 'viewer_dm_msg_idx');
            });
        }

        // Account-level block: owner can ban a viewer entirely so they cannot
        // start any future conversation across any of the owner's biolinks.
        if (!Schema::hasTable('viewer_dm_user_blocks')) {
            Schema::create('viewer_dm_user_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('viewer_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason', 255)->nullable();
                $table->timestamps();

                $table->unique(['owner_user_id', 'viewer_user_id'], 'viewer_dm_block_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('viewer_dm_user_blocks');
        Schema::dropIfExists('viewer_dm_messages');
        Schema::dropIfExists('viewer_dm_conversations');
    }
};
