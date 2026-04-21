<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\ChannelClassifier;
use App\Modules\User\Models\LinkClick;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Re-run the current ChannelClassifier against the stored user_agent of
 * historical link_clicks rows, overwriting the cached `channel` column
 * wherever the classifier now produces a different label.
 *
 * The original one-shot backfill ran inside the migration that introduced
 * the column, but the classifier evolves over time (new in-app webview
 * markers, vendor UA changes), so we need a reusable command to refresh
 * stale labels on demand. The command is safe to re-run: rows that
 * already match the current classification are skipped.
 *
 * Usage:
 *   php artisan link-clicks:reclassify-channels
 *   php artisan link-clicks:reclassify-channels --from=2026-01-01 --to=2026-03-01
 *   php artisan link-clicks:reclassify-channels --chunk=5000 --dry-run
 */
class ReclassifyLinkClickChannels extends Command
{
    protected $signature = 'link-clicks:reclassify-channels
        {--from= : Only reclassify rows whose clicked_at is on/after this date (YYYY-MM-DD or any parsable timestamp)}
        {--to= : Only reclassify rows whose clicked_at is on/before this date}
        {--chunk=2000 : Rows scanned per batch}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Re-run the current ChannelClassifier against stored user_agent values and update the cached channel column where it has drifted.';

    public function handle(): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $dry   = (bool) $this->option('dry-run');

        try {
            $from = $this->parseDate($this->option('from'));
            // For date-only --to inputs (YYYY-MM-DD), expand to end-of-day so
            // the boundary day is fully included rather than cut off at 00:00.
            $to   = $this->parseDate($this->option('to'), endOfDayIfDateOnly: true);
        } catch (\Throwable $e) {
            $this->error('Invalid --from/--to value: ' . $e->getMessage());
            return self::INVALID;
        }

        // Use withBots() so the bot-exclusion global scope on the LinkClick
        // model doesn't hide bot rows from us — bots are a valid channel
        // (KEY_BOT) the classifier produces, and stale bot rows should be
        // refreshed too.
        $base = function () use ($from, $to) {
            $q = LinkClick::withBots()->whereNotNull('user_agent');
            if ($from) $q->where('clicked_at', '>=', $from);
            if ($to)   $q->where('clicked_at', '<=', $to);
            return $q;
        };

        $total = (clone $base())->count();
        if ($total === 0) {
            $this->info('No rows match the given range — nothing to reclassify.');
            return self::SUCCESS;
        }

        $range = ($from ? $from->toDateTimeString() : '-∞') . ' .. ' . ($to ? $to->toDateTimeString() : '+∞');
        $this->info("Reclassifying up to {$total} rows (range: {$range}, chunk={$chunk})" . ($dry ? ' [dry-run]' : ''));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $scanned = 0;
        $updated = 0;
        $unchanged = 0;

        $base()
            ->select('id', 'user_agent', 'channel')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$scanned, &$updated, &$unchanged, $dry, $bar) {
                $byChannel = [];

                foreach ($rows as $row) {
                    $scanned++;
                    $bar->advance();

                    $now = ChannelClassifier::classify($row->user_agent);
                    if ($now === $row->channel) {
                        $unchanged++;
                        continue;
                    }

                    $byChannel[$now][] = $row->id;
                    $updated++;
                }

                if (!$dry) {
                    foreach ($byChannel as $channel => $ids) {
                        // withBots() so updates also reach bot rows.
                        LinkClick::withBots()->whereIn('id', $ids)->update(['channel' => $channel]);
                    }
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Scanned: {$scanned}. Updated: {$updated}. Unchanged: {$unchanged}" . ($dry ? ' [dry-run — no writes].' : '.'));

        return self::SUCCESS;
    }

    protected function parseDate(?string $value, bool $endOfDayIfDateOnly = false): ?Carbon
    {
        if ($value === null || $value === '') return null;
        $parsed = Carbon::parse($value);
        // If the user passed a bare YYYY-MM-DD (no time component), Carbon
        // gives us 00:00:00 — for upper bounds that means "everything before
        // this day", which is rarely what someone typing --to=2026-04-30 means.
        if ($endOfDayIfDateOnly && preg_match('~^\d{4}-\d{2}-\d{2}$~', trim($value))) {
            $parsed = $parsed->endOfDay();
        }
        return $parsed;
    }
}
