<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Creator-issued promo code. Validation lives on the model so both
 * the public checkout and the dashboard preview can reuse it.
 */
class SubscriptionPromoCode extends Model
{
    public const KIND_PERCENT     = 'percent';
    public const KIND_AMOUNT      = 'amount';
    public const KIND_MONTHS_FREE = 'months_free';
    public const KIND_FOUNDER     = 'founder';
    public const KIND_LIFETIME    = 'lifetime';

    public const KINDS = [
        self::KIND_PERCENT, self::KIND_AMOUNT,
        self::KIND_MONTHS_FREE, self::KIND_FOUNDER, self::KIND_LIFETIME,
    ];

    protected $fillable = [
        'user_id', 'code', 'label', 'kind', 'value',
        'applies_to_tier_ids', 'max_redemptions', 'redemptions_count',
        'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value'                => 'integer',
            'max_redemptions'      => 'integer',
            'redemptions_count'    => 'integer',
            'applies_to_tier_ids'  => 'array',
            'expires_at'           => 'datetime',
            'is_active'            => 'boolean',
        ];
    }

    public function creator() { return $this->belongsTo(User::class, 'user_id'); }

    public function isUsable(?SubscriptionTier $tier = null): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions) return false;
        if ($tier && is_array($this->applies_to_tier_ids) && count($this->applies_to_tier_ids) > 0) {
            if (!in_array((int) $tier->id, array_map('intval', $this->applies_to_tier_ids), true)) {
                return false;
            }
        }
        return true;
    }

    /** Returns the discounted price in cents, never negative. */
    public function applyTo(int $priceCents): int
    {
        return match ($this->kind) {
            self::KIND_PERCENT  => max(0, (int) round($priceCents * (1 - min(100, $this->value) / 100))),
            self::KIND_AMOUNT,
            self::KIND_FOUNDER  => max(0, $priceCents - (int) $this->value),
            self::KIND_LIFETIME => 0,
            default             => $priceCents,
        };
    }

    public function describe(): string
    {
        return match ($this->kind) {
            self::KIND_PERCENT     => $this->value . '% off',
            self::KIND_AMOUNT      => '$' . number_format($this->value / 100, 2) . ' off',
            self::KIND_MONTHS_FREE => $this->value . ' month' . ($this->value === 1 ? '' : 's') . ' free',
            self::KIND_FOUNDER     => 'Founder: $' . number_format($this->value / 100, 2) . ' off',
            self::KIND_LIFETIME    => 'Lifetime free',
            default                => 'Promo',
        };
    }
}
