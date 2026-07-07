<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Automatic HTTPS certificate issuance for verified domains.
 *
 * Every customer custom domain (and admin-provided global domain) that
 * passes DNS verification needs its own TLS certificate on the EC2
 * deployment — otherwise visitors get certificate errors. This service
 * shells out to the sudoers-whitelisted root helper installed by the EC2
 * kit (deploy/ec2/issue-domain-cert.sh → /usr/local/sbin/sayzio-issue-cert),
 * which runs certbot webroot issuance, writes the per-domain nginx vhost
 * and reloads nginx. Renewals are certbot's job (its timer/cron); this
 * service only covers first issuance.
 *
 * State lives on the domains row (ssl_status / ssl_attempts /
 * ssl_last_attempted_at / ssl_issued_at / ssl_last_error / ssl_alerted_at)
 * so retry backoff and alert dedup survive restarts and multiple servers.
 *
 * Failure surfacing — never silent: every failed attempt logs a loud
 * `::1inme:: SSL ISSUANCE FAILED` marker; after `alert_after_attempts`
 * consecutive failures the ops admins get an in-app + email alert
 * (re-alerted at most once per cooldown window), and a recovery notice
 * goes out when a previously-alerted domain finally gets its certificate.
 *
 * Disabled by default (`domains.ssl.auto_issue`): on Replit the platform
 * proxy terminates TLS and there is no certbot/nginx to drive.
 */
