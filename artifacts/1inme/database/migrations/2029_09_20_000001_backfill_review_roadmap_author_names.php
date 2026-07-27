<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time backfill for the two surfaces added to the display-name sync
 * after the original backfill ran: roadmap comment author names
 * (viewer_user_id linkage) and native review author names (matched by the
 * reviewer's email). Corrects renames that happened before these tables
 * joined the automatic fan-out.
 *
 * Reuses the shared statements from the `users:sync-display-names`
 * command, filtered to just the new tables. Idempotent and additive-only —
 * NULL/empty snapshots (anonymous choices) are never touched. Guarded
 * per-table so a partially-migrated schema can never fail the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        foreach (\App\Console\Commands\SyncDisplayNames::statements() as [$table, $label, $countSql, $updateSql]) {
            if (!in_array($table, ['roadmap_comments', 'reviews'], true)) {
                continue;
            }
            if (!Schema::hasTable($table)) {
                continue;
            }
            try {
                DB::update($updateSql);
            } catch (\Throwable $e) {
                // Backfill is best-effort; the artisan command can re-run it.
                logger()->warning("Display-name backfill skipped for {$table}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // Name syncing is non-destructive; there is no meaningful rollback.
    }
};
