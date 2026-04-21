<?php

namespace App\Console\Commands;

use App\Modules\User\Models\LinkClick;
use Illuminate\Console\Command;

/**
 * Backfill the `source` column on historical `link_clicks` rows that were
 * recorded before the LinkTrackingService started tagging it (task #379).
 *
 * Heuristic (applied only to rows where `source IS NULL`):
 *   - referrer IS NULL AND device_type IN ('mobile','tablet') AND
 *     browser IN ('Chrome','Safari','Other')   => 'mobile_app'
 *       Rationale: the in-app webview reports as mobile Chrome (Android) or
 *       mobile Safari (iOS) and never carries an HTTP referer. "Other" covers
 *       webview UAs we couldn't classify.
 *   - everything else                          => 'web'
 *
 * The command is idempotent: it never touches rows that already have a
 * non-null `source`, and re-running it is a no-op once completed. Work is
 * done in id-ordered chunks so it's safe on large tables.
 */
class BackfillClickSource extends Command
{
    protected $signature = 'clicks:backfill-source
        {--chunk=1000 : Rows scanned per batch}
        {--limit=0 : Optional cap on rows processed (0 = no cap)}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill link_clicks.source on historical rows using stored browser/device/referrer hints (mobile_app vs web).';

    public function handle(): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));
        $dry   = (bool) $this->option('dry-run');

        $base = fn() => LinkClick::query()->whereNull('source');

        $total = (clone $base())->count();
        if ($total === 0) {
            $this->info('Nothing to backfill — every link_clicks row already has a source.');
            return self::SUCCESS;
        }

        $cap = $limit > 0 ? min($total, $limit) : $total;
        $this->info("Backfilling source on {$cap} of {$total} null-source rows (chunk={$chunk})" . ($dry ? ' [dry-run]' : ''));

        $bar = $this->output->createProgressBar($cap);
        $bar->start();

        $processed = 0;
        $taggedMobile = 0;
        $taggedWeb = 0;

        $base()
            ->select('id', 'browser', 'device_type', 'referrer')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$processed, &$taggedMobile, &$taggedWeb, $cap, $dry, $bar) {
                $mobileIds = [];
                $webIds = [];

                foreach ($rows as $row) {
                    if ($processed >= $cap) break;

                    if ($this->looksLikeMobileApp($row->referrer, $row->device_type, $row->browser)) {
                        $mobileIds[] = $row->id;
                        $taggedMobile++;
                    } else {
                        $webIds[] = $row->id;
                        $taggedWeb++;
                    }

                    $processed++;
                    $bar->advance();
                }

                if (!$dry) {
                    if ($mobileIds) {
                        LinkClick::whereIn('id', $mobileIds)->update(['source' => 'mobile_app']);
                    }
                    if ($webIds) {
                        LinkClick::whereIn('id', $webIds)->update(['source' => 'web']);
                    }
                }

                return $processed < $cap;
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Processed: {$processed}. mobile_app: {$taggedMobile}, web: {$taggedWeb}" . ($dry ? ' [dry-run — no writes]' : '.'));

        return self::SUCCESS;
    }

    protected function looksLikeMobileApp(?string $referrer, ?string $deviceType, ?string $browser): bool
    {
        if ($referrer !== null && $referrer !== '') return false;
        if (!in_array($deviceType, ['mobile', 'tablet'], true)) return false;
        return in_array($browser, ['Chrome', 'Safari', 'Other'], true);
    }
}
