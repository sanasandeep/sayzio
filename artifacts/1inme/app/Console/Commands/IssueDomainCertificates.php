<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\SslCertificateIssuer;
use App\Modules\User\Models\Domain;
use Illuminate\Console\Command;

/**
 * Issue HTTPS certificates for verified domains that don't have one yet
 * (customer custom domains + admin global domains). Delegates the actual
 * certbot/nginx work to {@see SslCertificateIssuer} which shells out to the
 * EC2 kit's root helper. No-op unless `domains.ssl.auto_issue` is enabled
 * (SSL_AUTO_ISSUE=true — EC2 only; Replit's proxy terminates TLS itself).
 *
 * Scheduled every ten minutes from routes/console.php.
 */
class IssueDomainCertificates extends Command
{
    protected $signature = 'domains:issue-certificates
                            {--domain= : Issue for a single domain by id, ignoring retry backoff}
                            {--limit=25 : Max domains processed in one run}
                            {--force : Run even when SSL_AUTO_ISSUE is off (manual/EC2 shell use)}';

    protected $description = 'Issue Let\'s Encrypt certificates for verified custom/global domains lacking one';

    public function handle(SslCertificateIssuer $issuer): int
    {
        if (!SslCertificateIssuer::enabled() && !$this->option('force')) {
            $this->info('Automatic SSL issuance is disabled (set SSL_AUTO_ISSUE=true on the EC2 deployment).');
            return self::SUCCESS;
        }

        if ($domainId = $this->option('domain')) {
            $domain = Domain::query()->withoutGlobalScope('workspace')->find($domainId);
            if (!$domain) {
                $this->error("Domain #{$domainId} not found");
                return self::FAILURE;
            }
            if (!$domain->is_verified || !$domain->is_active) {
                $this->warn("Domain #{$domainId} ({$domain->domain}) is not an active verified domain — skipping");
                return self::SUCCESS;
            }
            $result = $issuer->issue($domain);
            $this->info("Domain #{$domainId} {$domain->domain} → {$result}");
            return $result === SslCertificateIssuer::RESULT_FAILED ? self::FAILURE : self::SUCCESS;
        }

        $issued = $failed = 0;
        foreach ($issuer->dueDomains((int) $this->option('limit')) as $domain) {
            $result = $issuer->issue($domain);
            if ($result === SslCertificateIssuer::RESULT_ISSUED) {
                $issued++;
                $this->info("Issued: {$domain->domain}");
            } else {
                $failed++;
                $this->error("Failed: {$domain->domain} — " . ((string) $domain->fresh()->ssl_last_error));
            }
        }

        $this->info("Done — issued: {$issued}, failed: {$failed}.");
        return self::SUCCESS;
    }
}
