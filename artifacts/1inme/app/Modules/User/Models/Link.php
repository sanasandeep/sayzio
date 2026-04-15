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
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'is_password_protected' => 'boolean',
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

    public function activeBiolinkBlocks()
    {
        return $this->hasMany(BiolinkBlock::class)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isAccessible(): bool
    {
        return $this->is_active && !$this->isExpired();
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

    public static function generateAlias(int $length = 7): string
    {
        do {
            $alias = Str::random($length);
        } while (static::where('alias', $alias)->exists());

        return $alias;
    }
}
