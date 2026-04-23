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
            'premium_features',
            'plan_free',
            'plan_paid',
        ],
    ];

    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:64'],
            'target' => ['required', 'string', 'max:64'],
        ]);

        $source = $data['source'];
        $target = $data['target'];

        if (!isset(self::ALLOWED[$source]) || !in_array($target, self::ALLOWED[$source], true)) {
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
