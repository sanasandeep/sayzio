<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class PageTemplate extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'description', 'thumbnail_url',
        'plan_tier', 'recommended_personas', 'is_active', 'sort_order', 'snapshot',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'snapshot' => 'array',
        'recommended_personas' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Templates available to a user with the given plan slug.
     * Empty plan_tier = open to all. Otherwise the template's required tier
     * sort_order must be <= the user's plan sort_order (higher tier users
     * see lower-tier templates).
     */
    public function scopeAvailableForPlan($query, ?string $userPlanSlug)
    {
        $ranks = Plan::pluck('sort_order', 'slug');
        $userRank = $userPlanSlug ? ($ranks[$userPlanSlug] ?? -1) : -1;
        $allowedTiers = $ranks->filter(fn($rank) => $rank <= $userRank)->keys()->all();
        return $query->where(function ($q) use ($allowedTiers) {
            $q->whereNull('plan_tier')->orWhere('plan_tier', '');
            if (!empty($allowedTiers)) {
                $q->orWhereIn('plan_tier', $allowedTiers);
            }
        });
    }

    /**
     * True when updated_at has drifted past created_at by more than the
     * shared tolerance — i.e. an admin has saved this row at least once
     * since it was created/seeded. Same signal that
     * `templates:refresh-persona-seed` uses to detect admin edits, so
     * the admin "Customized" badge and that command stay in agreement.
     */
    public function wasCustomized(): bool
    {
        if (!$this->updated_at || !$this->created_at) {
            return false;
        }
        return $this->updated_at->getTimestamp() - $this->created_at->getTimestamp()
            > \App\Console\Commands\RefreshPersonaSeed::EDIT_DRIFT_TOLERANCE;
    }

    public static function categories(): array
    {
        // Legacy "shape" categories kept for backwards-compatibility with
        // existing seed data. Personas are appended below so admins can
        // pick a persona-as-category for new templates and the same
        // dropdown stays in sync with the onboarding picker.
        $base = [
            'general' => 'General',
            'event' => 'Event',
            'product' => 'Product',
            'portfolio' => 'Portfolio',
        ];
        // Personas (slug => label). Where a persona slug overlaps a
        // legacy category key, the persona label wins so admins see a
        // single, consistent name.
        $personas = \App\Modules\User\Services\PersonaCatalog::slugLabelMap();
        return array_merge($base, $personas);
    }
}
