<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbox Agent (AI) columns on inbox_threads. Additive + idempotent: every
 * column is hasColumn-guarded so the migration is safe to (re-)run against
 * the shared RDS even if a prior run was interrupted.
 *
 *  - summary         : AI one-line thread summary (cheap list reads)
 *  - priority        : low|normal|high|urgent (triage signal)
 *  - triage_source   : rule|ai  (how category/priority/summary were set)
 *  - ai_draft        : last AI-drafted reply awaiting review/send
 *  - ai_draft_at     : when the draft was generated
 *  - autopilot_state : null|sent|review|skipped (autopilot decision)
 *  - sent_by_ai      : a reply was dispatched autonomously by the agent
 *  - ai_handled_at   : when the agent last acted on the thread
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inbox_threads')) {
            return;
        }

        Schema::table('inbox_threads', function (Blueprint $table) {
            if (!Schema::hasColumn('inbox_threads', 'summary')) {
                $table->string('summary', 500)->nullable()->after('preview');
            }
            if (!Schema::hasColumn('inbox_threads', 'priority')) {
                $table->string('priority', 8)->nullable()->after('category_source');
            }
            if (!Schema::hasColumn('inbox_threads', 'triage_source')) {
                $table->string('triage_source', 12)->nullable()->after('priority');
            }
            if (!Schema::hasColumn('inbox_threads', 'ai_draft')) {
                $table->text('ai_draft')->nullable()->after('triage_source');
            }
            if (!Schema::hasColumn('inbox_threads', 'ai_draft_at')) {
                $table->timestamp('ai_draft_at')->nullable()->after('ai_draft');
            }
            if (!Schema::hasColumn('inbox_threads', 'autopilot_state')) {
                $table->string('autopilot_state', 16)->nullable()->after('ai_draft_at');
            }
            if (!Schema::hasColumn('inbox_threads', 'sent_by_ai')) {
                $table->boolean('sent_by_ai')->default(false)->after('autopilot_state');
            }
            if (!Schema::hasColumn('inbox_threads', 'ai_handled_at')) {
                $table->timestamp('ai_handled_at')->nullable()->after('sent_by_ai');
            }
        });

        // Helps the manual-review queue filter (workspace + state) without a
        // full scan. Guarded so re-runs don't 23505/42P07.
        $this->ensureIndex(
            'inbox_threads',
            'inbox_threads_workspace_autopilot_idx',
            ['workspace_id', 'autopilot_state'],
        );
    }

    public function down(): void
    {
        // Intentionally a no-op: shared-DB migrations are additive-only.
    }

    private function ensureIndex(string $table, string $name, array $columns): void
    {
        try {
            $exists = collect(Schema::getIndexes($table))
                ->contains(fn ($i) => ($i['name'] ?? null) === $name);
            if ($exists) {
                return;
            }
        } catch (\Throwable $e) {
            // Older drivers may not support getIndexes(); fall through to a
            // guarded create below.
        }

        try {
            Schema::table($table, function (Blueprint $t) use ($name, $columns) {
                $t->index($columns, $name);
            });
        } catch (\Throwable $e) {
            // Index already exists / race — safe to ignore.
        }
    }
};
