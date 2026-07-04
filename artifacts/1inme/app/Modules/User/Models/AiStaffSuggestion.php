<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Task #3523 — a confirm-before-act suggestion raised by an AiStaff
 * (currently the billing domain: draft_invoice / chase_invoice). Mirrors
 * MarketingStrategySuggestion's pending -> applied/dismissed/error
 * lifecycle so a suggestion is claimed atomically before it is turned
 * into a real, owned object or a real outbound email.
 */
class AiStaffSuggestion extends Model
{
    protected $table = 'ai_staff_suggestions';

    public const KIND_DRAFT_INVOICE = 'draft_invoice';
    public const KIND_CHASE_INVOICE = 'chase_invoice';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPLIED   = 'applied';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ERROR     = 'error';

    protected $fillable = [
        'ai_staff_id', 'user_id', 'kind', 'status', 'payload', 'title', 'message',
        'applied_ref_type', 'applied_ref_id', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'          => 'array',
            'applied_ref_id'   => 'integer',
            'applied_at'       => 'datetime',
        ];
    }

    public function aiStaff(): BelongsTo
    {
        return $this->belongsTo(AiStaff::class, 'ai_staff_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }
}
