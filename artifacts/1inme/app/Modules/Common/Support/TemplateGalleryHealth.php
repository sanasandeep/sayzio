<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\PageTemplate;
use Illuminate\Support\Facades\Cache;

/**
 * Detects when the onboarding template gallery has gone empty — i.e. there are
 * zero active page templates for new users to pick from.
 *
 * Why this exists: the onboarding wizard gracefully degrades to an empty-state
 * escape ("No templates available yet" → Continue to dashboard) when a
 * persona/plan has no active templates to offer. That is the right safety net
 * for the end user, but it fails silently for the operator — nobody is told the
 * catalog has gone empty, so new users could quietly land on a bare setup
 * screen for days without anyone noticing. This gives the rest of the app a
 * cheap, reusable signal so an empty gallery is surfaced proactively (admin
 * dashboard banner, scheduled alert email/in-app) instead of going unnoticed.
 *
 * Every entry point is defensive: a DB that is unreachable (or otherwise
 * errors while we probe it) reports `available => false` rather than throwing,
 * so the surfaces that consume this — the admin dashboard especially — never
 * crash because of the very tool meant to keep them healthy. Mirrors
 * {@see SchemaHealth}.
 */
class TemplateGalleryHealth
{
    private const CACHE_KEY = 'template_gallery_health:report';
    private const CACHE_TTL = 120; // seconds

    /**
     * Freshly compute the active-template count straight from the DB.
     *
     * @return array{available:bool, active:int, empty:bool, error?:string}
     */
    public static function compute(): array
    {
        try {
            $active = PageTemplate::query()->where('is_active', true)->count();

            return [
                'available' => true,
                'active'    => $active,
                'empty'     => $active === 0,
            ];
        } catch (\Throwable $e) {
            // DB unreachable / query failed — report "unknown" so callers
            // degrade gracefully instead of 500-ing.
            return [
                'available' => false,
                'active'    => 0,
                'empty'     => false,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Cached variant for hot paths (dashboard render). Falls back to a direct
     * compute if the cache store itself is unavailable.
     *
     * @return array{available:bool, active:int, empty:bool, error?:string}
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
     * called right after the scheduled check (and whenever a template is
     * created/toggled) so a freshly-restocked (or freshly-emptied) gallery
     * shows up on the dashboard without waiting out the TTL.
     */
    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    public static function activeCount(bool $fresh = false): int
    {
        $report = $fresh ? self::compute() : self::cached();
        return (int) ($report['active'] ?? 0);
    }

    /**
     * True only when we could actually read the DB AND found zero active
     * templates — an unreachable DB is "unknown", not "empty".
     */
    public static function isEmpty(bool $fresh = false): bool
    {
        $report = $fresh ? self::compute() : self::cached();
        return ($report['available'] ?? false) && !empty($report['empty']);
    }
}
