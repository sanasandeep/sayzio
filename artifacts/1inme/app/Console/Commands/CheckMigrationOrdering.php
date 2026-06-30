<?php

namespace App\Console\Commands;

use App\Modules\Common\Support\MigrationOrderInspector;
use Illuminate\Console\Command;

/**
 * Fail the build when a migration references (modifies or foreign-keys) a table
 * that is only created by a later-dated migration. Such a migration builds fine
 * against any database that already has the table — the shared dev/live RDS,
 * where migrations were applied incrementally — but breaks `migrate:fresh` from
 * an empty database. This command surfaces that ordering bug statically, with no
 * database required (see {@see MigrationOrderInspector}), so it can run as a
 * pre-merge validation step where the only reachable database is the shared RDS
 * (which must never be wiped by `migrate:fresh`).
 *
 * Exit codes:
 *   0 — every migration's table/FK references resolve to an earlier migration.
 *   1 — at least one ordering violation, each naming the offending migration.
 *   2 — the inspector could not run (migration files unreadable, etc.).
 */
class CheckMigrationOrdering extends Command
{
    protected $signature = 'db:check-migration-ordering';

    protected $description = 'Replay all migrations in order (no database) and fail on a table/FK reference to a later-created table.';

    public function handle(): int
    {
        $result = MigrationOrderInspector::inspect();

        if (! ($result['available'] ?? false)) {
            $this->error('Could not inspect migration ordering: ' . ($result['error'] ?? 'unknown error'));

            return 2;
        }

        $violations = $result['violations'];

        if (empty($violations)) {
            $this->info("OK — replayed {$result['scanned']} migration(s) from an empty schema; every table/foreign-key reference resolves to an earlier migration.");

            return self::SUCCESS;
        }

        $count = count($violations);
        $this->error("Broken migration ordering — {$count} reference(s) to a table created only by a later migration:");
        $this->newLine();

        foreach ($violations as $v) {
            $this->line("  <fg=yellow>{$v['migration']}</> [{$v['type']}]");
            $this->line("    {$v['message']}");
            $this->newLine();
        }

        $this->error('A fresh `php artisan migrate` from an empty database would fail. Reorder the offending migration(s) above.');

        return self::FAILURE;
    }
}
