<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class CardTemplate extends Model
{
    /**
     * Tolerance window (seconds) for treating a row as untouched after
     * the seeder created it. Mirrors the page-template heuristic so the
     * "Customized" admin badge and the seeder's preserve-edits logic
     * stay in agreement.
     */
    public const EDIT_DRIFT_TOLERANCE = 2;

    protected $fillable = [
        'name', 'slug', 'category', 'description', 'thumbnail_url',
        'plan_tier', 'is_active', 'sort_order', 'snapshot', 'seed_version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'snapshot' => 'array',
        'seed_version' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

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
     * tolerance — i.e. an admin has saved this row at least once since
     * the seeder created it. Used by the seeder's re-run logic to
     * preserve admin edits.
     */
    public function wasCustomized(): bool
    {
        if (!$this->updated_at || !$this->created_at) {
            return false;
        }
        return $this->updated_at->getTimestamp() - $this->created_at->getTimestamp()
            > self::EDIT_DRIFT_TOLERANCE;
    }

    /**
     * Concrete design problems with this row's stored snapshot — unknown
     * block types and stale design-variant keys that would silently
     * degrade on the public page. Empty array = clean. Drives the
     * "Design issues" badge and one-click fix flow on the admin index.
     *
     * @return array<int,string>
     */
    public function designIssues(): array
    {
        return \App\Modules\User\Support\TemplateSnapshotValidator::issues(
            (array) ($this->snapshot ?? []),
            'card'
        );
    }

    public static function categories(): array
    {
        return [
            'general' => 'General',
            'hero' => 'Hero',
            'cta' => 'Call to Action',
            'social' => 'Buzz',
            'contact' => 'Contact',
            'product' => 'Product',
            'event' => 'Event',
            'gallery' => 'Gallery',
        ];
    }
}
