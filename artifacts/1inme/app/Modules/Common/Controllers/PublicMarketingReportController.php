<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\MarketingStrategy;
use App\Services\AI\MarketingStrategistService;
use Illuminate\Http\Request;

/**
 * Task #3281 — public, read-only view of a shared AI Marketing Strategist
 * report. Resolved by an unguessable `share_token`; no auth, no coins, no
 * mutation. The page is the same self-contained branded HTML used for the
 * Rich PDF (inline CSS, no Vite manifest) so it stays resilient and cannot
 * leak the owner's private controls.
 */
class PublicMarketingReportController extends Controller
{
    public function __construct(protected MarketingStrategistService $strategist) {}

    public function show(Request $request, string $token)
    {
        $strategy = MarketingStrategy::query()
            ->where('share_token', $token)
            ->first();

        if (!$strategy || !$strategy->isShared()) {
            abort(404);
        }

        $html = $this->strategist->toHtml($strategy);

        return response($html, 200, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'X-Robots-Tag'  => 'noindex, nofollow',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
