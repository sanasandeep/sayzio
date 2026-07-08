<?php

namespace App\Modules\Common\Support;

use App\Console\Commands\CheckBgTemplateLibrary;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\BgTemplate;
use Illuminate\Support\Facades\Cache;

/**
 * Detects when the biolink background template library has gone empty or
 * dropped below the expected floor, so the admin dashboard can show a
 * persistent warning banner alongside the scheduled
 * `bg-templates:check-library` in-app/email alerts.
 *
 * Why this exists: the biolink editor's Appearance → Page background →
 * Template picker gracefully degrades to a "No templates available yet"
 * empty state when `bg_templates` has zero active rows. The scheduled
 * watchdog ({@see CheckBgTemplateLibrary}) fans out notifications, but an
 * operator who misses those has no persistent surface telling them the
 * library is bare — unlike schema health and the template gallery, which
 * both show dashboard banners. This gives the dashboard the same cheap,
 * defensive signal.
 *
 * The live active count is the primary signal (so the banner appears even
 * before the hourly watchdog first fires, and disappears the moment the
 * library recovers). When the count can't be determined (DB hiccup), we fall
 * back to the watchdog's persisted `bg_template_health.alerting` episode
 * flag rather than throwing.
 *
 * Every entry point is defensive: a DB that is unreachable reports
 * `available => false` rather than throwing, so the dashboard never crashes
 * because of the very tool meant to keep it healthy. Mirrors
 * {@see TemplateGalleryHealth} / {@see SchemaHealth}.
 */
class BgTemplateHealth
{
    private const CACHE_KEY = 'bg_template_health:report';
    private const CACHE_TTL = 120; // seconds

    /**
     * Freshly compute the library status straight from the DB.
     *
     * @return array{
     *   available:bool, active:int, floor:int,
     *   empty:bool, low:bool, shortage:bool, error?:string
     * }
     */
    public static function compute(): array
    {
        $floor = CheckBgTemplateLibrary::MIN_ACTIVE;

        try {
            $active = BgTemplate::query()->where('is_active', true)->count();

            return [
                'available' => true,
                'active'    => $active,
                'floor'     => $floor,
                'empty'     => $active === 0,
                'low'       => $active > 0 && $active < $floor,
                'shortage'  => $active < $floor,
            ];
        } catch (\Throwable $e) {
            // Count unavailable (table missing / DB unreachable). Fall back to
            // the watchdog's persisted episode flag so a known-open shortage
            // still shows a banner; otherwise stay quiet rather than crash.
            $alerting = false;
            try {
                $state    = AppSetting::get('bg_template_health', []);
                $alerting = is_array($state) && ! empty($state['alerting']);
            } catch (\Throwable $inner) {
                // Settings unreachable too — report unavailable below.
            }

            if ($alerting) {
                return [
                    'available' => true,
                    'active'    => 0,
                    'floor'     => $floor,
                    'empty'     => true,
                    'low'       => false,
                    'shortage'  => true,
                    'error'     => $e->getMessage(),
                ];
            }

            return [
                'available' => false,
                'active'    => 0,
                'floor'     => $floor,
                'empty'     => false,
                'low'       => false,
                'shortage'  => false,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Cached wrapper for hot paths (the admin dashboard). A stale-by-two-
     * minutes answer is fine here; recovery clears within the TTL.
     *
     * @return array{available:bool, active:int, floor:int, empty:bool, low:bool, shortage:bool, error?:string}
     */
    public static function cached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::compute());
        } catch (\Throwable $e) {
            // Cache layer itself unhappy — fall back to a direct compute.
            return self::compute();
        }
    }

    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // Best-effort.
        }
    }
}
