<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
        'monthly_price', 'annual_price',
        'monthly_price_secondary', 'annual_price_secondary',
        'trial_days', 'grace_days', 'refund_window_days',
        'features', 'metadata', 'is_default', 'is_popular', 'status',
        'is_archived', 'is_internal', 'sort_order', 'intro_discount',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'metadata' => 'array',
            'intro_discount' => 'array',
            'monthly_price' => 'decimal:2',
            'annual_price' => 'decimal:2',
            'monthly_price_secondary' => 'decimal:2',
            'annual_price_secondary' => 'decimal:2',
            'is_default' => 'boolean',
            'is_popular' => 'boolean',
            'is_archived' => 'boolean',
            'is_internal' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(\App\Modules\User\Models\User::class);
    }

    public function domains()
    {
        return $this->belongsToMany(\App\Modules\User\Models\Domain::class, 'domain_plan');
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'addon_plan');
    }

    public function prices()
    {
        return $this->morphMany(Price::class, 'priceable');
    }

    /**
     * The normalized first-term introductory discount config for this
     * plan, or null when none is configured / it's effectively off.
     * Always read through this accessor (never the raw `intro_discount`
     * column) so callers get the canonical, validated shape.
     */
    public function introDiscount(): ?array
    {
        return \App\Services\Billing\IntroDiscount::normalize($this->intro_discount);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_archived', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Self-serve / public surfaces only: excludes "internal" plans that
     * admins/staff can assign to a user but which must never appear on the
     * public pricing page, the in-app upgrade page, or the smart-upgrade
     * recommender (e.g. comp / unlimited plans). Internal plans stay fully
     * visible in the admin plan listings and the assign-plan picker.
     */
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    /**
     * Resolve the canonical "default" plan a brand-new account lands on,
     * lineup-proof: it follows the `is_default` flag rather than a hardcoded
     * slug so admins can re-shape the lineup (rename/replace the free tier)
     * without breaking signup. Resolution order:
     *   1. An active (non-archived) plan flagged is_default.
     *   2. Any plan flagged is_default (even if archived) — last resort.
     *   3. The historical `free` slug, then the cheapest active plan.
     * Returns null only if the plans table is genuinely empty.
     */
    public static function defaultPlan(): ?self
    {
        return static::query()->where('is_default', true)->where('is_archived', false)
                ->orderByDesc('is_default')->orderBy('sort_order')->first()
            ?? static::query()->where('is_default', true)
                ->orderByDesc('is_default')->orderBy('sort_order')->first()
            ?? static::query()->where('slug', 'free')->first()
            ?? static::query()->active()->ordered()->first();
    }
}
