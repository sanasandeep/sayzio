<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An admin-composed "Zio Digest" — a rich-content broadcast (text, images,
 * videos, links, social embeds) with a public page at /digest/{slug} and
 * email + WhatsApp delivery. See Task #5620.
 */
class ZioDigest extends Model
{
    protected $fillable = [
        'title', 'slug', 'status', 'lead_image', 'summary', 'blocks',
        'audience', 'created_by_admin_id',
        'email_status', 'wa_status',
        'email_queued_count', 'email_sent_count', 'email_failed_count', 'email_skipped_count',
        'wa_queued_count', 'wa_sent_count', 'wa_failed_count', 'wa_skipped_count',
        'unsubscribed_count',
        'published_at', 'email_sent_at', 'wa_sent_at',
    ];

    protected $casts = [
        'blocks'        => 'array',
        'audience'      => 'array',
        'published_at'  => 'datetime',
        'email_sent_at' => 'datetime',
        'wa_sent_at'    => 'datetime',
    ];

    /** Block types the composer/renderer understand. */
    public const BLOCK_TYPES = ['heading', 'text', 'image', 'video', 'link', 'embed'];

    public function recipients(): HasMany
    {
        return $this->hasMany(ZioDigestRecipient::class, 'digest_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function publicUrl(): string
    {
        return route('site.digest.show', ['slug' => $this->slug]);
    }

    /**
     * Build a unique slug from a title (append -2, -3, ... on collision).
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'digest';
        $slug = $base;
        $n = 2;
        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    /** Normalize a submitted blocks array to the known shape. */
    public static function sanitizeBlocks($blocks): array
    {
        if (!is_array($blocks)) {
            return [];
        }
        $out = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            if (!in_array($type, self::BLOCK_TYPES, true)) {
                continue;
            }
            $clean = ['type' => $type];
            foreach (['text', 'level', 'url', 'alt', 'caption', 'title', 'description'] as $key) {
                if (isset($block[$key]) && is_scalar($block[$key])) {
                    $clean[$key] = mb_substr(trim((string) $block[$key]), 0, $key === 'text' ? 20000 : 2048);
                }
            }
            $out[] = $clean;
        }

        return array_slice($out, 0, 100);
    }
}
