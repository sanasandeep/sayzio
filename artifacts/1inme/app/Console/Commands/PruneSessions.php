<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune expired rows from the database-backed sessions table.
 *
 * With SESSION_DRIVER=database every browser login writes a row to
 * `sessions`. Laravel's built-in probabilistic GC (the session `lottery`)
 * would prune them, but on a distant RDS each sweep is a blocking DELETE
 * that taxes whichever unlucky request triggers it — so the lottery is
 * disabled in config/session.php and this nightly command owns cleanup.
 *
 * Expiry mirrors the framework's own rule: a row whose `last_activity`
 * (unix epoch) is older than `session.lifetime` minutes is dead. Deletes
 * are id-keyed and chunked (Postgres has no DELETE ... LIMIT) with a
 * --max-batches cap so one run can never mass-delete unexpectedly.
 */
class PruneSessions extends Command
{
    protected $signature = 'sessions:prune
        {--chunk=1000 : Rows to delete per batch (keeps each statement small)}
        {--max-batches=5000 : Safety cap on batches per run}
        {--dry-run : Report what would happen without deleting anything}';

    protected $description = 'Delete session rows idle longer than session.lifetime. Chunked + capped.';

    public function handle(): int
    {
        $dry        = (bool) $this->option('dry-run');
        $chunk      = max(1, (int) $this->option('chunk'));
        $maxBatches = max(1, (int) $this->option('max-batches'));

        $connection = config('session.connection');
        $table      = config('session.table', 'sessions');
        $lifetime   = max(1, (int) config('session.lifetime', 120));

        if (!Schema::connection($connection)->hasTable($table)) {
            $this->warn("{$table} table does not exist yet — nothing to prune.");
            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes($lifetime)->getTimestamp();

        if ($dry) {
            $count = (int) DB::connection($connection)->table($table)
                ->where('last_activity', '<', $cutoff)
                ->count();
            $this->line("Dry run: ~{$count} session row(s) idle longer than {$lifetime} minute(s) would be deleted.");
            return self::SUCCESS;
        }

        $total = 0;
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $ids = DB::connection($connection)->table($table)
                ->where('last_activity', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $total += DB::connection($connection)->table($table)->whereIn('id', $ids)->delete();

            if (count($ids) < $chunk) {
                break;
            }
        }

        $this->info("Deleted {$total} expired session row(s) (idle > {$lifetime} minute(s)).");

        return self::SUCCESS;
    }
}
