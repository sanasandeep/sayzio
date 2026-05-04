<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Link extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'project_id', 'domain_id', 'type', 'alias', 'title',
        'long_url', 'redirect_type', 'is_active', 'is_verified', 'verified_name', 'verified_logo',
        'expires_at', 'password', 'is_password_protected',
        'seo_title', 'seo_description', 'seo_image', 'favicon',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'settings', 'total_clicks', 'unique_clicks',
        'splash_page_id', 'splash_enabled',
        'visibility', 'is_demo',
        // Auto-fire workspace tracking pixels (Meta/TikTok/Google Ads) on click.
        'auto_pixel',
        // Link Insurance
        'insurance_enabled', 'insurance_cadence_minutes',
        'insurance_failure_threshold', 'insurance_recovery_threshold',
        'insurance_auto_restore', 'insurance_state', 'insurance_active_url',
        'insurance_consecutive_failures', 'insurance_consecutive_successes',
        'insurance_last_checked_at', 'insurance_last_failover_at',
        'insurance_fallback_message',
        'insurance_primary_serve_count', 'insurance_failover_serve_count',
        // AR Business Card
        'ar_enabled', 'ar_settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'is_password_protected' => 'boolean',
            'splash_enabled' => 'boolean',
            'expires_at' => 'datetime',
            'settings' => 'array',
            'insurance_enabled'                 => 'boolean',
            'insurance_auto_restore'            => 'boolean',
            'insurance_cadence_minutes'         => 'integer',
            'insurance_failure_threshold'       => 'integer',
            'insurance_recovery_threshold'      => 'integer',
            'insurance_consecutive_failures'    => 'integer',
            'insurance_consecutive_successes'   => 'integer',
            'insurance_last_checked_at'         => 'datetime',
            'insurance_last_failover_at'        => 'datetime',
            'ar_enabled'                        => 'boolean',
            'ar_settings'                       => 'array',
            'auto_pixel'                        => 'boolean',
        ];
    }

    public function backups()
    {
        return $this->hasMany(LinkBackup::class)->orderBy('position');
    }

    public function healthChecks()
    {
        return $this->hasMany(LinkHealthCheck::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verificationRequests()
    {
        return $this->hasMany(VerificationRequest::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function pixels()
    {
        return $this->belongsToMany(Pixel::class, 'link_pixels');
    }

    public function clicks()
    {
        return $this->hasMany(LinkClick::class);
    }

    public function fileLink()
    {
        return $this->hasOne(FileLink::class);
    }

    public function icsData()
    {
        return $this->hasOne(IcsData::class);
    }

    public function vcfData()
    {
        return $this->hasOne(VcfData::class);
    }

    public function rsvps()
    {
        return $this->hasMany(Rsvp::class);
    }

    public function pollVotes()
    {
        return $this->hasMany(PollVote::class);
    }

    public function biolinkBlocks()
    {
        return $this->hasMany(BiolinkBlock::class)->orderBy('sort_order');
    }

    /**
     * A/B test variants for this short link. Populated by the browser
     * extension's "Shorten as A/B test" flow. When non-empty AND the
     * link's `settings.ab_test.winner_variant_id` is null, the redirect
     * path performs sticky weighted variant assignment instead of
     * sending visitors to `long_url`.
     */
    public function abVariants()
    {
        return $this->hasMany(AbVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * True when the link has an active A/B test running (variants exist
     * and no winner has been declared yet).
     */
    public function hasActiveAbTest(): bool
    {
        $winner = data_get($this->settings, 'ab_test.winner_variant_id');
        if ($winner) return false;
        return $this->abVariants()->exists();
    }

    /**
     * Additional aliases (alternative URL slugs) that all serve THIS biolink page
     * with no redirect. The base `links.alias` column is the "primary" alias and
     * is NOT stored in this table — it lives on the link row itself for backward
     * compatibility with all existing queries.
     */
    public function aliases()
    {
        return $this->hasMany(LinkAlias::class);
    }

    /**
     * Returns every alias (primary + extras) as a flat array of strings.
     */
    public function getAllAliases(): array
    {
        $primary = $this->alias ? [$this->alias] : [];
        $extras  = $this->relationLoaded('aliases')
            ? $this->aliases->pluck('alias')->all()
            : $this->aliases()->pluck('alias')->all();
        return array_values(array_unique(array_merge($primary, $extras)));
    }

    /**
     * Resolve a public-facing alias to its Link row. Checks the primary alias
     * first (fast path — covers 100% of pre-existing links and the most common
     * case), then falls back to the link_aliases table for additional aliases.
     * Returns null if no link matches.
     */
    public static function resolveByAlias(string $alias, ?string $host = null): ?self
    {
        // Host-aware resolution. Three cases:
        //   1. Platform host (APP_URL, Replit dev domain, deployed Replit
        //      URL, localhost) → fall back to links with domain_id IS NULL.
        //   2. Verified+active row in the `domains` table → scope alias
        //      lookup to that domain_id so the same alias can live
        //      independently on different custom domains.
        //   3. Anything else (a host that looks like a custom-domain
        //      attempt but isn't registered/verified) → return null so
        //      the caller can serve "Domain not connected".
        $domainId = null;
        $normalizedHost = \App\Modules\Common\Support\PlatformHosts::normalize($host);
        if ($normalizedHost !== null) {
            $domain = Domain::where('domain', $normalizedHost)->first();
            if ($domain) {
                if (!$domain->is_active || !$domain->is_verified) {
                    // Host is registered as a custom domain but the user
                    // hasn't completed verification/activation yet — the
                    // caller should serve "Domain not connected" instead
                    // of leaking the platform's no-domain links.
                    return null;
                }
                $domainId = $domain->id;
            }
            // No row at all → fall through and treat as platform: match
            // links with domain_id IS NULL just like APP_URL host does.
        }

        $query = static::where('alias', $alias);
        if ($host !== null) {
            $query->where(function ($q) use ($domainId) {
                $domainId === null ? $q->whereNull('domain_id') : $q->where('domain_id', $domainId);
            });
        }
        $link = $query->first();
        if ($link) return $link;

        $extraQ = LinkAlias::where('alias', $alias);
        if ($host !== null) {
            $extraQ->whereHas('link', function ($q) use ($domainId) {
                $domainId === null ? $q->whereNull('domain_id') : $q->where('domain_id', $domainId);
            });
        }
        $extra = $extraQ->first();
        return $extra ? static::find($extra->link_id) : null;
    }

    public function activeBiolinkBlocks()
    {
        return $this->hasMany(BiolinkBlock::class)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    /**
     * Layout A/B tests for this biolink. Most links never have one, but
     * when present the public renderer prefers the variant snapshots
     * over the live `biolink_blocks` rows.
     */
    public function biolinkExperiments()
    {
        return $this->hasMany(\App\Modules\User\Models\BiolinkExperiment::class)
            ->orderByDesc('id');
    }

    public function activeBiolinkExperiment()
    {
        return $this->hasOne(\App\Modules\User\Models\BiolinkExperiment::class)
            ->where('status', 'running')
            ->latestOfMany();
    }

    public function isExpired(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) return true;

        $s = $this->settings ?? [];
        $maxClicks = (int) ($s['max_clicks'] ?? 0);
        if ($maxClicks > 0 && (int) $this->total_clicks >= $maxClicks) return true;

        // One-time link: expired the moment it's been visited at least once.
        if (!empty($s['expire_on_first_click']) && (int) $this->total_clicks >= 1) return true;

        return false;
    }

    public function isScheduledFuture(): bool
    {
        $s = $this->settings ?? [];
        $startAt = $s['start_at'] ?? null;
        if (!$startAt) return false;
        try {
            return \Carbon\Carbon::parse($startAt)->isFuture();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * "Daily active window" — link is only reachable during these hours
     * on the configured days (in the link's chosen timezone). Returns
     * true when no window is configured (i.e. always-on).
     */
    public function isWithinActiveWindow(): bool
    {
        $s  = $this->settings ?? [];
        $aw = $s['active_window'] ?? null;
        if (!is_array($aw) || empty($aw['enabled'])) return true;

        $tz    = $s['timezone'] ?? 'UTC';
        $days  = (array) ($aw['days'] ?? []);

        // Normalise to a list of slots. Supports the new multi-slot shape
        // (`slots: [{start, end}, ...]`) and the legacy single-window shape
        // (`start`, `end` at the top level).
        $slots = [];
        if (!empty($aw['slots']) && is_array($aw['slots'])) {
            foreach ($aw['slots'] as $sl) {
                if (!empty($sl['start']) && !empty($sl['end'])) {
                    $slots[] = ['start' => $sl['start'], 'end' => $sl['end']];
                }
            }
        } elseif (!empty($aw['start']) && !empty($aw['end'])) {
            $slots[] = ['start' => $aw['start'], 'end' => $aw['end']];
        }
        if (empty($slots)) return true;

        try {
            $now = \Carbon\Carbon::now($tz);
        } catch (\Throwable $e) {
            $now = \Carbon\Carbon::now('UTC');
        }

        $cur = (int) $now->format('Hi');
        $dayKey = strtolower(substr($now->format('D'), 0, 3));
        $prevDayKey = strtolower(substr($now->copy()->subDay()->format('D'), 0, 3));
        $allowedToday    = empty($days) || in_array($dayKey, $days, true);
        $allowedYesterday = empty($days) || in_array($prevDayKey, $days, true);

        // Any matching slot opens the window.
        foreach ($slots as $sl) {
            $a = (int) str_replace(':', '', $sl['start']);
            $b = (int) str_replace(':', '', $sl['end']);
            if ($a <= $b) {
                if ($allowedToday && $cur >= $a && $cur <= $b) return true;
            } else {
                // Wrapped slot (e.g. 22:00 – 02:00).
                if ($cur >= $a && $allowedToday)    return true;
                if ($cur <= $b && $allowedYesterday) return true;
            }
        }
        return false;
    }

    /**
     * True when the visitor's country code (uppercase ISO-2) appears in
     * the configured banned-locations list. Null country = treat as
     * not-blocked (we can't verify, so we don't 403).
     */
    public function isCountryBlocked(?string $countryCode): bool
    {
        $list = (array) (($this->settings ?? [])['country_blocklist'] ?? []);
        if (empty($list) || !$countryCode) return false;
        return in_array(strtoupper($countryCode), array_map('strtoupper', $list), true);
    }

    public function isAccessible(): bool
    {
        return $this->is_active
            && !$this->isExpired()
            && !$this->isScheduledFuture()
            && $this->isWithinActiveWindow();
    }

    /**
     * High-level reason this link is currently inaccessible. Drives the
     * messaging on the visitor-facing "link unavailable" page. Returns
     * null when the link is reachable.
     */
    public function unavailabilityReason(): ?string
    {
        if (!$this->is_active)            return 'inactive';
        if ($this->isExpired())           return $this->expires_at && $this->expires_at->isPast() ? 'expired' : 'limit_reached';
        if ($this->isScheduledFuture())   return 'scheduled';
        if (!$this->isWithinActiveWindow()) return 'closed_hours';
        return null;
    }

    /**
     * Next moment (in the link's timezone) at which the daily active
     * window will be open again, or null if no window is configured /
     * we can't find an opening within the next 7 days.
     */
    public function nextActiveWindowOpening(): ?\Carbon\Carbon
    {
        $s  = $this->settings ?? [];
        $aw = $s['active_window'] ?? null;
        if (!is_array($aw) || empty($aw['enabled'])) return null;

        $tz   = $s['timezone'] ?? 'UTC';
        $days = (array) ($aw['days'] ?? ['mon','tue','wed','thu','fri','sat','sun']);

        $slots = [];
        if (!empty($aw['slots']) && is_array($aw['slots'])) {
            foreach ($aw['slots'] as $sl) {
                if (!empty($sl['start']) && !empty($sl['end'])) $slots[] = $sl;
            }
        } elseif (!empty($aw['start']) && !empty($aw['end'])) {
            $slots[] = ['start' => $aw['start'], 'end' => $aw['end']];
        }
        if (empty($slots)) return null;

        try { $now = \Carbon\Carbon::now($tz); } catch (\Throwable $e) { return null; }

        // Sweep the next 7 days for the earliest start that is in the future
        // and falls on an allowed day.
        $best = null;
        for ($i = 0; $i < 8; $i++) {
            $day    = $now->copy()->addDays($i);
            $dayKey = strtolower(substr($day->format('D'), 0, 3));
            if (!in_array($dayKey, $days, true)) continue;
            foreach ($slots as $sl) {
                [$h, $m] = array_pad(explode(':', $sl['start']), 2, '0');
                $candidate = $day->copy()->setTime((int) $h, (int) $m, 0);
                if ($candidate->lessThanOrEqualTo($now)) continue;
                if ($best === null || $candidate->lessThan($best)) $best = $candidate;
            }
            if ($best) break; // earliest opening on this day wins
        }
        return $best;
    }

    /**
     * Splash page (standalone, reusable across multiple links).
     */
    public function splashPage()
    {
        return $this->belongsTo(SplashPage::class);
    }

    /**
     * Whether this link has an enabled splash (intermediate transition) page
     * that should be rendered before the visitor reaches the destination.
     */
    public function hasSplashEnabled(): bool
    {
        return $this->splash_enabled && $this->splash_page_id && $this->splashPage;
    }

    /** Splash page configuration array (always returns an array). */
    public function getSplashConfig(): array
    {
        if (!$this->splash_page_id) return [];
        $sp = $this->relationLoaded('splashPage') ? $this->splashPage : $this->splashPage()->first();
        return $sp ? $sp->toRenderArray() : [];
    }

    /**
     * URL to send visitors to when this link has expired (or is unavailable).
     * Returns null if no custom expiry URL configured.
     */
    public function getExpiryRedirectUrl(): ?string
    {
        $url = $this->settings['expiry_url'] ?? null;
        if (!$url) return null;
        $url = trim($url);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public function getShortUrl(): string
    {
        if ($this->domain) {
            return "https://{$this->domain->domain}/{$this->alias}";
        }
        return url("/{$this->alias}");
    }

    /**
     * The destination clickers should be sent to right now. Normally
     * this is {@see $long_url}, but when "Link Insurance" has promoted
     * a backup we serve the promoted URL instead. Visitors don't see
     * the swap — they hit the same short alias and just land on the
     * working backup. UTM params are still appended so analytics keep
     * working across primary and backups.
     */
    public function getDestinationUrl(): ?string
    {
        $url = $this->insurance_state === 'failover' && !empty($this->insurance_active_url)
            ? $this->insurance_active_url
            : $this->long_url;
        if ($url === null || $url === '') return null;
        $utmParams = [];

        if ($this->utm_source) $utmParams['utm_source'] = $this->utm_source;
        if ($this->utm_medium) $utmParams['utm_medium'] = $this->utm_medium;
        if ($this->utm_campaign) $utmParams['utm_campaign'] = $this->utm_campaign;
        if ($this->utm_term) $utmParams['utm_term'] = $this->utm_term;
        if ($this->utm_content) $utmParams['utm_content'] = $this->utm_content;

        if (!empty($utmParams)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($utmParams);
        }

        return $url;
    }

    /**
     * Friendly user-facing labels for link types. Internal slugs
     * (`url`, `biolink`, `file`, `ics`, `vcf`) remain unchanged.
     */
    public const TYPE_LABELS = [
        'url'     => 'Short Link',
        'biolink' => 'Link in Bio',
        'file'    => 'File Share',
        'ics'     => 'Event',
        'vcf'     => 'Contact Card',
    ];

    public static function typeLabel(?string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucfirst($type ?? 'link');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabel($this->type);
    }

    public static function generateAlias(int $length = 7): string
    {
        do {
            $alias = Str::random($length);
        } while (static::where('alias', $alias)->exists());

        return $alias;
    }
}
