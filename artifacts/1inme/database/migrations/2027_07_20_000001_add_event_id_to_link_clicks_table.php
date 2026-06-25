<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add an idempotency key to link_clicks so the async write path
 * (PersistLinkClicksJob) can insertOrIgnore each row exactly once even when a
 * queued batch is retried or re-delivered. The column is nullable (existing
 * rows + any legacy synchronous writer leave it null) and carries a PARTIAL
 * unique index so only non-null event ids are deduplicated — null rows never
 * collide.
 *
 * Additive and shared-DB-safe: a single nullable column + one partial unique
 * index, both fully guarded so re-running the migration (or running it against
 * an environment that already has them) is a no-op.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('link_clicks')) {
            return;
        }

        if (!Schema::hasColumn('link_clicks', 'event_id')) {
            Schema::table('link_clicks', function (Blueprint $table) {
                $table->uuid('event_id')->nullable()->after('id');
            });
        }

        // Partial unique index — only enforces uniqueness on rows that carry an
        // event id, so historical/legacy null rows are unaffected. Created via
        // raw SQL because Laravel's schema builder can't express a WHERE clause
        // on an index. IF NOT EXISTS keeps the migration idempotent.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS link_clicks_event_id_unique '
            . 'ON link_clicks (event_id) WHERE event_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('link_clicks')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS link_clicks_event_id_unique');

        if (Schema::hasColumn('link_clicks', 'event_id')) {
            Schema::table('link_clicks', function (Blueprint $table) {
                $table->dropColumn('event_id');
            });
        }
    }
};
