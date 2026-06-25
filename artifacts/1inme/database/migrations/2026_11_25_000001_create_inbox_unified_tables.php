<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Unified, polymorphic thread index across every inbox source.
        // Each row points at the original record (form submission, subscriber,
        // viewer DM conversation, etc.) so existing data flows keep working,
        // but the unified inbox queries this single table for triage / filters
        // / SLA / assignment.
        if (!Schema::hasTable('inbox_threads')) {
            Schema::create('inbox_threads', function (Blueprint $t) {
                $t->id();
                $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $t->unsignedBigInteger('user_id'); // workspace owner (denormalized for quick scoping)
                $t->string('source_type', 32);     // form_submission|subscriber|viewer_dm|sponsorship|email
                $t->unsignedBigInteger('source_id');
                $t->string('channel', 32)->nullable(); // instagram|tiktok|x|email|form|biolink_dm|sponsorship
                $t->string('subject', 300)->nullable();
                $t->string('preview', 500)->nullable();
                $t->string('sender_name', 200)->nullable();
                $t->string('sender_email', 200)->nullable();
                $t->string('sender_handle', 200)->nullable();
                $t->string('sender_avatar', 500)->nullable();
                $t->string('category', 24)->default('lead');         // lead|fan|sponsorship|support|spam
                $t->float('category_confidence')->nullable();
                $t->string('category_source', 12)->default('auto');  // auto|manual
                $t->unsignedBigInteger('assignee_user_id')->nullable();
                $t->string('status', 16)->default('open');           // open|archived|snoozed
                $t->timestamp('sla_due_at')->nullable();
                $t->boolean('sla_overdue_notified')->default(false);
                $t->timestamp('last_message_at')->nullable();
                $t->string('last_sender', 8)->nullable();            // in|out
                $t->boolean('is_starred')->default(false);
                $t->boolean('is_read')->default(false);
                $t->unsignedSmallInteger('unread_count')->default(0);
                $t->json('meta')->nullable();
                $t->timestamps();

                $t->unique(['source_type', 'source_id'], 'inbox_threads_source_unique');
                $t->index(['workspace_id', 'status', 'last_message_at'], 'inbox_threads_ws_idx');
                $t->index(['workspace_id', 'category'], 'inbox_threads_ws_cat_idx');
                $t->index(['workspace_id', 'sla_due_at'], 'inbox_threads_ws_sla_idx');
                $t->index(['assignee_user_id', 'status'], 'inbox_threads_assignee_idx');
            });
        }

        if (!Schema::hasTable('inbox_messages')) {
            Schema::create('inbox_messages', function (Blueprint $t) {
                $t->id();
                $t->foreignId('thread_id')->constrained('inbox_threads')->cascadeOnDelete();
                $t->string('direction', 4); // in|out
                $t->string('sender_name', 200)->nullable();
                $t->string('sender_handle', 200)->nullable();
                $t->unsignedBigInteger('sender_user_id')->nullable();
                $t->text('body');
                $t->timestamp('sent_at')->nullable();
                $t->string('external_id', 200)->nullable();
                $t->json('meta')->nullable();
                $t->timestamps();

                $t->index(['thread_id', 'sent_at'], 'inbox_messages_thread_idx');
            });
        }

        // Saved snippets / shortcuts insertable into any reply ("/intro").
        if (!Schema::hasTable('inbox_snippets')) {
            Schema::create('inbox_snippets', function (Blueprint $t) {
                $t->id();
                $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $t->unsignedBigInteger('created_by_user_id')->nullable();
                $t->string('shortcut', 64);
                $t->string('label', 200);
                $t->text('body');
                $t->timestamps();

                $t->index(['workspace_id', 'shortcut'], 'inbox_snippets_ws_idx');
            });
        }

        // Audit / back-link of every one-click conversion (kanban card,
        // contact, vault client, calendar event) created from a thread.
        if (!Schema::hasTable('inbox_thread_conversions')) {
            Schema::create('inbox_thread_conversions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('thread_id')->constrained('inbox_threads')->cascadeOnDelete();
                $t->string('kind', 16); // kanban|contact|vault|calendar
                $t->unsignedBigInteger('target_id');
                $t->unsignedBigInteger('created_by_user_id')->nullable();
                $t->json('meta')->nullable();
                $t->timestamps();

                $t->index(['thread_id', 'kind'], 'inbox_thread_conv_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_thread_conversions');
        Schema::dropIfExists('inbox_snippets');
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('inbox_threads');
    }
};
