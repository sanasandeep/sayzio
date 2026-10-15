<?php

namespace App\Services;

use App\Modules\User\Models\MarketingPlanCalc;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BudgetForecast
{
    public function version(MarketingPlanCalc $plan, bool $shared = false): ?int
    {
        if (! Schema::hasTable('marketing_forecast_versions')) {
            return null;
        }
        $id = DB::table('marketing_forecast_versions')->insertGetId(['plan_id' => $plan->id, 'name' => $plan->name, 'payload' => json_encode($plan->payload), 'shared' => $shared, 'created_at' => now(), 'updated_at' => now()]);
        $old = DB::table('marketing_forecast_versions')->where('plan_id', $plan->id)->where('shared', false)->latest('id')->skip(20)->take(1000)->pluck('id');
        DB::table('marketing_forecast_versions')->whereIn('id', $old)->delete();
        $oldShared = DB::table('marketing_forecast_versions')->where('plan_id', $plan->id)->where('shared', true)->latest('id')->skip(20)->take(1000)->pluck('id');
        DB::table('marketing_forecast_versions')->whereIn('id', $oldShared)->delete();

        return $id;
    }

    /** Deterministic report projection; values remain explicit assumptions, not promised returns. */
    public function calculate(array $p, float $subscription = 0, string $scenario = 'expected'): array
    {
        $n = fn ($v) => is_numeric($v) && is_finite((float) $v) ? max(0, (float) $v) : 0;
        $period = max(1, min(12, (int) ($p['period_months'] ?? 12)));
        $s = $scenario === 'expected' ? [] : ($p['scenarios'][$scenario] ?? []);
        $factor = fn ($key) => array_key_exists($key, $s) ? $n($s[$key]) / 100 : 1;
        $weights = array_slice(array_pad((array) ($p['weights'] ?? []), 12, 1), 0, $period);
        $sum = max(.000000001, array_sum($weights));
        $uplift = $p['uplifts'] ?? [];
        $vl = (($uplift['apply'] ?? false) ? 1 + $n($uplift['chat'] ?? 0) / 100 : 1) * $factor('vl');
        $lc = (($uplift['apply'] ?? false) ? 1 + $n($uplift['crm'] ?? 0) / 100 : 1) * $factor('lc');
        $lag = max(0, min(11, (int) ($p['sales_delay'] ?? 0)));
        $repeat = 1 + $n($p['repeat_rate'] ?? 0) / 100 * (1 - min(100, $n($p['churn_rate'] ?? 0)) / 100);
        $refund = 1 - min(100, $n($p['refund_rate'] ?? 0)) / 100;
        $months = array_fill(0, 12, ['spend' => 0.0, 'visitors' => 0.0, 'leads' => 0.0, 'customers' => 0.0, 'revenue' => 0.0]);
        foreach ($p['channels'] ?? [] as $c) {
            $budget = $n($p['annual_budget'] ?? 0) * $factor('budget') * $n($c['alloc'] ?? 0) / 100;
            if (! ($c['fixed'] ?? false)) {
                $budget = max($n($c['min_budget'] ?? 0), $budget);
                if ($n($c['max_budget'] ?? 0) > 0) {
                    $budget = min($budget, $n($c['max_budget']));
                }
            }
            for ($m = 0; $m < $period; $m++) {
                $w = $n($weights[$m] ?? 1) / $sum;
                $sp = ($c['fixed'] ?? false) ? $subscription + $n($p['ai_credits'] ?? 0) : $budget * $w;
                $vis = ($c['fixed'] ?? false) ? $n($p['organic_visitors'] ?? 0) * $w * $period : ($n($c['cpv'] ?? 0) > 0 ? $sp / ($n($c['cpv']) * max(.01, $factor('cpv'))) : 0);
                $ld = min($vis, $vis * $n($c['vl'] ?? 0) / 100 * $vl);
                $cu = min($ld, $ld * $n($c['lc'] ?? 0) / 100 * $lc);
                $months[$m]['spend'] += $sp;
                $months[$m]['visitors'] += $vis;
                $months[$m]['leads'] += $ld;
                if ($m + $lag < $period) {
                    $months[$m + $lag]['customers'] += $cu;
                    $months[$m + $lag]['revenue'] += $cu * $n($c['acv'] ?? 0) * $repeat * $refund;
                }
            }
        }
        foreach ($p['costs'] ?? [] as $cost) {
            $amount = $n($cost['amount'] ?? 0);
            $at = max(0, min(11, (int) ($cost['month'] ?? 0)));
            for ($m = 0; $m < $period; $m++) {
                if (($cost['frequency'] ?? 'monthly') === 'monthly' || $m === $at) {
                    $months[$m]['spend'] += $amount;
                }
            }
        }
        $totals = [];
        foreach (array_keys($months[0]) as $k) {
            $totals[$k] = array_sum(array_column($months, $k));
        }
        $totals['gross_profit'] = $totals['revenue'] * min(100, $n($p['gross_margin'] ?? 60)) / 100;
        $totals['contribution'] = $totals['gross_profit'] - $totals['spend'];
        $totals['cac'] = $totals['customers'] > 0 ? $totals['spend'] / $totals['customers'] : null;

        return ['months' => $months, 'totals' => $totals, 'period' => $period];
    }
}
