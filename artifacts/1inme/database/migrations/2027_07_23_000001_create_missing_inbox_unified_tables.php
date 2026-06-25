<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Heals edited-after-applied drift on the unified-inbox tables.
 *
 * 2026_11_25_000001_create_inbox_unified_tables originally created only
 * `inbox_threads` and was recorded as "Ran". It was later edited to also create
 * `inbox_messages`, `inbox_snippets` and `inbox_thread_conversions`. Laravel
 * never re-runs a recorded migration, so on long-lived databases that applied
 * the earlier version those three tables silently never landed — while
 * `migrate:status` keeps reporting 0 pending. Any page that reads them 500s, and
 * {@see \App\Modules\Common\Support\ExpectedSchemaHealth} (which replays the
 * migration files to derive the expected schema) correctly flags the three as
 * whole-missing tables.
 *
 * This migration re-applies ONLY the three missing tables, each guarded by
 * `Schema::hasTable()` so it is a no-op on any environment (fresh or already
 * healed) where they already exist, and fills the gap where they don't. The
 * column / index / foreign-key definitions are byte-for-byte identical to the
 * owning create migration so a repaired table matches what a fresh `migrate`
 * would have produced. `inbox_threads` is intentionally left alone (it already
 * exists everywhere and is the FK parent of these tables).
 */
return new class extends Migration {
    public function up(): void
    {
        // Saved messages timeline for each unified thread.
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
        // Additive repair only — leave the healed tables in place on rollback so
        // we never re-open the drift this migration exists to close.
    }
};
