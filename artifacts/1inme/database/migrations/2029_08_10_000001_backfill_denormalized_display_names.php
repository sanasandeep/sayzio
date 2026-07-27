<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time backfill: rewrite stale denormalized display-name copies
 * (block comments, community rosters, fan points, subscriber entries,
 * internally-linked contacts) from the current users.name. Corrects
 * renames that happened before the automatic name-sync existed.
 *
 * Set-based JOIN updates (one statement per table, fast over RDS) shared
 * with the `users:sync-display-names` artisan command. Idempotent and
 * additive-only — never touches NULL/empty snapshots (anonymous choices)
 * or Google-synced contacts. Guarded per-table so a partially-migrated
 * schema can never fail the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        foreach (\App\Console\Commands\SyncDisplayNames::statements() as [$table, $label, $countSql, $updateSql]) {
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
