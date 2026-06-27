<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\User;
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
}
