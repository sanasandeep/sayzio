<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\DomainHealthChecker;
use App\Modules\User\Models\Domain;
use Illuminate\Console\Command;

/**
 * Probe verified user-owned custom domains, detect DNS drift, and
 * trigger notifications / auto-unverification per the configured grace
 * window. See {@see DomainHealthChecker} for the state machine.
 *
 * Scheduled hourly from routes/console.php.
 */
class CheckDomainHealth extends Command
{
    protected $signature = 'domains:check-health
                            {--domain= : Probe a single domain by id, ignoring cadence}
                            {--limit=500 : Hard cap on the number of domains processed in one run}';

    protected $description = 'Probe verified custom domains for DNS drift and run the takeover-protection state machine';

    public function handle(DomainHealthChecker $checker): int
    {
        $limit    = (int) $this->option('limit');
        $domainId = $this->option('domain');

        if ($domainId) {
            $domain = Domain::find($domainId);
            if (!$domain) {
                $this->error("Domain #{$domainId} not found");
                return self::FAILURE;
            }
            if (!$domain->user_id || !$domain->is_verified) {
                $this->warn("Domain #{$domainId} is not a verified user-owned domain — skipping");
                return self::SUCCESS;
            }
            $status = $checker->checkDomain($domain);
            $this->info("Probed #{$domainId} {$domain->domain} → {$status}");
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($checker->dueDomains($limit) as $domain) {
            try {
                $checker->checkDomain($domain);
            } catch (\Throwable $e) {
                $this->error("Domain #{$domain->id} probe failed: {$e->getMessage()}");
            }
            $count++;
        }

        $this->info("Probed {$count} domain(s)");
        return self::SUCCESS;
    }
}
