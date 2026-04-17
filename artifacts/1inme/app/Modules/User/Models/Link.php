<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Link extends Model
{
    protected $fillable = [
        'user_id', 'project_id', 'domain_id', 'type', 'alias', 'title',
        'long_url', 'redirect_type', 'is_active', 'is_verified', 'verified_name', 'verified_logo',
        'expires_at', 'password', 'is_password_protected',
        'seo_title', 'seo_description', 'seo_image', 'favicon',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'settings', 'total_clicks', 'unique_clicks',
        'splash_page_id', 'splash_enabled',
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
        ];
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

    public function biolinkBlocks()
    {
        return $this->hasMany(BiolinkBlock::class)->orderBy('sort_order');
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
    public static function resolveByAlias(string $alias): ?self
    {
        $link = static::where('alias', $alias)->first();
        if ($link) return $link;

        $extra = LinkAlias::where('alias', $alias)->first();
        return $extra ? static::find($extra->link_id) : null;
    }

    public function activeBiolinkBlocks()
    {
        return $this->hasMany(BiolinkBlock::class)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order');
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

    public function isAccessible(): bool
    {
        return $this->is_active && !$this->isExpired() && !$this->isScheduledFuture();
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

    public function getDestinationUrl(): string
    {
        $url = $this->long_url;
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
        'ics'     => 'Event Invite',
        'vcf'     => 'Digital Card',
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
