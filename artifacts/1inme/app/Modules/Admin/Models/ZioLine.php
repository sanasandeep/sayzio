<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A line Zio speaks in the homepage hero bubble.
 *
 * Same shape and same caching contract as {@see SiteStat}: the hero reads
 * cachedActive() directly from the blade, MarketingPageCache::warm() keeps
 * the key hot, and only plain attribute arrays are cached (never serialized
 * models, which do not survive the file cache).
 *
 * The hero is on the initial, deliberately lean response, so a cache MISS
 * here costs a query on the critical path. That is the same deal SiteStat
 * already makes for the hero trust band -- and the reason flushCache() is
 * called on every write rather than waiting out the TTL, so an admin edit
 * shows up on the next page load instead of within five minutes.
 */
class ZioLine extends Model
{
    protected $table = 'zio_lines';

    /** Cache key for the active-lines payload (shared with the warmer). */
    public const ACTIVE_CACHE_KEY = 'home:zio_lines:active';

    /**
     * The bubble is a fixed ellipse, so a line has to fit inside a curve
     * rather than stretch a box. Past about this much it stops fitting the
     * shape at the sizes the hero uses. Enforced in the admin form, and
     * surfaced there as a live counter.
     */
    public const MAX_LENGTH = 72;

    protected $fillable = ['text', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeOrdered($q) { return $q->orderBy('sort_order')->orderBy('id'); }

    /** Active lines (ordered), cached for 5 minutes. */
    public static function cachedActive(int $ttl = 300): Collection
    {
        $rows = Cache::remember(self::ACTIVE_CACHE_KEY, $ttl, fn () => self::buildActiveRows());

        return static::hydrate($rows);
    }

    /**
     * Build the cacheable payload (plain attribute arrays). Shared by
     * cachedActive() and MarketingPageCache::warm().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buildActiveRows(): array
    {
        return static::active()->ordered()->get()->map(fn ($m) => $m->getAttributes())->all();
    }

    public static function flushCache(): void
    {
        Cache::forget(self::ACTIVE_CACHE_KEY);
    }

    /**
     * Just the strings, for the hero. Returns [] when nothing is active, so
     * the blade can fall back rather than render an empty bubble.
     *
     * @return list<string>
     */
    public static function activeTexts(): array
    {
        return self::cachedActive()->pluck('text')->all();
    }
}
