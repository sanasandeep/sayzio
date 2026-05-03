<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkBackup;
use App\Modules\User\Models\LinkHealthCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "Link Insurance" probe + decision engine.
 *
 * For each enabled link this service is responsible for:
 *
 *   1. Probing the link's currently-serving destination (primary OR the
 *      promoted backup, whichever is active).
 *   2. Recording a `link_health_checks` row for the audit trail and the
 *      per-link uptime sparkline on the dashboard.
 *   3. Counting consecutive failures/successes and, when the configured
 *      thresholds are crossed, promoting the next healthy backup or
 *      restoring the primary.
 *   4. Emitting a `link_failover` / `link_restored` in-app notification
 *      to the link owner, and (best-effort) an email via {@see
 *      \App\Mail\LinkInsuranceAlertMail}.
 *
 * Deliberate scope cuts (called out in the task plan):
 *
 *   - Single-region probe only. The "multi-region 2-of-3 corroboration"
 *     bullet from the original spec is not implemented; one HTTP HEAD
 *     (with a GET fallback) per cycle is the entire signal.
 *   - "Page-removed content signature" detection is reduced to a HTTP
 *     status read — 4xx/5xx are treated as down, anything else healthy.
 *     Soft-404 detection (200 with a "this page has been removed" body)
 *     is intentionally out of scope.
 *   - The Performance Coach insight integration (showing a "you had a
 *     failover this week" card) is also out of scope here; the data is
 *     queryable but not yet wired into LinkPerformanceCoach.
 */
class LinkHealthChecker
{
    /** Hard cap on backups per link enforced in the controller. */
    public const MAX_BACKUPS_PER_LINK = 3;

    /** Allowed cadence values (minutes) shown in the settings UI. */
    public const ALLOWED_CADENCES = [5, 15, 30, 60, 240];

    /** Probe timeout — kept short so a stuck request doesn't block the cycle. */
    protected const PROBE_TIMEOUT_SECONDS = 8;

    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * Yield links whose next probe is due (or that have never been
     * probed). Caller is expected to iterate this and call {@see
     * checkLink()} for each — this lets the scheduled command keep a
     * tight RAM footprint even for accounts with thousands of links.
     *
     * @return \Illuminate\Support\LazyCollection<int, Link>
     */
    public function dueLinks(?\DateTimeInterface $now = null)
    {
        $now ??= now();
        // withoutGlobalScopes() is required because the scheduler runs
        // outside any HTTP request, so the BelongsToWorkspace global
        // scope on Link would otherwise return zero rows in console.
        return Link::withoutGlobalScopes()
            ->where('insurance_enabled', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('insurance_last_checked_at')
                  ->orWhereRaw(
                      'insurance_last_checked_at + (insurance_cadence_minutes || \' minutes\')::interval <= ?',
                      [$now]
                  );
            })
            ->orderBy('insurance_last_checked_at', 'asc')
            ->lazyById(100);
    }

    /**
     * Run one probe cycle against $link's currently-active destination
     * and update its failover state. Returns the persisted health-check
     * row so callers (notably the feature tests) can assert on it.
     */
    public function checkLink(Link $link): LinkHealthCheck
    {
        // 1. Decide what URL we're testing this cycle. When in failover
        //    state we probe BOTH the active backup (to know if we should
        //    move on) AND the primary (to know if we should restore) —
        //    but the "active" probe is the one that drives the failure
        //    counter for the current state, so we pick that here.
        [$activeUrl, $activeBackup] = $this->resolveActiveTarget($link);

        $probe = $this->probe($activeUrl);
        $check = LinkHealthCheck::create([
            'link_id'        => $link->id,
            'link_backup_id' => $activeBackup?->id,
            'target_url'     => $activeUrl,
            'status'         => $probe['status'],
            'http_code'      => $probe['http_code'],
            'latency_ms'     => $probe['latency_ms'],
            'error_class'    => $probe['error_class'],
            'error_detail'   => $probe['error_detail'],
            'checked_at'     => now(),
        ]);

        if ($activeBackup) {
            $activeBackup->forceFill([
                'last_status'     => $probe['status'],
                'last_http_code'  => $probe['http_code'],
                'last_checked_at' => now(),
            ])->save();
        }

        // 2. Update consecutive counters atomically with the state
        //    transition so a crash mid-cycle can't desync them.
        DB::transaction(function () use ($link, $probe) {
            $link->refresh();
            $link->insurance_last_checked_at = now();

            if ($probe['status'] === 'healthy') {
                $link->insurance_consecutive_failures = 0;
                // Only the *primary*-state probes feed the recovery
                // counter. While in failover the active probe is
                // hitting the backup, so a healthy result there must
                // not be misread as "primary is back" — that signal
                // comes exclusively from recheckPrimaryFromFailover().
                if ($link->insurance_state === 'primary') {
                    $link->insurance_consecutive_successes++;
                } else {
                    $link->insurance_consecutive_successes = 0;
                }
                $this->maybeRestore($link);
            } else {
                $link->insurance_consecutive_failures++;
                $link->insurance_consecutive_successes = 0;
                $this->maybeFailover($link);
            }

            $link->save();
        });

        return $check;
    }

    /**
     * The URL we are currently serving to clickers — primary if state
     * is 'primary' or 'down', otherwise the promoted backup pointed at
     * by insurance_active_url.
     *
     * @return array{0: string, 1: ?LinkBackup}
     */
    protected function resolveActiveTarget(Link $link): array
    {
        if ($link->insurance_state === 'failover' && $link->insurance_active_url) {
            $backup = $link->backups()
                ->where('url', $link->insurance_active_url)
                ->orderBy('position')
                ->first();
            return [$link->insurance_active_url, $backup];
        }
        return [(string) $link->long_url, null];
    }

    /**
     * Promote the next healthy backup if we've hit the failure
     * threshold on the currently-active target.
     */
    protected function maybeFailover(Link $link): void
    {
        if ($link->insurance_consecutive_failures < $link->insurance_failure_threshold) {
            return;
        }

        $next = $this->pickNextHealthyBackup($link);

        // No healthy backup left → mark fully down and notify only once
        // per outage (state already 'down' means we already fired).
        if (!$next) {
            if ($link->insurance_state !== 'down') {
                $link->insurance_state = 'down';
                $link->insurance_active_url = null;
                $link->insurance_last_failover_at = now();
                $this->dispatchNotification($link, 'link_failover', [
                    'reason'  => 'all_destinations_down',
                    'message' => 'Primary and every backup destination are unreachable.',
                ]);
            }
            return;
        }

        // Already failed-over to this backup? Try the *next* one.
        if ($link->insurance_state === 'failover'
            && $link->insurance_active_url === $next->url) {
            $next = $this->pickNextHealthyBackup($link, $next->position);
            if (!$next) {
                $link->insurance_state = 'down';
                $link->insurance_active_url = null;
                $link->insurance_last_failover_at = now();
                $this->dispatchNotification($link, 'link_failover', [
                    'reason'  => 'all_destinations_down',
                    'message' => 'Primary and every backup destination are unreachable.',
                ]);
                return;
            }
        }

        $previousUrl = $link->insurance_state === 'failover'
            ? $link->insurance_active_url
            : $link->long_url;

        $link->insurance_state = 'failover';
        $link->insurance_active_url = $next->url;
        $link->insurance_last_failover_at = now();
        $link->insurance_consecutive_failures = 0;

        $this->dispatchNotification($link, 'link_failover', [
            'previous_url' => $previousUrl,
            'new_url'      => $next->url,
            'backup_label' => $next->label,
            'position'     => $next->position,
        ]);
    }

    /**
     * Restore the primary if auto-restore is on and we've seen enough
     * consecutive healthy probes against it. Note: for that to fire we
     * have to actually be probing the primary, which only happens when
     * state is 'primary' or 'down'. While in 'failover' the active
     * target is the backup; restore is driven by the separate
     * primary-recheck path in {@see recheckPrimaryFromFailover()}.
     */
    protected function maybeRestore(Link $link): void
    {
        if ($link->insurance_state === 'primary') return;
        if (!$link->insurance_auto_restore) return;
        if ($link->insurance_consecutive_successes < $link->insurance_recovery_threshold) return;

        $previous = $link->insurance_active_url;
        $link->insurance_state = 'primary';
        $link->insurance_active_url = null;
        $link->insurance_consecutive_successes = 0;

        $this->dispatchNotification($link, 'link_restored', [
            'previous_url' => $previous,
            'restored_url' => $link->long_url,
        ]);
    }

    /**
     * While a link is failed-over the regular cycle probes the active
     * backup. To know when the primary is back we run a *second*,
     * cheaper probe against the primary on the same cadence and feed
     * the result into the recovery counter. Called from the scheduled
     * command after {@see checkLink()}.
     */
    public function recheckPrimaryFromFailover(Link $link): ?LinkHealthCheck
    {
        if ($link->insurance_state !== 'failover') return null;
        if (!$link->long_url) return null;

        $probe = $this->probe($link->long_url);
        $check = LinkHealthCheck::create([
            'link_id'      => $link->id,
            'target_url'   => $link->long_url,
            'status'       => $probe['status'],
            'http_code'    => $probe['http_code'],
            'latency_ms'   => $probe['latency_ms'],
            'error_class'  => $probe['error_class'],
            'error_detail' => $probe['error_detail'],
            'checked_at'   => now(),
        ]);

        if (!$link->insurance_auto_restore) {
            return $check;
        }

        DB::transaction(function () use ($link, $probe) {
            $link->refresh();
            if ($probe['status'] !== 'healthy') {
                // Recovery requires *consecutive* successes — a single
                // failed primary probe must reset the counter so we
                // don't restore prematurely after intermittent ups.
                if ($link->insurance_consecutive_successes !== 0) {
                    $link->insurance_consecutive_successes = 0;
                    $link->save();
                }
                return;
            }
            $link->insurance_consecutive_successes++;
            if ($link->insurance_consecutive_successes >= $link->insurance_recovery_threshold) {
                $previous = $link->insurance_active_url;
                $link->insurance_state = 'primary';
                $link->insurance_active_url = null;
                $link->insurance_consecutive_successes = 0;
                $link->save();
                $this->dispatchNotification($link, 'link_restored', [
                    'previous_url' => $previous,
                    'restored_url' => $link->long_url,
                ]);
            } else {
                $link->save();
            }
        });

        return $check;
    }

    /**
     * Pick the lowest-position backup whose last probe was healthy
     * (or that we have never probed — those are treated as candidates).
     * If $afterPosition is given, skip everything at or below it so we
     * can jump to the *next* backup when the current failover target
     * also goes down.
     */
    protected function pickNextHealthyBackup(Link $link, ?int $afterPosition = null): ?LinkBackup
    {
        return $link->backups()
            ->orderBy('position')
            ->when($afterPosition !== null, fn ($q) => $q->where('position', '>', $afterPosition))
            ->where(function ($q) {
                $q->whereNull('last_status')->orWhere('last_status', '!=', 'down');
            })
            ->first();
        }

    /**
     * One HTTP probe. HEAD first because most CDNs answer it cheaply;
     * fall back to GET if HEAD is rejected (405) since some real-world
     * destinations (e.g. Notion pages) only accept GET.
     *
     * @return array{status: string, http_code: ?int, latency_ms: ?int, error_class: ?string, error_detail: ?string}
     */
    public function probe(string $url): array
    {
        $start = microtime(true);
        // SSRF guard — refuse to probe URLs that resolve to a private,
        // loopback, link-local or cloud-metadata address. Without this
        // a workspace member could point a backup at
        // http://169.254.169.254/ and have the scheduler poke our own
        // VPC/cloud metadata endpoint on their behalf.
        if ($why = $this->ssrfReason($url)) {
            return [
                'status'       => 'down',
                'http_code'    => null,
                'latency_ms'   => 0,
                'error_class'  => 'blocked',
                'error_detail' => $why,
            ];
        }
        try {
            $resp = Http::timeout(self::PROBE_TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => '1INME-LinkInsurance/1.0 (+https://1inme.com)'])
                ->withOptions(['allow_redirects' => true])
                ->head($url);

            if ($resp->status() === 405 || $resp->status() === 501) {
                $resp = Http::timeout(self::PROBE_TIMEOUT_SECONDS)
                    ->withHeaders(['User-Agent' => '1INME-LinkInsurance/1.0 (+https://1inme.com)'])
                    ->withOptions(['allow_redirects' => true])
                    ->get($url);
            }

            $latency = (int) round((microtime(true) - $start) * 1000);
            $code    = $resp->status();

            if ($code >= 200 && $code < 400) {
                return [
                    'status'       => 'healthy',
                    'http_code'    => $code,
                    'latency_ms'   => $latency,
                    'error_class'  => null,
                    'error_detail' => null,
                ];
            }

            return [
                'status'       => 'down',
                'http_code'    => $code,
                'latency_ms'   => $latency,
                'error_class'  => $code >= 500 ? 'http_5xx' : 'http_4xx',
                'error_detail' => "HTTP {$code}",
            ];
        } catch (ConnectionException $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            return [
                'status'       => 'down',
                'http_code'    => null,
                'latency_ms'   => $latency,
                'error_class'  => $this->classifyConnectionError($e),
                'error_detail' => mb_substr($e->getMessage(), 0, 250),
            ];
        } catch (RequestException $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            return [
                'status'       => 'down',
                'http_code'    => $e->response?->status(),
                'latency_ms'   => $latency,
                'error_class'  => 'unknown',
                'error_detail' => mb_substr($e->getMessage(), 0, 250),
            ];
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            Log::warning('LinkInsurance probe threw', [
                'url' => $url, 'error' => $e->getMessage(),
            ]);
            return [
                'status'       => 'down',
                'http_code'    => null,
                'latency_ms'   => $latency,
                'error_class'  => 'unknown',
                'error_detail' => mb_substr($e->getMessage(), 0, 250),
            ];
        }
    }

    /**
     * Returns a human-readable reason if $url should NOT be probed
     * (private/loopback/metadata target), or null if it's safe.
     * Resolves the host once via gethostbynamel() so DNS-based bypasses
     * (a public hostname that resolves to 10.0.0.1) are caught too.
     */
    public function ssrfReason(string $url): ?string
    {
        $parts = @parse_url($url);
        if (!$parts || empty($parts['host'])) return 'invalid_url';

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) return 'unsupported_scheme';

        $host = strtolower($parts['host']);
        // Some hosts users try to abuse don't even need DNS.
        $blockedNames = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'metadata.google.internal'];
        if (in_array($host, $blockedNames, true)) return 'blocked_host';

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) {
            if (!filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                return 'private_or_reserved_ip';
            }
            // AWS / GCP / Azure metadata endpoint.
            if ($ip === '169.254.169.254') return 'cloud_metadata_ip';
        }
        return null;
    }

    protected function classifyConnectionError(ConnectionException $e): string
    {
        $msg = strtolower($e->getMessage());
        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) return 'timeout';
        if (str_contains($msg, 'ssl') || str_contains($msg, 'tls') || str_contains($msg, 'certificate')) return 'tls';
        if (str_contains($msg, 'could not resolve') || str_contains($msg, 'name or service not known')) return 'dns';
        if (str_contains($msg, 'refused') || str_contains($msg, 'connect')) return 'connect';
        return 'connect';
    }

    /**
     * Send the in-app notification to the link owner. Email goes via
     * the same call but only when the user's preference matrix has
     * 'email' on for this type — which is the default for both
     * insurance types per {@see NotificationService::catalog()}.
     */
    protected function dispatchNotification(Link $link, string $type, array $payload): void
    {
        $user = $link->user()->first();
        if (!$user) return;

        $payload = array_merge([
            'link_id' => $link->id,
            'alias'   => $link->alias,
            'title'   => $link->title,
        ], $payload);

        // One-click restore action surfaced in the in-app
        // notifications drawer (and re-used by the email button) so
        // the owner can flip the link back to primary without leaving
        // the alert.
        if ($type === 'link_failover') {
            $payload['actions'] = [
                [
                    'label' => 'Restore primary now',
                    'url'   => route('user.links.insurance.restore-action', ['link' => $link->id]),
                    'kind'  => 'primary',
                ],
                [
                    'label' => 'Manage Link Insurance',
                    'url'   => route('user.links.insurance.settings', ['link' => $link->id]),
                    'kind'  => 'secondary',
                ],
            ];
        }

        $this->notifications->notify($user, $type, $payload);

        // Best-effort email. Wrapped in try/catch because a misconfigured
        // SMTP must NEVER stop a failover from happening.
        try {
            if ($this->notifications->prefersChannel($user->id, $type, 'email')
                && !empty($user->email)) {
                \Illuminate\Support\Facades\Mail::to($user->email)
                    ->send(new \App\Mail\LinkInsuranceAlertMail($link, $type, $payload));
            }
        } catch (\Throwable $e) {
            Log::warning('LinkInsurance email send failed', [
                'link_id' => $link->id, 'type' => $type, 'error' => $e->getMessage(),
            ]);
        }
    }
}
