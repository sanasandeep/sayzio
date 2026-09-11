<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteStat extends Model
{
    protected $table = 'site_stats';

    /** Cache key for the active-stats payload (shared with the warmer). */
    public const ACTIVE_CACHE_KEY = 'home:site_stats:active';

    /**
     * Active stats (ordered), cached for 5 minutes. Read by both the hero
     * trust band and the stats section on the marketing home page; caching the
     * raw attribute arrays + rehydrating avoids two ~750ms RDS queries per
     * render. Callers slice with ->take() in memory.
     */
    public static function cachedActive(int $ttl = 300): Collection
    {
        $rows = Cache::remember(
            self::ACTIVE_CACHE_KEY,
            $ttl,
            fn () => self::buildActiveRows()
        );

        return static::hydrate($rows);
    }

    /**
     * Build the cacheable payload (plain attribute arrays). Shared by
     * cachedActive() and MarketingPageCache::warm().
     */
    public static function buildActiveRows(): array
    {
        return static::active()->ordered()->get()->map(fn ($m) => $m->getAttributes())->all();
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

    /**
     * The value as it should be READ, with thousands grouped the way the
     * audience groups them.
     *
     * The stored values were written with Indian grouping -- `3,75,000`,
     * `1,05,000`, `1,50,000`. That is correct in Hyderabad and a typo
     * everywhere else, and the homepage showed `375,000+` in the hero trust
     * line a few hundred pixels above `3,75,000+` in the stats band. Two
     * spellings of the same number on one screen is worse than either
     * spelling alone: the reader does not conclude "different convention",
     * they conclude one of them is wrong, and then quietly discount every
     * other figure on the page.
     *
     * Grouping is presentation, so it is decided here rather than in the
     * stored string -- an admin typing `3,75,000` into the stats screen gets
     * `375,000` on the page, and the two can no longer drift apart.
     *
     * Only plain numbers are touched. A value an admin wrote as `1.5M`, `99.9%`
     * or `24/7` is a deliberate format and passes through untouched.
     */
    public function displayValue(): string
    {
        $raw = trim((string) $this->value);

        // Anything that is not digits and separators is a deliberate format.
        if ($raw === '' || ! preg_match('/^[0-9][0-9,]*(\.[0-9]+)?$/', $raw)) {
            return $raw;
        }

        $target = $this->numericTarget();
        if ($target === null) {
            return $raw;
        }

        $decimals = str_contains($raw, '.')
            ? strlen(substr(strrchr($raw, '.'), 1))
            : 0;

        return number_format($target, $decimals);
    }
}
