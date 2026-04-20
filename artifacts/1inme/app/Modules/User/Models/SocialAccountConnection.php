<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccountConnection extends Model
{
    protected $fillable = [
        'user_id', 'platform', 'handle', 'display_name', 'profile_url',
        'avatar_url', 'external_id', 'access_token', 'refresh_token',
        'token_expires_at', 'follower_count', 'last_refreshed_at',
        'last_refresh_status', 'last_refresh_error', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at'  => 'datetime',
            'last_refreshed_at' => 'datetime',
            'meta'              => 'array',
            'follower_count'    => 'integer',
            // Tokens are encrypted at rest; nulls pass through.
            'access_token'      => 'encrypted',
            'refresh_token'     => 'encrypted',
        ];
    }

    /** Per-platform brand colour + Font Awesome icon used by the public renderer. */
    public const PLATFORM_META = [
        'instagram' => ['label' => 'Instagram', 'icon' => 'fab fa-instagram',  'color' => '#E4405F', 'kind' => 'oauth',  'url' => 'https://instagram.com/{h}'],
        'tiktok'    => ['label' => 'TikTok',    'icon' => 'fab fa-tiktok',     'color' => '#000000', 'kind' => 'oauth',  'url' => 'https://tiktok.com/@{h}'],
        'youtube'   => ['label' => 'YouTube',   'icon' => 'fab fa-youtube',    'color' => '#FF0000', 'kind' => 'handle', 'url' => 'https://youtube.com/@{h}'],
        'twitter'   => ['label' => 'X',         'icon' => 'fab fa-x-twitter',  'color' => '#000000', 'kind' => 'oauth',  'url' => 'https://x.com/{h}'],
        'facebook'  => ['label' => 'Facebook',  'icon' => 'fab fa-facebook-f', 'color' => '#1877F2', 'kind' => 'oauth',  'url' => 'https://facebook.com/{h}'],
        'linkedin'  => ['label' => 'LinkedIn',  'icon' => 'fab fa-linkedin-in','color' => '#0A66C2', 'kind' => 'oauth',  'url' => 'https://linkedin.com/in/{h}'],
        'pinterest' => ['label' => 'Pinterest', 'icon' => 'fab fa-pinterest',  'color' => '#BD081C', 'kind' => 'oauth',  'url' => 'https://pinterest.com/{h}'],
        'twitch'    => ['label' => 'Twitch',    'icon' => 'fab fa-twitch',     'color' => '#9146FF', 'kind' => 'handle', 'url' => 'https://twitch.tv/{h}'],
        'github'    => ['label' => 'GitHub',    'icon' => 'fab fa-github',     'color' => '#181717', 'kind' => 'handle', 'url' => 'https://github.com/{h}'],
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function platformLabel(string $platform): string
    {
        return self::PLATFORM_META[$platform]['label'] ?? ucfirst($platform);
    }

    public static function isSupported(string $platform): bool
    {
        return isset(self::PLATFORM_META[$platform]);
    }

    public function brandColor(): string
    {
        return self::PLATFORM_META[$this->platform]['color'] ?? '#7c3aed';
    }

    public function brandIcon(): string
    {
        return self::PLATFORM_META[$this->platform]['icon'] ?? 'fas fa-link';
    }

    /** Resolved canonical profile URL (uses stored value, else builds from handle). */
    public function resolvedProfileUrl(): string
    {
        if ($this->profile_url) return $this->profile_url;
        $tpl = self::PLATFORM_META[$this->platform]['url'] ?? null;
        $h = ltrim((string) $this->handle, '@');
        return $tpl ? str_replace('{h}', rawurlencode($h), $tpl) : '';
    }

    /** Format a follower count like "1.2K" / "3.4M". */
    public static function formatCount(?int $n): ?string
    {
        if ($n === null) return null;
        if ($n < 1000)        return (string) $n;
        if ($n < 1_000_000)   return self::trim($n / 1000) . 'K';
        if ($n < 1_000_000_000) return self::trim($n / 1_000_000) . 'M';
        return self::trim($n / 1_000_000_000) . 'B';
    }

    private static function trim(float $v): string
    {
        $s = number_format($v, 1, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    }

    /** Whether the cached follower count is older than the refresh window (hours). */
    public function isStale(int $hours = 4): bool
    {
        if (! $this->last_refreshed_at) return true;
        return $this->last_refreshed_at->lt(now()->subHours($hours));
    }
}
