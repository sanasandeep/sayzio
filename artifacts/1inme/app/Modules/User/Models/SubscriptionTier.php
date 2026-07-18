<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One creator-defined subscription tier (free or paid). The platform
 * takes 0% of the price; gateway fees come out of the creator's
 * payout (see Task #1208).
 *
 * Sort order doubles as the tier *level* — a fan subscribed to a tier
 * with sort_order >= a post's lowest gating tier sees that post. This
 * keeps the access policy a single column compare and avoids a
 * separate "tier groups" table.
 */
class SubscriptionTier extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'is_free', 'is_active', 'sort_order',
        'price_monthly_cents', 'price_yearly_cents', 'currency',
        'perks', 'color', 'badge',
    ];

    protected function casts(): array
    {
        return [
            'is_free'             => 'boolean',
            'is_active'           => 'boolean',
            'sort_order'          => 'integer',
            'price_monthly_cents' => 'integer',
            'price_yearly_cents'  => 'integer',
            'perks'               => 'array',
        ];
    }

    public function creator() { return $this->belongsTo(User::class, 'user_id'); }
    public function subscriptions() { return $this->hasMany(CreatorSubscription::class, 'tier_id'); }

    public static function makeSlug(int $userId, string $name): string
    {
        $base = Str::slug($name) ?: 'tier';
        return \App\Support\UniqueSuffix::resolve(self::query()->where('user_id', $userId), $base);
    }

    public function priceForCycle(string $cycle): int
    {
        if ($this->is_free) return 0;
        return $cycle === 'yearly'
            ? (int) ($this->price_yearly_cents ?? ($this->price_monthly_cents * 12))
            : (int) $this->price_monthly_cents;
    }

    public function yearlyDiscountPercent(): ?int
    {
        if ($this->is_free || !$this->price_yearly_cents || !$this->price_monthly_cents) return null;
        $full = $this->price_monthly_cents * 12;
        if ($full <= 0) return null;
        return (int) round((1 - $this->price_yearly_cents / $full) * 100);
    }

    /**
     * Default perks rendered when the editor leaves the field blank,
     * so the public tier card never shows a bare button.
     */
    public function visiblePerks(): array
    {
        $perks = is_array($this->perks) ? array_filter(array_map('trim', $this->perks), 'strlen') : [];
        if (count($perks)) return array_values($perks);
        if ($this->is_free) {
            return ['Public posts', 'Reactions and comments', 'Direct support — no fee to follow'];
        }
        return ['Tier-only posts', 'Member badge on your comments', 'Cancel anytime'];
    }
}
