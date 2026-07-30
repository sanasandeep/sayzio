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
            'trend'    => $report['trend'],
            'plans'    => $report['plans'],
            'csvExports' => $this->csvExports($report, $period),
        ]);
    }

    /**
     * Client-side CSV export payloads (one per report section), built from the
     * same read-only report. Currencies are never mixed: money rows carry an
     * explicit currency column and amounts are plain major-unit decimals.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, array{filename: string, header: list<string>, rows: list<list<string|int|float|null>>}>
     */
    private function csvExports(array $report, string $period): array
    {
        $date  = now()->toDateString();
        $money = fn (?int $minor): ?string => $minor === null ? null : number_format($minor / 100, 2, '.', '');

        $packageRows = [];
        foreach ($report['packages'] as $p) {
            $base = [
                $p['name'],
                $p['coin_amount'],
                $p['bonus_coins'],
                $p['total_coins'],
            ];
            $tail = [
                $p['api_budget_pct'],
                $p['margin_pct'],
                $p['buys']['chat_tokens'],
                $p['buys']['qr_generations'],
                $p['buys']['tts_chars'],
                $p['buys']['stt_minutes'],
            ];
            if (empty($p['prices'])) {
                $packageRows[] = [...$base, null, null, null, ...$tail];
                continue;
            }
            foreach ($p['prices'] as $cur => $pr) {
                $packageRows[] = [
                    ...$base,
                    $cur,
                    $money($pr['amount_minor']),
                    $pr['per_coin_minor'] !== null ? $money((int) round($pr['per_coin_minor'])) : null,
                    ...$tail,
                ];
            }
        }

        $aiSpend = $report['aiSpend'];
        $aiRows  = [
            ['ai_coins_spent', null, null, $aiSpend['totals']['ai_spent']['this'], $aiSpend['totals']['ai_spent']['last'], (int) $aiSpend['totals']['ai_spent']['this'] - (int) $aiSpend['totals']['ai_spent']['last'], null],
            ['coins_purchased', null, null, $aiSpend['totals']['coins_purchased']['this'], $aiSpend['totals']['coins_purchased']['last'], (int) $aiSpend['totals']['coins_purchased']['this'] - (int) $aiSpend['totals']['coins_purchased']['last'], null],
        ];
        foreach ($aiSpend['purchase_money'] as $cur => $m) {
            $aiRows[] = ['topup_revenue', null, $cur, $money($m['this']), $money($m['last']), $money((int) $m['this'] - (int) $m['last']), null];
        }
        foreach ($aiSpend['features'] as $f) {
            $aiRows[] = [
                'feature_coins',
                $f->feature,
                null,
                (int) $f->this_month,
                (int) $f->last_month,
                (int) $f->this_month - (int) $f->last_month,
                (int) $f->calls_this,
            ];
        }

        $planRows = [];
        foreach ($report['plans'] as $row) {
            $base = [
                $row['plan']->name,
                $row['plan']->is_internal ? 'yes' : 'no',
                $row['users'],
                $row['active_subs'],
                $row['ai_coins_spent'],
            ];
            if (empty($row['currencies'])) {
                $planRows[] = [...$base, null, null, null, null, null, null];
                continue;
            }
            foreach ($row['currencies'] as $cur => $c) {
                $planRows[] = [
                    ...$base,
                    $cur,
                    $money($c['revenue_minor']),
                    $money($c['coin_amount_minor']),
                    $money($c['coin_api_budget_minor']),
                    $money($c['est_ai_cost_minor']),
                    $money($c['margin_minor']),
                ];
            }
        }

        return [
            'packages' => [
                'filename' => "coin-packages-{$date}.csv",
                'header'   => ['package', 'coin_amount', 'bonus_coins', 'total_coins', 'currency', 'price', 'price_per_coin', 'api_budget_pct', 'margin_pct', 'est_chat_tokens', 'est_artistic_qrs', 'est_tts_chars', 'est_stt_minutes'],
                'rows'     => $packageRows,
            ],
            'aiSpend' => [
                'filename' => "ai-burn-vs-topup-{$date}.csv",
                'header'   => ['metric', 'feature', 'currency', 'this_month', 'last_month', 'delta', 'calls_this_month'],
                'rows'     => $aiRows,
            ],
            'plans' => [
                'filename' => "plan-profit-{$period}-{$date}.csv",
                'header'   => ['plan', 'internal', 'users', 'active_subs', 'ai_coins_spent', 'currency', 'subscription_revenue', 'coin_revenue', 'api_budget', 'est_ai_cost', 'margin'],
                'rows'     => $planRows,
            ],
        ];
    }
}
