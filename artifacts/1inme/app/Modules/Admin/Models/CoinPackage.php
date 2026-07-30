<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class CoinPackage extends Model
{
    /**
     * Billing-cycle slot used to store the optional "compare-at" / original
     * (strike-off) price in the shared polymorphic `prices` table, alongside
     * the live `monthly` slot used for the charged amount. Display-only: the
     * checkout always charges the live price.
     */
    public const COMPARE_CYCLE = 'compare';

    /**
     * Fallback internal API-budget allocation (% of price) used when a
     * package pre-dates the allocation column and admin has not set one.
     */
    public const DEFAULT_API_BUDGET_PCT = 70.0;

    protected $fillable = [
        'name', 'slug', 'description', 'best_for', 'coin_amount', 'bonus_coins',
        'status', 'is_archived', 'sort_order', 'api_budget_pct',
    ];

    protected function casts(): array
    {
        return [
            'coin_amount'    => 'integer',
            'bonus_coins'    => 'integer',
            'is_archived'    => 'boolean',
            'api_budget_pct' => 'float',
        ];
    }

    /**
     * Hidden internal allocation: % of the purchase price budgeted for API
     * costs. NEVER expose on user-facing surfaces — admin-only.
     */
    public function apiBudgetPct(): float
    {
        $pct = $this->api_budget_pct;
        return $pct === null ? self::DEFAULT_API_BUDGET_PCT : (float) $pct;
    }

    /** Platform margin % — always the complement of the API budget. */
    public function marginPct(): float
    {
        return round(100.0 - $this->apiBudgetPct(), 2);
    }

    public function prices()
    {
        return $this->morphMany(Price::class, 'priceable');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_archived', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Total coins customer receives = base + bonus. */
    public function totalCoins(): int
    {
        return (int) $this->coin_amount + (int) $this->bonus_coins;
    }

    /**
     * Read the optional original ("compare-at") price for a currency, in
     * MINOR units (cents/paise). Returns 0 when none is set. Uses the
     * eager-loaded `prices` relation when available to avoid N+1, else
     * issues a single targeted query.
     */
    public function originalPriceMinor(string $currency): int
    {
        if ($this->relationLoaded('prices')) {
            $row = $this->prices->first(fn ($p) => $p->currency === $currency
                && $p->billing_cycle === self::COMPARE_CYCLE
                && (bool) $p->is_active);
        } else {
            $row = $this->prices()
                ->where('currency', $currency)
                ->where('billing_cycle', self::COMPARE_CYCLE)
                ->where('is_active', true)
                ->first();
        }
        return $row ? (int) $row->amount_minor_units : 0;
    }

    /**
     * Shared "should we show a strikethrough?" rule for every surface. Given
     * the live price (in minor units), returns the original-price display
     * payload only when an original is set AND is strictly higher than the
     * live price; otherwise null (render just the single price).
     *
     * @return array{amount_minor:int, formatted:string}|null
     */
    public function originalPriceDisplay(string $currency, int $currentMinor): ?array
    {
        $original = $this->originalPriceMinor($currency);
        if ($original <= 0 || $original <= $currentMinor) {
            return null;
        }
        return [
            'amount_minor' => $original,
            'formatted'    => \App\Services\PricingResolver::money($original, $currency),
        ];
    }
}
