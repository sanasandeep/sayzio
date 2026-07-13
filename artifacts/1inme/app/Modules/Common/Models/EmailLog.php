<?php

namespace App\Modules\Common\Models;

use App\Modules\Common\Support\EmailLogRetentionPolicy;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per outbound email send attempt.
 *
 * @see \App\Modules\Common\Services\Emailer  writer for templated sends
 * @see \App\Listeners\LogOutboundEmail       catch-all writer for the rest
 */
class EmailLog extends Model
{
    protected $fillable = [
        'email_key',
        'category',
        'recipient',
        'subject',
        'body',
        'format',
        'transport',
        'status',
        'error',
        'user_id',
        'related_type',
        'related_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isResend(): bool
    {
        return (bool) ($this->meta['resent_from'] ?? false);
    }

    /**
     * Cap the stored body on EVERY write (regardless of which call site creates
     * the row), so a single pathological email can never persist an unbounded
     * blob. The cap is a storage concern only — the actual sent message is
     * unaffected (the writers send before logging).
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => self::capBody($value),
        );
    }

    /**
     * Cap a rendered body to the configured max stored size. Returns the
     * (possibly truncated) body; a truncated body is suffixed with a marker so
     * the admin viewer makes the truncation obvious. Capping can be disabled by
     * setting `email_logs.max_body_bytes` to 0/-1. The result is always <= the
     * configured cap, even when the cap is smaller than the marker itself.
     */
    public static function capBody(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }

        $max = EmailLogRetentionPolicy::maxBodyBytes();
        if ($max <= 0 || strlen($body) <= $max) {
            return $body;
        }

        $marker = "\n\n[\u{2026} body truncated to {$max} bytes by email-log retention]";

        // If the marker alone won't fit the cap, hard-cut to the byte budget so
        // the result never exceeds the configured maximum.
        if (strlen($marker) >= $max) {
            return mb_strcut($body, 0, $max, 'UTF-8');
        }

        $budget = $max - strlen($marker);

        // Trim on a UTF-8 character boundary so we never store a broken glyph.
        return mb_strcut($body, 0, $budget, 'UTF-8') . $marker;
    }
}
