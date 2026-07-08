<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Controllers\MarketingEventController;
use App\Modules\Common\Models\MarketingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin report: click counts per marketing CTA target, grouped by source.
 * Powers Admin → Marketing → Marketing Events.
 */
class MarketingEventStatsController extends Controller
{
    private const ALLOWED_WINDOWS = [7, 30, 90, 365];
    private const DEFAULT_WINDOW = 30;

    /**
     * Friendly labels for the (source, target) pairs registered in
     * MarketingEventController::ALLOWED. Anything missing falls back to
     * the raw target slug so a newly-registered CTA still renders.
     */
    private const TARGET_LABELS = [
        'landing_pricing_teaser' => [
            'pricing'          => 'See all plans (/pricing)',
            'coins'            => 'Coin packages tab (/pricing?view=coins)',
            'plan_free'        => 'Plan CTA — Free tier',
            'plan_paid'        => 'Plan CTA — Paid tier',
        ],
        'landing_home_cta' => [
            'hero'         => 'Hero — "Make mine free"',
            'audience'     => 'Audience cards CTA',
            'how_it_works' => 'How it works — "Start free — no card"',
            'final_cta'    => 'Closing section — "Sign up free"',
        ],
        'event_tips_directory' => [
            'biolink'  => 'Link-in-bio tip',
            'vcf'      => 'Contact card (vCard) tip',
            'calendar' => 'Follow calendar tip',
            'reviews'  => 'Collect reviews tip',
        ],
        'event_tips_event' => [
            'biolink'  => 'Link-in-bio tip',
            'vcf'      => 'Contact card (vCard) tip',
            'calendar' => 'Follow calendar tip',
            'reviews'  => 'Collect reviews tip',
        ],
    ];

    private const SOURCE_LABELS = [
        'landing_pricing_teaser' => 'Landing pricing teaser',
        'landing_home_cta'       => 'Landing home CTAs',
        'event_tips_directory'   => 'Connection tips — Events directory',
        'event_tips_event'       => 'Connection tips — Event page',
        MarketingEventController::HOME_SHOWCASE_DEMO_SOURCE => 'Home showcase — demo cards',
    ];

    public function index(Request $request)
    {
        $days = (int) $request->query('days', self::DEFAULT_WINDOW);
        if (!in_array($days, self::ALLOWED_WINDOWS, true)) {
            $days = self::DEFAULT_WINDOW;
        }
        $since = now()->subDays($days);

        // Single aggregated read: GROUP BY (source, target).
        $rows = MarketingEvent::query()
            ->where('occurred_at', '>=', $since)
            ->select('source', 'target', DB::raw('COUNT(*) as n'))
            ->groupBy('source', 'target')
            ->get();

        $counts = [];
        $totalsBySource = [];
        foreach ($rows as $row) {
            $counts[$row->source][$row->target] = (int) $row->n;
            $totalsBySource[$row->source] = ($totalsBySource[$row->source] ?? 0) + (int) $row->n;
        }

        // Build a stable display list from the allow-list so 0-count
        // targets still appear (useful when a CTA stops being clicked).
        // The home-showcase demo source has dynamic targets (one per live
        // seeded demo page), so its target list is the union of the current
        // demo slugs and anything actually recorded in the window — a demo
        // that was later unseeded keeps its historical counts visible.
        $demoSource = MarketingEventController::HOME_SHOWCASE_DEMO_SOURCE;
        $demoTargets = array_values(array_unique(array_merge(
            MarketingEventController::homeShowcaseDemoTargets(),
            array_keys($counts[$demoSource] ?? [])
        )));

        $allSources = MarketingEventController::ALLOWED
            + [$demoSource => $demoTargets];

        $sections = [];
        foreach ($allSources as $source => $targets) {
            $sourceTotal = $totalsBySource[$source] ?? 0;
            $rowsOut = [];
            foreach ($targets as $t) {
                $count = $counts[$source][$t] ?? 0;
                $rowsOut[] = [
                    'target' => $t,
                    'label'  => self::TARGET_LABELS[$source][$t] ?? $t,
                    'count'  => $count,
                    'pct'    => $sourceTotal > 0 ? ($count / $sourceTotal * 100) : 0.0,
                ];
            }
            usort($rowsOut, fn ($a, $b) => $b['count'] <=> $a['count']);

            $sections[] = [
                'source'      => $source,
                'sourceLabel' => self::SOURCE_LABELS[$source] ?? $source,
                'total'       => $sourceTotal,
                'rows'        => $rowsOut,
            ];
        }

        $grandTotal = array_sum($totalsBySource);

        return view('admin.marketing-events.index', [
            'days'           => $days,
            'allowedWindows' => self::ALLOWED_WINDOWS,
            'sections'       => $sections,
            'grandTotal'     => $grandTotal,
        ]);
    }
}
