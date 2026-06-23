<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Testimonial extends Model
{
    /**
     * Active testimonials (all rows), cached for 5 minutes.
     *
     * The marketing home page reads testimonials on every render; over the
     * cross-region RDS that is a ~750ms query each. Caching the raw attribute
     * arrays (not the Eloquent models — those don't survive the file cache)
     * and rehydrating keeps the home path query-free between refreshes.
     */
    public static function cachedActive(int $ttl = 300): Collection
    {
        $rows = Cache::remember(
            'home:testimonials:active',
            $ttl,
            fn () => static::active()->ordered()->get()->map(fn ($m) => $m->getAttributes())->all()
        );

        return static::hydrate($rows);
    }

    protected $fillable = [
        'quote', 'author_name', 'author_role', 'accent_color',
        'rating', 'row', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'rating'     => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->author_name), 0, 1));
    }
}
