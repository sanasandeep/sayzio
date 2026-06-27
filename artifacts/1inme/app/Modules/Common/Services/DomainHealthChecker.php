<?php

namespace App\Modules\Common\Services;

use App\Mail\DomainHealthAlertMail;
use App\Modules\User\Models\Domain;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Periodic DNS verification for verified user-owned custom domains.
 *
 * For each domain we look up the live CNAME record and compare it to
 * the expected `cname_target`. Status flow:
 *
 *   healthy   -> drifting   (CNAME stops resolving to our infrastructure)
 *   drifting  -> healthy    (CNAME comes back before grace expires)
 *   drifting  -> unverified (grace window elapsed without resolution)
 *
 * The first transition to `drifting` fires an in-app + email alert with
 * the exact records to fix. The transition to `unverified` fires a final
 * warning email and flips `is_verified=false` so traffic stops being
 * served — but the row is kept (preserving the unique-domain claim lock)
 * so the original creator can re-verify after fixing DNS without anyone
 * else swooping in to claim the host on the platform in the meantime.
 */
class DomainHealthChecker
{
    /** Re-check cadence: skip domains we've already probed within this many minutes. */
    public const RECHECK_INTERVAL_MINUTES = 60;

    /** Re-notify cooldown so a stuck domain doesn't spam the creator hourly. */
    public const RENOTIFY_INTERVAL_HOURS = 24;

    public function __construct(private NotificationService $notifications)
    {
    }

    /** Hours of grace after first drift before auto-unverifying. */
    public static function graceHours(): int
    {
        $hours = (int) config('domains.drift_grace_hours', (int) env('DOMAIN_DRIFT_GRACE_HOURS', 168));
        return max(1, $hours);
    }

    /**
     * @return Collection<int, Domain>
     */
    public function dueDomains(int $limit = 500): Collection
    {
        $cutoff = now()->subMinutes(self::RECHECK_INTERVAL_MINUTES);

        return Domain::query()
            ->whereNotNull('user_id')
            ->where('is_verified', true)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('dns_last_checked_at')
                  ->orWhere('dns_last_checked_at', '<=', $cutoff);
            })
            ->orderBy('dns_last_checked_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Probe a single domain, persist the new health state, and trigger
     * notifications / auto-unverify as needed. Returns the resolved
     * status string for inspection in tests.
     */
    public function checkDomain(Domain $domain): string
    {
        $expected = $this->expectedTarget($domain);
        [$matched, $observedTarget] = $this->resolve($domain->domain, $expected);

        $now = now();
        $domain->dns_last_checked_at = $now;
        $domain->dns_last_target     = $observedTarget;

        if ($matched) {
            $wasDrifting = $domain->dns_status === Domain::DNS_STATUS_DRIFTING;
            $domain->dns_status            = Domain::DNS_STATUS_HEALTHY;
            $domain->dns_drift_started_at  = null;
            $domain->dns_drift_notified_at = null;
            $domain->dns_unverified_warning_sent_at = null;
            $domain->save();

            if ($wasDrifting) {
                Log::info('domain.dns.recovered', [
                    'domain_id' => $domain->id,
                    'domain'    => $domain->domain,
                ]);
            }
            return Domain::DNS_STATUS_HEALTHY;
        }

        // Drift path: open a drift window or extend an existing one.
        if (!$domain->dns_drift_started_at) {
            $domain->dns_drift_started_at = $now;
        }
        $domain->dns_status = Domain::DNS_STATUS_DRIFTING;

        $graceHours    = self::graceHours();
        $graceElapsed  = $domain->dns_drift_started_at->diffInHours($now) >= $graceHours;

        if ($graceElapsed) {
            // Final-warning email + auto-unverify.
            $domain->dns_status   = Domain::DNS_STATUS_UNVERIFIED;
            $domain->is_verified  = false;
            $domain->verified_at  = null;
            $domain->dns_unverified_warning_sent_at = $now;
            $domain->save();

            $this->notify($domain, 'custom_domain_unverified', $expected);
            return Domain::DNS_STATUS_UNVERIFIED;
        }

        // Still inside grace — notify on first detection or after the
        // re-notify cooldown.
        $shouldNotify = !$domain->dns_drift_notified_at
            || $domain->dns_drift_notified_at->diffInHours($now) >= self::RENOTIFY_INTERVAL_HOURS;

        if ($shouldNotify) {
            $domain->dns_drift_notified_at = $now;
        }
        $domain->save();

        if ($shouldNotify) {
            $this->notify($domain, 'custom_domain_drift', $expected);
        }
        return Domain::DNS_STATUS_DRIFTING;
    }

    /**
     * Resolve the host's CNAME records and report whether any of them
     * point at the expected target. Returns [matched, observed_target].
     */
    protected function resolve(string $host, string $expected): array
    {
        $expected = rtrim(strtolower($expected), '.');
        $records  = @dns_get_record($host, DNS_CNAME);
        if (!is_array($records) || empty($records)) {
            return [false, null];
        }
        $observed = null;
        foreach ($records as $r) {
            if (empty($r['target'])) continue;
            $target = rtrim(strtolower($r['target']), '.');
            $observed = $observed ?? $target;
            if ($target === $expected) {
                return [true, $target];
            }
        }
        return [false, $observed];
    }

    protected function expectedTarget(Domain $domain): string
    {
        return strtolower($domain->cname_target ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?? ''));
    }

    protected function notify(Domain $domain, string $type, string $expected): void
    {
        $domain->loadMissing('user');
        $user = $domain->user;
        if (!$user) return;

        $payload = [
            'domain_id'      => $domain->id,
            'domain'         => $domain->domain,
            'expected_cname' => $expected,
            'observed_cname' => $domain->dns_last_target,
            'drift_started'  => optional($domain->dns_drift_started_at)->toIso8601String(),
            'grace_hours'    => self::graceHours(),
            'target_url'     => route('user.domains.index'),
        ];

        $this->notifications->notify($user, $type, $payload);

        try {
            if ($this->notifications->prefersChannel($user->id, $type, 'email') && !empty($user->email)) {
                \App\Modules\Common\Services\Emailer::sendMailable('domain.health_alert', $user->email, new DomainHealthAlertMail($domain, $type, $payload), ['domain' => $domain->domain ?? ''], ['user' => $user->id, 'related' => $domain]);
            }
        } catch (\Throwable $e) {
            Log::warning('domain.dns.email_failed', [
                'domain_id' => $domain->id,
                'type'      => $type,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