class SslCertificateIssuer
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ISSUED  = 'issued';
    public const STATUS_FAILED  = 'failed';

    /** Result codes returned by issue(). */
    public const RESULT_ISSUED  = 'issued';
    public const RESULT_FAILED  = 'failed';
    public const RESULT_SKIPPED = 'skipped';

    public static function enabled(): bool
    {
        return (bool) config('domains.ssl.auto_issue');
    }

    /**
     * Reset a domain's SSL state so the scheduler picks it up promptly on
     * its next tick. Called from every verification success path (user +
     * admin). Best-effort: the columns may not exist yet on a lagging
     * shared schema, and a failed stamp must never break verification —
     * a NULL ssl_status is picked up by dueDomains() anyway.
     */
    public static function markPending(Domain $domain): void
    {
        try {
            $domain->forceFill([
                'ssl_status'            => self::STATUS_PENDING,
                'ssl_attempts'          => 0,
                'ssl_last_attempted_at' => null,
                'ssl_last_error'        => null,
                'ssl_alerted_at'        => null,
            ])->save();
        } catch (\Throwable $e) {
            Log::warning("ssl markPending failed for domain #{$domain->id}: " . $e->getMessage());
        }
    }

    /**
     * Verified, active domains still needing a certificate — customer
     * custom domains AND admin global domains (user_id NULL) — honouring
     * the per-domain retry backoff. Oldest-attempted first so one
     * perpetually-failing domain can't starve the rest.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,Domain>
     */
    public function dueDomains(int $limit = 25)
    {
        $retryHours = max(1, (int) config('domains.ssl.retry_hours', 1));

        return Domain::query()
            ->withoutGlobalScope('workspace')
            ->where('is_active', true)
            ->where('is_verified', true)
            ->where(function ($q) {
                $q->whereNull('ssl_status')->orWhere('ssl_status', '!=', self::STATUS_ISSUED);
            })
            ->where(function ($q) use ($retryHours) {
                $q->whereNull('ssl_last_attempted_at')
                  ->orWhere('ssl_last_attempted_at', '<', now()->subHours($retryHours));
            })
            ->orderByRaw('ssl_last_attempted_at ASC NULLS FIRST')
            ->limit($limit)
            ->get();
    }

    /**
     * Attempt certificate issuance for one domain. Returns a RESULT_*
     * string. Never throws — failures are recorded on the row, logged
     * loudly, and alerted per the cooldown policy.
     */
    public function issue(Domain $domain): string
    {
        // Defense in depth: the store() validators already constrain the
        // hostname shape, but this value reaches a root helper — re-check.
        if (!preg_match('/^[a-z0-9]([a-z0-9.\-]*[a-z0-9])?\.[a-z]{2,}$/i', (string) $domain->domain)) {
            $this->recordFailure($domain, 'Domain name failed the safety pattern — refusing to pass it to the issuance helper.');
            return self::RESULT_FAILED;
        }

        $argv = preg_split('/\s+/', trim((string) config('domains.ssl.command')));
        if (empty($argv) || $argv[0] === '') {
            $this->recordFailure($domain, 'domains.ssl.command is empty — set SSL_ISSUE_COMMAND.');
            return self::RESULT_FAILED;
        }
        $argv[] = strtolower($domain->domain);
        if ($email = config('domains.ssl.certbot_email')) {
            $argv[] = $email;
        }

        $domain->forceFill(['ssl_last_attempted_at' => now()])->save();

        try {
            $result = Process::timeout((int) config('domains.ssl.timeout', 300))->run($argv);
        } catch (\Throwable $e) {
            $this->recordFailure($domain, 'Issuance process error: ' . $e->getMessage());
            return self::RESULT_FAILED;
        }

        if ($result->successful()) {
            $wasAlerted = (bool) $domain->ssl_alerted_at;
            $domain->forceFill([
                'ssl_status'     => self::STATUS_ISSUED,
                'ssl_issued_at'  => now(),
                'ssl_last_error' => null,
            ])->save();
            Log::info("SSL certificate issued for {$domain->domain} (domain #{$domain->id}).");
            if ($wasAlerted) {
                $this->dispatchRecovery($domain);
            }
            return self::RESULT_ISSUED;
        }

        $output = trim($result->errorOutput() . "\n" . $result->output());
        $this->recordFailure(
            $domain,
            'Issuance helper exited ' . $result->exitCode() . ': ' . mb_substr($output, -2000)
        );
        return self::RESULT_FAILED;
    }

    /**
     * Record a failed attempt: bump the counter, keep the error for the
     * admin surface, log the loud marker, and alert ops admins once the
     * threshold is crossed (deduped by the cooldown window).
     */
    private function recordFailure(Domain $domain, string $error): void
    {
        $attempts = (int) $domain->ssl_attempts + 1;
        $domain->forceFill([
            'ssl_status'            => self::STATUS_FAILED,
            'ssl_attempts'          => $attempts,
            'ssl_last_attempted_at' => $domain->ssl_last_attempted_at ?? now(),
            'ssl_last_error'        => $error,
        ])->save();

        // Loud marker so log-based alerting catches it like the deploy and
        // schema-health markers.
        Log::error("::1inme:: SSL ISSUANCE FAILED for {$domain->domain} (domain #{$domain->id}, attempt {$attempts}): {$error}");

        $threshold = max(1, (int) config('domains.ssl.alert_after_attempts', 3));
        if ($attempts < $threshold) {
            return;
        }

        $cooldown = max(1, (int) config('domains.ssl.alert_cooldown_hours', 24));
        if ($domain->ssl_alerted_at && $domain->ssl_alerted_at->greaterThan(now()->subHours($cooldown))) {
            return;
        }

        $this->dispatchFailureAlert($domain, $attempts, $error);
    }

    private function dispatchFailureAlert(Domain $domain, int $attempts, string $error): void
    {
        $owner   = $domain->user_id ? ($domain->user?->email ?? "user #{$domain->user_id}") : 'admin global domain';
        $subject = "HTTPS certificate issuance failing for {$domain->domain}";
        $body    = "Automatic SSL issuance for the verified domain {$domain->domain} ({$owner}) has failed "
                 . "{$attempts} time(s). Visitors on that domain will see certificate errors until this is fixed.\n\n"
                 . "Last error:\n" . mb_substr($error, -600) . "\n\n"
                 . "Common causes: the domain's DNS no longer points at this server, port 80 is unreachable "
                 . "(Let's Encrypt HTTP-01 validation), or a Let's Encrypt rate limit. Check "
                 . "/var/log/letsencrypt/letsencrypt.log on the server, or run "
                 . "`sudo /usr/local/sbin/sayzio-issue-cert {$domain->domain}` manually.";

        $inApp  = $this->fanOutInApp('domain_ssl_failed', $subject, $body, [
            'domain'   => $domain->domain,
            'attempts' => $attempts,
        ]);
        $emails = $this->fanOutEmail($subject, $body);

        $domain->forceFill(['ssl_alerted_at' => now()])->save();
        Log::warning("SSL failure alert for {$domain->domain} dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    private function dispatchRecovery(Domain $domain): void
    {
        $subject = "HTTPS certificate issued for {$domain->domain}";
        $body    = "Good news — the SSL certificate for {$domain->domain} has been issued and installed after earlier "
                 . "failures. The domain now serves HTTPS normally; renewals are handled automatically by certbot. "
                 . 'No further action needed.';

        $this->fanOutInApp('domain_ssl_issued', $subject, $body, ['domain' => $domain->domain]);
        $this->fanOutEmail($subject, $body);
    }

    /**
     * Operators who opted in to operational alerts — same audience as the
     * schema-health and other ops commands.
     */
    private function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private function adminUrl(): string
    {
        try {
            return route('admin.dashboard');
        } catch (\Throwable $e) {
            return url('/admin');
        }
    }

    /** @param array<string,mixed> $extra */
    private function fanOutInApp(string $type, string $subject, string $body, array $extra): int
    {
        $url = $this->adminUrl();
        $delivered = 0;
        foreach ($this->admins() as $u) {
            try {
                UserNotification::create([
                    'user_id' => $u->id,
                    'type'    => $type,
                    'data'    => array_merge([
                        'subject'    => $subject,
                        'body'       => $body,
                        'message'    => $body, // legacy field rendered by the notifications view
                        'url'        => $url,
                        'target_url' => $url,
                    ], $extra),
                    'created_at' => now(),
                ]);
                $delivered++;
            } catch (\Throwable $e) {
                Log::warning("ssl in-app alert failed for user {$u->id}: " . $e->getMessage());
            }
        }
        return $delivered;
    }

    private function fanOutEmail(string $subject, string $body): int
    {
        $emails = collect($this->admins())
            ->filter(fn ($u) => $u->email && $u->email_verified_at)
            ->pluck('email')
            ->unique()
            ->values()
            ->all();

        $sent = 0;
        foreach ($emails as $email) {
            try {
                Emailer::send('system.health_alert', $email, [], [
                    'subject' => $subject,
                    'body'    => $body . "\n\n" . $this->adminUrl(),
                    'format'  => 'text',
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("ssl alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }
}
