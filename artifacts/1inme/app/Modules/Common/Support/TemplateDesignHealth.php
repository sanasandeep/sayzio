<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Support\TemplateSnapshotValidator;
use Illuminate\Support\Facades\Cache;

/**
 * Detects design issues that have crept into already-saved page & card
 * template snapshots by running each active template's stored snapshot
 * through {@see TemplateSnapshotValidator::issues()}.
 *
 * Why this exists: snapshots are validated at *save* time, but a later code
 * change (a removed block type, a retired design-variant key) can silently
 * invalidate a snapshot that was perfectly valid when it was saved. A broken
 * snapshot doesn't throw — it just renders the template with stripped styling
 * or a blank/unknown block on the public page, and is only visible if an admin
 * happens to open the templates index. This gives the rest of the app a cheap,
 * reusable signal so a degraded template is surfaced proactively (scheduled
 * alert email/in-app) instead of being discovered by visitors.
 *
 * Every entry point is defensive: a DB that is unreachable (or otherwise
 * errors while we probe it) reports `available => false` rather than throwing,
 * so the surfaces that consume this never crash because of the very tool meant
 * to keep them healthy. Mirrors {@see SchemaHealth}.
 */
class TemplateDesignHealth
{
    private const CACHE_KEY = 'template_design_health:report';
    private const CACHE_TTL = 120; // seconds

    /**
     * Freshly compute the broken-template report straight from the DB.
     *
     * @return array{available:bool, scanned:int, broken:array<int,array{kind:string,id:int,name:string,slug:string,issues:array<int,string>}>, error?:string}
     */
    public static function compute(): array
    {
        try {
            $broken  = [];
            $scanned = 0;

            foreach (PageTemplate::query()->where('is_active', true)->cursor() as $tpl) {
                $scanned++;
                $issues = TemplateSnapshotValidator::issues((array) ($tpl->snapshot ?? []), 'page');
                if (!empty($issues)) {
                    $broken[] = self::row('page', $tpl, $issues);
                }
            }

            foreach (CardTemplate::query()->where('is_active', true)->cursor() as $tpl) {
                $scanned++;
                $issues = TemplateSnapshotValidator::issues((array) ($tpl->snapshot ?? []), 'card');
                if (!empty($issues)) {
                    $broken[] = self::row('card', $tpl, $issues);
                }
            }

            return [
                'available' => true,
                'scanned'   => $scanned,
                'broken'    => $broken,
            ];
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'scanned'   => 0,
                'broken'    => [],
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  \App\Modules\Admin\Models\PageTemplate|\App\Modules\Admin\Models\CardTemplate  $tpl
     * @param  array<int,string>  $issues
     * @return array{kind:string,id:int,name:string,slug:string,issues:array<int,string>}
     */
    private static function row(string $kind, $tpl, array $issues): array
    {
        return [
            'kind'   => $kind,
            'id'     => (int) $tpl->id,
            'name'   => (string) ($tpl->name ?? ('#' . $tpl->id)),
            'slug'   => (string) ($tpl->slug ?? ''),
            'issues' => array_values($issues),
        ];
    }

    /**
     * Cached variant for hot paths. Falls back to a direct compute if the
     * cache store itself is unavailable.
     *
     * @return array{available:bool, scanned:int, broken:array<int,array{kind:string,id:int,name:string,slug:string,issues:array<int,string>}>, error?:string}
     */
    public static function cached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::compute());
        } catch (\Throwable $e) {
            return self::compute();
        }
    }

    /**
     * Drop the cached report so the next read reflects reality immediately —
     * called right after the scheduled check.
     */
    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /**
     * Stable identifier for a broken template ("page:12") used by the alert
     * command to detect when the *set* of broken templates changes between
     * runs (so newly-broken templates re-alert even mid-episode).
     */
    public static function ref(array $row): string
    {
        return ($row['kind'] ?? '?') . ':' . ($row['id'] ?? '?');
    }
}
