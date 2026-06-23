<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteStat extends Model
{
    protected $table = 'site_stats';

    /**
     * Active stats (ordered), cached for 5 minutes. Read by both the hero
     * trust band and the stats section on the marketing home page; caching the
     * raw attribute arrays + rehydrating avoids two ~750ms RDS queries per
     * render. Callers slice with ->take() in memory.
     */
    public static function cachedActive(int $ttl = 300): Collection
    {
        $rows = Cache::remember(
            'home:site_stats:active',
            $ttl,
            fn () => static::active()->ordered()->get()->map(fn ($m) => $m->getAttributes())->all()
        );

        return static::hydrate($rows);
    }

    protected $fillable = [
        'label', 'value', 'suffix', 'icon', 'color', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeOrdered($q) { return $q->orderBy('sort_order')->orderBy('id'); }

    /** Numeric portion stripped of commas/letters, for the count-up animation. */
    public function numericTarget(): ?float
    {
        $clean = preg_replace('/[^0-9.]/', '', (string) $this->value);
        return $clean === '' ? null : (float) $clean;
    }
}
