<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\LinkHealthChecker;
use Illuminate\Console\Command;

/**
 * Probe every "Link Insurance"-enabled link whose next check is due,
 * record the result, and run the failover/restore decision logic.
 *
 * Scheduled every 5 minutes from routes/console.php — links can opt
 * into a longer cadence (15/30/60/240 min) which the {@see
 * LinkHealthChecker::dueLinks()} query honours.
 */
class CheckLinkHealth extends Command
{
    protected $signature = 'links:check-health
                            {--link= : Probe a single link by id, ignoring cadence}
                            {--limit=500 : Hard cap on the number of links processed in one run}';

    protected $description = 'Probe Link Insurance-enabled destinations and run failover/restore';

    public function handle(LinkHealthChecker $checker): int
    {
        $limit  = (int) $this->option('limit');
        $linkId = $this->option('link');

        if ($linkId) {
            $link = \App\Modules\User\Models\Link::withoutGlobalScopes()->find($linkId);
            if (!$link) {
                $this->error("Link #{$linkId} not found");
                return self::FAILURE;
            }
            $checker->checkLink($link);
            $checker->recheckPrimaryFromFailover($link);
            $this->info("Probed link #{$linkId}");
            return self::SUCCESS;
        }

        $count = 0;
        // Iterate without the workspace global scope so the scheduler
        // (running outside any HTTP request) sees every workspace's
        // links — otherwise the BelongsToWorkspace scope would silently
        // return zero rows and probes would never fire.

        foreach ($checker->dueLinks() as $link) {
            try {
                $checker->checkLink($link);
                $checker->recheckPrimaryFromFailover($link);
            } catch (\Throwable $e) {
                $this->error("Link #{$link->id} probe failed: {$e->getMessage()}");
            }
            $count++;
            if ($count >= $limit) break;
        }

        $this->info("Probed {$count} link(s)");
        return self::SUCCESS;
    }
}
