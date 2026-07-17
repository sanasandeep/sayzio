<?php

namespace App\Modules\Common\Models;

use App\Modules\Common\Support\DatabaseErrors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class SiteAssistantPageHint extends Model
{
    protected $fillable = [
        'label', 'route_pattern', 'surface', 'description',
        'suggested_actions', 'disable_widget', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'suggested_actions' => 'array',
            'is_active'         => 'bool',
            'disable_widget'    => 'bool',
            'priority'          => 'int',
        ];
    }

    /** Cache key prefix for the per-surface hint rows (shared with the warmer). */
    public const SURFACE_CACHE_PREFIX = 'site_assistant:hints:';

    /**
     * Build the cacheable per-surface payload (plain attribute arrays).
     * Shared by resolve() and MarketingPageCache::warm().
     */
    public static function buildRowsForSurface(string $surface): array
    {
        return static::where('is_active', true)
            ->whereIn('surface', [$surface, 'any'])
            ->orderBy('priority')
            ->get()
            ->map(fn ($m) => $m->getAttributes())
            ->all();
    }

    /**
     * Pick the best-matching active hint for a route name + surface.
     * route_pattern uses fnmatch-style wildcards (e.g. user.links.*).
     */
    public static function resolve(?string $routeName, ?string $path, string $surface): ?self
    {
        // Resolved on every page render (global widget partial) — cache the raw
        // attribute rows per surface for 5 minutes and rehydrate, so the cross-
        // region RDS isn't hit on each request. getAttributes() keeps the JSON
        // `suggested_actions` column in its raw form so the `array` cast still
        // decodes correctly after hydrate().
        try {
            $rows = Cache::remember(
                self::SURFACE_CACHE_PREFIX . $surface,
                300,
                fn () => static::buildRowsForSurface($surface)
            );
        } catch (QueryException $e) {
            // The widget partial renders on EVERY page — including error pages
            // triggered by a broken/un-migrated database. A missing
            // site_assistant_page_hints table must degrade to "no hint", never
            // cascade into a 500 while rendering an error page.
            if (DatabaseErrors::isMissingTable($e)) {
                return null;
            }
            throw $e;
        }
        $hints = static::hydrate($rows);

        $routeName = (string) $routeName;
        $path      = (string) $path;

        foreach ($hints as $h) {
            $p = (string) $h->route_pattern;
            if ($p === '') continue;
            if (fnmatch($p, $routeName) || fnmatch($p, $path)) {
                return $h;
            }
        }
        return null;
    }
}
