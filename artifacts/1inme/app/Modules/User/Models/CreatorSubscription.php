<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fan ↔ creator subscription row. Exactly one *active* row per
 * (fan, creator) at a time — switching tiers updates the existing
 * row in-place rather than creating a second active sub.
 */
class CreatorSubscription extends Model
{
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_PAUSED   = 'paused';

    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_YEARLY  = 'yearly';

    protected $fillable = [
        'fan_user_id', 'creator_user_id', 'tier_id',
        'billing_cycle', 'status',
        'price_cents', 'currency',
        'started_at', 'current_period_start', 'current_period_end',
        'canceled_at', 'cancel_at_period_end', 'last_payment_at',
        'renewal_reminder_sent_at',
        'gateway', 'gateway_subscription_id', 'promo_code_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at'           => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end'   => 'datetime',
            'canceled_at'          => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'last_payment_at'      => 'datetime',
            'renewal_reminder_sent_at' => 'datetime',
            'price_cents'          => 'integer',
            'metadata'             => 'array',
        ];
    }

    public function fan()     { return $this->belongsTo(User::class, 'fan_user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'creator_user_id'); }
    public function tier()    { return $this->belongsTo(SubscriptionTier::class, 'tier_id'); }
    public function promoCode() { return $this->belongsTo(SubscriptionPromoCode::class, 'promo_code_id'); }

    /** Live for any access policy purpose (active or grace period). */
    public function isCurrent(): bool
    {
        if (in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true)) return true;
        if ($this->status === self::STATUS_PAST_DUE && $this->current_period_end && $this->current_period_end->isFuture()) {
            return true;
        }
        return false;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE   => $this->cancel_at_period_end ? 'Active · cancels at period end' : 'Active',
            self::STATUS_TRIALING => 'Trialing',
            self::STATUS_PAST_DUE => 'Past due',
            self::STATUS_CANCELED => 'Canceled',
            self::STATUS_PAUSED   => 'Paused',
            default               => ucfirst($this->status ?: 'unknown'),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE   => $this->cancel_at_period_end ? 'amber' : 'emerald',
            self::STATUS_TRIALING => 'sky',
            self::STATUS_PAST_DUE => 'rose',
            self::STATUS_CANCELED => 'slate',
            self::STATUS_PAUSED   => 'amber',
            default               => 'slate',
        };
    }
}
