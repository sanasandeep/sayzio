<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\BotDetector;
use App\Modules\User\Models\LinkClick;
use Illuminate\Console\Command;

/**
 * Backfill the `is_bot` flag on historical `link_clicks` rows that were
 * recorded before the BotDetector started tagging them at write time.
 *
 * Re-evaluates each row's stored `user_agent` through the BotDetector and
 * flips `is_bot` to true where appropriate. Rows already flagged as bots
 * are left alone, so re-running is a no-op once completed.
 *
 * Work is done in id-ordered chunks so it's safe on large tables. After a
 * non-dry run that flipped any rows, optionally re-runs
 * `analytics:recount-link-stats` so the cached counters re-align.
 */
class BackfillClickIsBot extends Command
{
    protected $signature = 'clicks:backfill-is-bot
        {--chunk=1000 : Rows scanned per batch}
        {--limit=0 : Optional cap on rows processed (0 = no cap)}
        {--dry-run : Report what would change without writing}
        {--recount : After backfilling, re-run analytics:recount-link-stats}';

    protected $description = 'Re-evaluate user_agent on historical link_clicks rows and flag bot/scraper hits via BotDetector.';

    public function handle(BotDetector $detector): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));
        $dry   = (bool) $this->option('dry-run');

        $base = fn() => LinkClick::query()->where('is_bot', false);

        $total = (clone $base())->count();
        if ($total === 0) {
            $this->info('Nothing to backfill — no non-bot rows to re-evaluate.');
            return self::SUCCESS;
        }

        $cap = $limit > 0 ? min($total, $limit) : $total;
        $this->info("Scanning {$cap} of {$total} non-bot rows (chunk={$chunk})" . ($dry ? ' [dry-run]' : ''));

        $bar = $this->output->createProgressBar($cap);
        $bar->start();

        $processed = 0;
        $flipped   = 0;

        $base()
            ->select('id', 'user_agent')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$processed, &$flipped, $cap, $dry, $detector, $bar) {
                $botIds = [];

                foreach ($rows as $row) {
                    if ($processed >= $cap) break;

                    if ($detector->isBot($row->user_agent)) {
                        $botIds[] = $row->id;
                        $flipped++;
                    }

                    $processed++;
                    $bar->advance();
                }

                if (!$dry && $botIds) {
                    LinkClick::whereIn('id', $botIds)->update(['is_bot' => true]);
                }

                return $processed < $cap;
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Processed: {$processed}. Flagged as bot: {$flipped}" . ($dry ? ' [dry-run — no writes]' : '.'));

        if (!$dry && $flipped > 0 && $this->option('recount')) {
            $this->info('Re-running analytics:recount-link-stats to refresh cached counters…');
            $this->call('analytics:recount-link-stats');
        }

        return self::SUCCESS;
    }
}
