<?php

namespace App\Console\Commands;

use App\Modules\Common\Models\SiteAssistantCutoffAlert;
use Illuminate\Console\Command;

/**
 * Delete site_assistant_cutoff_alerts rows older than the retention
 * window. The analytics dashboard only ever shows the most recent
 * handful of alerts, so older rows are dead weight — without this
 * sweep the table would grow forever as alerts keep firing.
 */
class PruneSiteAssistantCutoffAlerts extends Command
{
    protected $signature = 'site-assistant:prune-cutoff-alerts
        {--days=90 : Delete alert rows older than this many days}';

    protected $description = 'Delete site-assistant cut-off alert history rows older than the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = SiteAssistantCutoffAlert::query()
            ->where('dispatched_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} site-assistant cut-off alert row(s) older than {$days} day(s).");
        return self::SUCCESS;
    }
}
