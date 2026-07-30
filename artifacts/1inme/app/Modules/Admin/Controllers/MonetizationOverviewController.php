<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\MonetizationOverviewService;
use Illuminate\Http\Request;

/**
 * Admin-only, read-only "Monetization Overview" report:
 * coin packages vs live AI-credit costs, AI coin burn vs top-up, and
 * plan-wise profitability. No mutations — this is a reporting surface.
 */
class MonetizationOverviewController extends Controller
{
    public function index(Request $request, MonetizationOverviewService $svc)
    {
        $period = (string) $request->query('period', 'month');
        if (!array_key_exists($period, MonetizationOverviewService::PERIODS)) {
            $period = 'month';
        }
        $since = $svc->periodSince($period);
        $report = $svc->report($since);

        return view('admin.monetization.index', [
            'period'   => $period,
            'periods'  => MonetizationOverviewService::PERIODS,
            'since'    => $since,
            'packages' => $report['packages'],
            'aiRates'  => $report['aiRates'],
            'aiSpend'  => $report['aiSpend'],
            'plans'    => $report['plans'],
        ]);
    }
}
