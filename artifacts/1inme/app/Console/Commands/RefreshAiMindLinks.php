<?php

namespace App\Console\Commands;

use App\Jobs\IngestAiMindSourceJob;
use App\Modules\User\Models\AiMindSource;
use App\Services\AI\AiMindSettings;
use Illuminate\Console\Command;

/**
 * Re-crawls every link source whose `next_refresh_at` has passed.
 * Capped per run by `max_link_refreshes_per_day` so a single tick of
 * the scheduler can't fan out across the whole platform.
 */
class RefreshAiMindLinks extends Command
{
    protected $signature = 'minds:refresh-links {--limit= : Override the per-run cap}';
    protected $description = 'Re-crawl AI Mind link sources whose refresh window has elapsed.';

    public function handle(): int
    {
        $cap = (int) ($this->option('limit') ?: AiMindSettings::cap('max_link_refreshes_per_day'));
        if ($cap <= 0) {
            $this->info('Link refresh disabled by admin cap.');
            return self::SUCCESS;
        }

        $due = AiMindSource::query()
            ->where('type', AiMindSource::TYPE_LINK)
            ->where('status', '!=', AiMindSource::STATUS_DISABLED)
            ->whereNotNull('next_refresh_at')
            ->where('next_refresh_at', '<=', now())
            ->whereHas('mind', fn($q) => $q->where('is_disabled', false))
            ->orderBy('next_refresh_at')
            ->limit($cap)
            ->pluck('id');

        foreach ($due as $id) {
            IngestAiMindSourceJob::dispatch((int) $id);
        }
        $this->info("Queued {$due->count()} link refreshes.");
        return self::SUCCESS;
    }
}
