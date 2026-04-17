<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SocialProof extends Model
{
    protected $fillable = [
        'user_id', 'uuid', 'name', 'type', 'is_active',
        'settings', 'design', 'targeting', 'schedule',
        'impressions', 'clicks', 'conversions',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'settings'    => 'array',
        'design'      => 'array',
        'targeting'   => 'array',
        'schedule'    => 'array',
        'impressions' => 'integer',
        'clicks'      => 'integer',
        'conversions' => 'integer',
    ];

    public const TYPES = [
        'recent_activity'  => 'Recent Activity',
        'visitor_count'    => 'Live Visitor Counter',
        'conversion_count' => 'Conversion Counter',
        'email_signup'     => 'Email Signup Prompt',
        'countdown'        => 'Countdown Timer',
        'review'           => 'Review / Rating',
        'custom_html'      => 'Custom HTML',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $sp) {
            if (empty($sp->uuid)) $sp->uuid = (string) Str::uuid();
        });
    }

    public function user()  { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(SocialProofItem::class)->orderBy('sort_order'); }
    public function events(){ return $this->hasMany(SocialProofEvent::class); }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function ctr(): float
    {
        return $this->impressions > 0 ? round(($this->clicks / $this->impressions) * 100, 2) : 0.0;
    }

    /**
     * Default skeletons for each type — used when creating a new proof.
     */
    public static function defaultSettingsFor(string $type): array
    {
        return match ($type) {
            'recent_activity' => [
                'title_template' => '{name} from {location}',
                'body_template'  => '{action}',
                'mode'           => 'curated', // curated | simulated
                'simulated_pool' => [],
            ],
            'visitor_count' => [
                'mode'      => 'simulated',
                'min'       => 12,
                'max'       => 48,
                'text'      => '{count} people are viewing this page',
            ],
            'conversion_count' => [
                'window' => '24h',
                'text'   => '{count} people purchased in the last 24 hours',
                'count'  => 47,
            ],
            'email_signup' => [
                'title' => 'Join our newsletter',
                'body'  => 'Get weekly tips delivered to your inbox.',
                'cta'   => 'Subscribe',
            ],
            'countdown' => [
                'title'      => 'Limited offer ends in',
                'ends_at'    => now()->addDays(3)->toIso8601String(),
                'expired_text' => 'Offer expired',
            ],
            'review' => [
                'rotate' => true,
                'items'  => [],
            ],
            'custom_html' => [
                'html' => '<div style="padding:12px;font:14px sans-serif;">Hello world!</div>',
            ],
            default => [],
        };
    }

    public static function defaultDesign(): array
    {
        return [
            'position'   => 'bottom-left', // bottom-left | bottom-right | top-left | top-right
            'theme'      => 'light',       // light | dark
            'accent'     => '#7c3aed',
            'rounded'    => 'lg',          // sm | md | lg | xl | full
            'shadow'     => true,
            'animation'  => 'slide-up',    // slide-up | fade | zoom
            'show_close' => true,
        ];
    }

    public static function defaultTargeting(): array
    {
        return [
            'pages_include' => [], // empty = all pages
            'pages_exclude' => [],
            'devices'       => ['desktop', 'tablet', 'mobile'],
            'delay'         => 3,    // seconds before first show
            'interval'      => 8,    // seconds between notifications
            'duration'      => 5,    // seconds each notification stays visible
            'max_per_session' => 0,  // 0 = unlimited
        ];
    }
}
