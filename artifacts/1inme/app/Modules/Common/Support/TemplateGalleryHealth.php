<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Services\PersonaCatalog;
use Illuminate\Support\Facades\Cache;

/**
 * Detects when the onboarding template gallery has gone empty — either the
 * whole catalog (zero active page templates for anyone) OR a specific persona
 * (zero active templates that recommend it), so new users of that persona get
 * no tailored starting point.
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
 * Coverage is bucketed per persona: onboarding partitions templates into a
 * "Recommended for {persona}" section (templates whose `recommended_personas`
 * tag list contains the picked persona) and a "Browse all" section. A persona
 * with zero active recommended templates still sees the browse-all list, so the
 * gallery isn't literally blank — but that persona gets no tailored
 * recommendation, which is the coverage gap this surfaces. Because onboarding
 * shows every active template (paid ones rendered as locked, not hidden), plan
 * tier never makes the gallery blank; instead we compute, per persona, whether
 * any recommended template is reachable at the entry-level (default) plan tier,
 * so admins can also spot personas whose recommendations are all paywalled.
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
     * Freshly compute the active-template count + per-persona coverage
     * straight from the DB.
     *
     * @return array{
     *   available:bool, active:int, empty:bool,
     *   personas?:array<int,array{slug:string,label:string,count:int,free:int}>,
     *   uncovered?:array<int,array{slug:string,label:string}>,
     *   gated?:array<int,array{slug:string,label:string}>,
     *   has_gaps?:bool, error?:string
     * }
     */
    public static function compute(): array
    {
        try {
            // Pull just the columns we need for every active template once,
            // then bucket in PHP — avoids a query per persona.
            $templates = PageTemplate::query()
                ->where('is_active', true)
                ->get(['recommended_personas', 'plan_tier']);

            $active = $templates->count();

            // Plan tiers reachable by an entry-level (default plan) user, so we
            // can tell whether a persona's recommendations are all paywalled.
            // Mirrors PageTemplate::scopeAvailableForPlan's rule: a template is
            // reachable when it has no required tier, or its tier's sort_order
            // is <= the user's plan sort_order.
            $ranks = Plan::query()->pluck('sort_order', 'slug');
            $baseSlug = Plan::defaultPlan()?->slug;
            $baseRank = $baseSlug !== null ? ($ranks[$baseSlug] ?? -1) : -1;
            $baseTiers = $ranks->filter(fn ($rank) => $rank <= $baseRank)->keys()->all();

            $isFreeTier = static function ($planTier) use ($baseTiers): bool {
                return $planTier === null || $planTier === '' || in_array($planTier, $baseTiers, true);
            };

            $personas = [];
            $uncovered = [];
            $gated = [];

            foreach (PersonaCatalog::slugLabelMap() as $slug => $label) {
                $recommended = $templates->filter(function ($tpl) use ($slug) {
                    $tags = $tpl->recommended_personas ?? [];
                    return is_array($tags) && in_array($slug, $tags, true);
                });
                $count = $recommended->count();
                $free  = $recommended->filter(fn ($tpl) => $isFreeTier($tpl->plan_tier))->count();

                $personas[] = [
                    'slug'  => $slug,
                    'label' => $label,
                    'count' => $count,
                    'free'  => $free,
                ];

                if ($count === 0) {
                    // No tailored template at all for this persona.
                    $uncovered[] = ['slug' => $slug, 'label' => $label];
                } elseif ($free === 0) {
                    // Has recommendations, but every one of them is locked
                    // behind a paid tier — entry-level users see an all-locked
                    // recommended row. Advisory, not a hard gap.
                    $gated[] = ['slug' => $slug, 'label' => $label];
                }
            }

            return [
                'available' => true,
                'active'    => $active,
                'empty'     => $active === 0,
                'personas'  => $personas,
                'uncovered' => $uncovered,
                'gated'     => $gated,
                // A hard gap is at least one persona with zero recommended
                // templates (the whole-catalog-empty case is the extreme where
                // every persona is uncovered). Paywalled-only personas are
                // advisory and intentionally do NOT open an episode, since a
                // paid-only persona can be a deliberate product choice.
                'has_gaps'  => !empty($uncovered),
            ];
        } catch (\Throwable $e) {
            // DB unreachable / query failed — report "unknown" so callers
            // degrade gracefully instead of 500-ing.
            return [
                'available' => false,
                'active'    => 0,
                'empty'     => false,
                'personas'  => [],
                'uncovered' => [],
                'gated'     => [],
                'has_gaps'  => false,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Cached variant for hot paths (dashboard render). Falls back to a direct
     * compute if the cache store itself is unavailable. Shape mirrors compute().
     *
     * @return array{available:bool, active:int, empty:bool, has_gaps:bool, personas:array<int,array{slug:string,label:string,count:int,free:int}>, uncovered:array<int,array{slug:string,label:string,count:int,free:int}>, gated:array<int,array{slug:string,label:string,count:int,free:int}>, error?:string}
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

    /**
     * Personas with zero active recommended templates. Empty when the DB is
     * unreachable (unknown, not "all covered") or every persona is covered.
     *
     * @return array<int,array{slug:string,label:string}>
     */
    public static function uncoveredPersonas(bool $fresh = false): array
    {
        $report = $fresh ? self::compute() : self::cached();
        if (!($report['available'] ?? false)) {
            return [];
        }
        return $report['uncovered'] ?? [];
    }

    /**
     * True when we could read the DB AND at least one persona has no active
     * recommended template — an unreachable DB is "unknown", not "gap".
     */
    public static function hasGaps(bool $fresh = false): bool
    {
        $report = $fresh ? self::compute() : self::cached();
        return ($report['available'] ?? false) && !empty($report['has_gaps']);
    }
}
