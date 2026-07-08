<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\MarketingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records server-side click events on marketing CTAs. Currently driven by
 * the landing-page pricing teaser drill-downs (source = "landing_pricing_teaser").
 *
 * The endpoint is intentionally permissive (no auth, CSRF-exempted in
 * bootstrap/app.php) because it fires from anonymous landing-page visits.
 * `source` and `target` are validated against an allow-list so the table
 * can't be polluted with arbitrary strings.
 */
class MarketingEventController extends Controller
{
    /**
     * Allowed (source, target) combinations. Adding a new tracked CTA
     * requires registering it here so that admins always see a stable
     * list of event types in the report.
     */
    public const ALLOWED = [
        'landing_pricing_teaser' => [
            'pricing',
            'coins',
            'plan_free',
            'plan_paid',
        ],
        'landing_home_cta' => [
            'hero',
            'audience',
            'how_it_works',
            'final_cta',
        ],
        // "10x your connections" tip cards shown alongside events. The
        // surface (events directory vs a public event page) is encoded in
        // the source; the target is the suggested link `type` from
        // SitePagesContent::eventConnectionTips() — keep these two lists in
        // lockstep when a new tip type is added there (Task #3684).
        'event_tips_directory' => [
            'biolink',
            'vcf',
            'calendar',
            'reviews',
        ],
        'event_tips_event' => [
            'biolink',
            'vcf',
            'calendar',
            'reviews',
        ],
    ];

    /**
     * Source used by the home "What you can create" showcase cards when a
     * card links to a live demo page. Targets are dynamic (the link-type
     * slug, i.e. the demo alias minus its `demo-type-` prefix) and are
     * validated against the seeded demo pages instead of the static
     * ALLOWED list — see {@see homeShowcaseDemoTargets()}.
     */
    public const HOME_SHOWCASE_DEMO_SOURCE = 'home_showcase_demo';

    /**
     * The currently-valid targets for the home-showcase demo source: one
     * slug per live seeded demo page. Shares the /demos gallery cache so
     * the warm path is query-free; a cache/DB failure fails closed (no
     * targets accepted) rather than 500ing the beacon endpoint.
     *
     * @return array<int, string>
     */
    public static function homeShowcaseDemoTargets(): array
    {
        try {
            $demoData = \Illuminate\Support\Facades\Cache::remember(
                \App\Modules\Common\Controllers\SitePageController::DEMOS_CACHE_KEY,
                300,
                fn () => \App\Modules\Common\Controllers\SitePageController::buildDemosData()
            );
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach (array_keys((array) ($demoData['links'] ?? [])) as $alias) {
            $slug = \Illuminate\Support\Str::after((string) $alias, 'demo-type-');
            if ($slug !== '' && $slug !== (string) $alias) {
                $out[] = $slug;
            }
        }
        return $out;
    }

    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:64'],
            'target' => ['required', 'string', 'max:64'],
        ]);

        $source = $data['source'];
        $target = $data['target'];

        $allowed = $source === self::HOME_SHOWCASE_DEMO_SOURCE
            ? in_array($target, self::homeShowcaseDemoTargets(), true)
            : (isset(self::ALLOWED[$source]) && in_array($target, self::ALLOWED[$source], true));

        if (! $allowed) {
            return response()->json(['ok' => false, 'error' => 'unknown_event'], 422);
        }

        MarketingEvent::create([
            'source'      => $source,
            'target'      => $target,
            'ip_address'  => $request->ip(),
            'referrer'    => mb_substr((string) $request->header('referer', ''), 0, 1024) ?: null,
            'occurred_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
