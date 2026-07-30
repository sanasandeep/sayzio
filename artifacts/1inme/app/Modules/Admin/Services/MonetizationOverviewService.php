<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\CoinPurchaseAllocation;
use App\Modules\Admin\Models\Plan;
use App\Services\AI\AiEngineSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only aggregation behind the admin "Monetization Overview" report.
 *
 * Three sections, all money grouped per currency (never mixed):
 *  1. packages()  — per active coin package: price per currency, total coins,
 *     effective price per coin, and what those coins buy in AI terms at the
 *     LIVE AI-engine rates (chat tokens, artistic QRs, TTS chars, STT
 *     minutes, brand assets).
 *  2. aiSpend()   — AI coin burn by feature, this month vs last month, plus
 *     coin top-ups (purchases) over the same two months so admins see burn
 *     vs top-up side by side.
 *  3. plans()     — per plan: active subscribers, paid subscription revenue,
 *     coin-purchase revenue by plan holders (with the API-budget/margin
 *     split from coin_purchase_allocations), estimated AI/coin cost consumed
 *     and the resulting margin.
 *
 * Every query tolerates a missing table (fresh schema) by returning empty
 * data instead of throwing. Nothing here mutates state.
 */
class MonetizationOverviewService
{
    /** Supported period-filter keys => human labels. */
    public const PERIODS = [
        'month' => 'This month',
        '3m'    => 'Last 3 months',
        'all'   => 'All time',
    ];

    /** @var array<string,bool> per-request table existence cache */
    private array $tables = [];

    public function periodSince(string $period): ?Carbon
    {
        return match ($period) {
            'month' => now()->startOfMonth(),
            '3m'    => now()->subMonths(3),
            default => null, // all time
        };
    }

    /**
     * Full report payload for the page.
     *
     * @return array{packages:array,aiRates:array,aiSpend:array,trend:array,plans:array}
     */
    public function report(?Carbon $since): array
    {
        return [
            'packages' => $this->packages(),
            'aiRates'  => $this->aiRates(),
            'aiSpend'  => $this->aiSpend(),
            'trend'    => $this->monthlyTrend(),
            'plans'    => $this->plans($since),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Month-by-month trend (last N calendar months incl. current)
    // ─────────────────────────────────────────────────────────────

    /**
     * Per-calendar-month breakdown over the last $months months (oldest
     * first, current month included) so admins can spot growth or margin
     * erosion at a glance:
     *
     *   month              — 'YYYY-MM'
     *   label              — e.g. 'Jan 2026'
     *   ai_coins_spent     — AI coin burn (wallet spend where meta.ai)
     *   coins_purchased    — coins added via wallet purchases
     *   topup_revenue      — [currency => amount_minor] from coin_purchase_allocations
     *   subscription_revenue — [currency => amount_minor] from paid invoices
     *                          tied to a subscription (by paid_at)
     *
     * Currencies are never mixed. Every query tolerates a missing table.
     *
     * @return array{months:array<int,array<string,mixed>>,currencies:array<int,string>}
     */
    public function monthlyTrend(int $months = 12): array
    {
        $months = max(1, min(24, $months));
        $start = now()->startOfMonth()->subMonthsNoOverflow($months - 1);

        // Seed every month so gaps render as zeros, oldest first.
        $rows = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonthsNoOverflow($i);
            $rows[$m->format('Y-m')] = [
                'month'                => $m->format('Y-m'),
                'label'                => $m->format('M Y'),
                'ai_coins_spent'       => 0,
                'coins_purchased'      => 0,
                'topup_revenue'        => [],
                'subscription_revenue' => [],
            ];
        }
        $currencies = [];

        if ($this->has('wallet_transactions')) {
            $wallet = DB::table('wallet_transactions')
                ->where('created_at', '>=', $start)
                ->where(function ($q) {
                    $q->where(function ($q) {
                        $q->where('type', 'spend')->whereRaw("(meta->>'ai') = 'true'");
                    })->orWhere('type', 'purchase');
                })
                ->select(
                    DB::raw("to_char(created_at, 'YYYY-MM') AS ym"),
                    DB::raw("SUM(CASE WHEN type = 'spend' THEN -delta_coins ELSE 0 END) AS ai_spent"),
                    DB::raw("SUM(CASE WHEN type = 'purchase' THEN delta_coins ELSE 0 END) AS purchased"),
                )
                ->groupBy(DB::raw('1'))
                ->get();
            foreach ($wallet as $r) {
                if (isset($rows[$r->ym])) {
                    $rows[$r->ym]['ai_coins_spent']  = (int) $r->ai_spent;
                    $rows[$r->ym]['coins_purchased'] = (int) $r->purchased;
                }
            }
        }

        if ($this->has('coin_purchase_allocations')) {
            $money = DB::table('coin_purchase_allocations')
                ->where('created_at', '>=', $start)
                ->select(
                    DB::raw("to_char(created_at, 'YYYY-MM') AS ym"),
                    'currency',
                    DB::raw('SUM(amount_minor) AS amount_minor'),
                )
                ->groupBy(DB::raw('1'), 'currency')
                ->get();
            foreach ($money as $r) {
                if (isset($rows[$r->ym])) {
                    $rows[$r->ym]['topup_revenue'][$r->currency] = (int) $r->amount_minor;
                    $currencies[$r->currency] = true;
                }
            }
        }

        if ($this->has('invoices') && $this->has('subscriptions')) {
            $subs = DB::table('invoices')
                ->whereNotNull('subscription_id')
                ->where('status', 'paid')
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', $start)
                ->select(
                    DB::raw("to_char(paid_at, 'YYYY-MM') AS ym"),
                    'currency',
                    DB::raw('SUM(grand_total_minor) AS amount_minor'),
                )
                ->groupBy(DB::raw('1'), 'currency')
                ->get();
            foreach ($subs as $r) {
                if (isset($rows[$r->ym])) {
                    $rows[$r->ym]['subscription_revenue'][$r->currency] = (int) $r->amount_minor;
                    $currencies[$r->currency] = true;
                }
            }
        }

        foreach ($rows as &$row) {
            ksort($row['topup_revenue']);
            ksort($row['subscription_revenue']);
        }
        unset($row);
        $currencies = array_keys($currencies);
        sort($currencies);

        return [
            'months'     => array_values($rows),
            'currencies' => $currencies,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Section 1 — coin packages vs AI credits
    // ─────────────────────────────────────────────────────────────

    /**
     * Live AI-engine coin rates the purchasing-power figures are computed
     * from, so the page can show its assumptions.
     *
     * @return array{chat_model:?string,chat_in_per_1k:float,chat_out_per_1k:float,chat_blended_per_1k:float,qr_coins:int,tts_per_1k_chars:float,stt_per_minute:float,brand_asset_coins:int}
     */
    public function aiRates(): array
    {
        $modelName = null;
        $in = $out = 0.0;
        try {
            $modelName = AiEngineSettings::featureModel('mind');
            foreach (AiEngineSettings::models() as $m) {
                if ($m['name'] === $modelName) {
                    $in  = (float) $m['in_coins_per_1k'];
                    $out = (float) $m['out_coins_per_1k'];
                    break;
                }
            }
        } catch (\Throwable $e) {
            // Missing app_settings table on a fresh schema — fall back to
            // the shipped default rate card.
            foreach (AiEngineSettings::defaultModels() as $m) {
                if ($m['kind'] === 'chat') {
                    $modelName = $m['name'];
                    $in  = (float) $m['in_coins_per_1k'];
                    $out = (float) $m['out_coins_per_1k'];
                    break;
                }
            }
        }

        // Blended chat rate: assume a typical 1:3 input:output token mix.
        $blended = ($in + 3 * $out) / 4;

        return [
            'chat_model'          => $modelName,
            'chat_in_per_1k'      => $in,
            'chat_out_per_1k'     => $out,
            'chat_blended_per_1k' => $blended,
            'qr_coins'            => $this->settingInt(fn () => AiEngineSettings::qrArtCoinsPerGeneration(), AiEngineSettings::DEFAULT_QR_ART_COINS),
            'tts_per_1k_chars'    => $this->settingFloat(fn () => AiEngineSettings::voiceTtsCoinsPer1kChars(), 5.0),
            'stt_per_minute'      => $this->settingFloat(fn () => AiEngineSettings::voiceSttCoinsPerMinute(), 3.0),
            'brand_asset_coins'   => $this->settingInt(fn () => AiEngineSettings::brandAssetCoinsPerGeneration(), AiEngineSettings::DEFAULT_BRAND_ASSET_COINS),
        ];
    }

    /**
     * Per active package: prices per currency (minor units), coin totals,
     * effective price per coin (minor units, 4 dp), API-budget split, and
     * AI purchasing power for the package's total coins.
     *
     * @return array<int,array<string,mixed>>
     */
    public function packages(): array
    {
        if (!$this->has('coin_packages')) {
            return [];
        }
        $rates = $this->aiRates();

        return CoinPackage::query()->active()->ordered()->with('prices')->get()
            ->map(function (CoinPackage $pkg) use ($rates) {
                $coins = $pkg->totalCoins();
                $prices = [];
                foreach ($pkg->prices as $p) {
                    if ($p->billing_cycle !== 'monthly' || !$p->is_active) {
                        continue;
                    }
                    $amount = (int) $p->amount_minor_units;
                    $prices[$p->currency] = [
                        'amount_minor'   => $amount,
                        'per_coin_minor' => $coins > 0 ? round($amount / $coins, 4) : null,
                    ];
                }
                ksort($prices);

                return [
                    'id'             => $pkg->id,
                    'name'           => $pkg->name,
                    'coin_amount'    => (int) $pkg->coin_amount,
                    'bonus_coins'    => (int) $pkg->bonus_coins,
                    'total_coins'    => $coins,
                    'api_budget_pct' => $pkg->apiBudgetPct(),
                    'margin_pct'     => $pkg->marginPct(),
                    'prices'         => $prices,
                    'buys'           => $this->purchasingPower($coins, $rates),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * What $coins buys at the live AI rates.
     *
     * @return array{chat_tokens:?int,qr_generations:?int,tts_chars:?int,stt_minutes:?float,brand_assets:?int}
     */
    public function purchasingPower(int $coins, ?array $rates = null): array
    {
        $r = $rates ?? $this->aiRates();
        return [
            'chat_tokens'    => $r['chat_blended_per_1k'] > 0 ? (int) floor($coins / $r['chat_blended_per_1k'] * 1000) : null,
            'qr_generations' => $r['qr_coins'] > 0 ? intdiv($coins, $r['qr_coins']) : null,
            'tts_chars'      => $r['tts_per_1k_chars'] > 0 ? (int) floor($coins / $r['tts_per_1k_chars'] * 1000) : null,
            'stt_minutes'    => $r['stt_per_minute'] > 0 ? round($coins / $r['stt_per_minute'], 1) : null,
            'brand_assets'   => $r['brand_asset_coins'] > 0 ? intdiv($coins, $r['brand_asset_coins']) : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Section 2 — AI coin spend vs top-up, this vs last month
    // ─────────────────────────────────────────────────────────────

    /**
     * @return array{features:array<int,object>,totals:array<string,array{this:int,last:int}>,purchase_money:array<string,array{this:int,last:int}>}
     */
    public function aiSpend(): array
    {
        $thisStart = now()->startOfMonth();
        $lastStart = now()->subMonthNoOverflow()->startOfMonth();

        $features = [];
        $totals = [
            'ai_spent'        => ['this' => 0, 'last' => 0],
            'coins_purchased' => ['this' => 0, 'last' => 0],
        ];
        $purchaseMoney = [];

        if ($this->has('wallet_transactions')) {
            // AI spend by feature, both months in one pass.
            $rows = DB::table('wallet_transactions')
                ->whereRaw("(meta->>'ai') = 'true'")
                ->where('type', 'spend')
                ->where('created_at', '>=', $lastStart)
                ->select(
                    DB::raw("COALESCE(meta->>'feature', '(none)') AS feature"),
                    DB::raw("SUM(CASE WHEN created_at >= '{$thisStart->toDateTimeString()}' THEN -delta_coins ELSE 0 END) AS this_month"),
                    DB::raw("SUM(CASE WHEN created_at <  '{$thisStart->toDateTimeString()}' THEN -delta_coins ELSE 0 END) AS last_month"),
                    DB::raw("SUM(CASE WHEN created_at >= '{$thisStart->toDateTimeString()}' THEN 1 ELSE 0 END) AS calls_this"),
                )
                ->groupBy(DB::raw("meta->>'feature'"))
                ->orderByDesc(DB::raw('2'))
                ->get();
            $features = $rows->all();
            $totals['ai_spent']['this'] = (int) $rows->sum('this_month');
            $totals['ai_spent']['last'] = (int) $rows->sum('last_month');

            // Coin top-ups (purchases), same two months.
            $buy = DB::table('wallet_transactions')
                ->where('type', 'purchase')
                ->where('created_at', '>=', $lastStart)
                ->selectRaw("SUM(CASE WHEN created_at >= ? THEN delta_coins ELSE 0 END) AS this_month, SUM(CASE WHEN created_at < ? THEN delta_coins ELSE 0 END) AS last_month", [$thisStart, $thisStart])
                ->first();
            $totals['coins_purchased']['this'] = (int) ($buy->this_month ?? 0);
            $totals['coins_purchased']['last'] = (int) ($buy->last_month ?? 0);
        }

        if ($this->has('coin_purchase_allocations')) {
            $money = DB::table('coin_purchase_allocations')
                ->where('created_at', '>=', $lastStart)
                ->select(
                    'currency',
                    DB::raw("SUM(CASE WHEN created_at >= '{$thisStart->toDateTimeString()}' THEN amount_minor ELSE 0 END) AS this_month"),
                    DB::raw("SUM(CASE WHEN created_at <  '{$thisStart->toDateTimeString()}' THEN amount_minor ELSE 0 END) AS last_month"),
                )
                ->groupBy('currency')->orderBy('currency')->get();
            foreach ($money as $m) {
                $purchaseMoney[$m->currency] = [
                    'this' => (int) $m->this_month,
                    'last' => (int) $m->last_month,
                ];
            }
        }

        return [
            'features'       => $features,
            'totals'         => $totals,
            'purchase_money' => $purchaseMoney,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Section 3 — plan-wise profit
    // ─────────────────────────────────────────────────────────────

    /**
     * Per plan (keyed by plan id):
     *   plan, users, active_subs, ai_coins_spent, and per-currency rows of
     *   { revenue_minor, coin_amount_minor, coin_api_budget_minor,
     *     coin_margin_minor, coins_purchased, est_ai_cost_minor, margin_minor }.
     *
     * Estimated AI cost: the plan's spent coins are split across currencies
     * by that plan's purchased-coin share, then priced at the currency's
     * observed API-budget-per-coin rate from coin_purchase_allocations
     * (capped at the full API budget so a plan can never "cost" more than
     * what was budgeted from its own purchases).
     *
     * @return array<int,array<string,mixed>>
     */
    public function plans(?Carbon $since): array
    {
        if (!$this->has('plans')) {
            return [];
        }
        $plans = Plan::query()->ordered()->orderBy('id')->get();

        $users = $this->has('users')
            ? DB::table('users')->select('plan_id', DB::raw('COUNT(*) AS n'))
                ->whereNotNull('plan_id')->groupBy('plan_id')->pluck('n', 'plan_id')
            : collect();

        $activeSubs = $this->has('subscriptions')
            ? DB::table('subscriptions')->where('status', 'active')
                ->select('plan_id', DB::raw('COUNT(*) AS n'))
                ->groupBy('plan_id')->pluck('n', 'plan_id')
            : collect();

        // Paid subscription invoices per plan + currency.
        $revenue = [];
        if ($this->has('invoices') && $this->has('subscriptions')) {
            $rows = DB::table('invoices')
                ->join('subscriptions', 'subscriptions.id', '=', 'invoices.subscription_id')
                ->where('invoices.status', 'paid')
                ->when($since, fn ($q) => $q->where('invoices.paid_at', '>=', $since))
                ->select(
                    'subscriptions.plan_id',
                    'invoices.currency',
                    DB::raw('SUM(invoices.grand_total_minor) AS amount_minor'),
                    DB::raw('COUNT(*) AS invoices'),
                )
                ->groupBy('subscriptions.plan_id', 'invoices.currency')
                ->get();
            foreach ($rows as $r) {
                $revenue[$r->plan_id][$r->currency] = [
                    'amount_minor' => (int) $r->amount_minor,
                    'invoices'     => (int) $r->invoices,
                ];
            }
        }

        // Coin purchases (allocation snapshots) attributed to the buyer's
        // CURRENT plan, per currency.
        $coinBuys = [];
        if ($this->has('coin_purchase_allocations') && $this->has('users')) {
            $rows = DB::table('coin_purchase_allocations AS cpa')
                ->join('users', 'users.id', '=', 'cpa.user_id')
                ->whereNotNull('users.plan_id')
                ->when($since, fn ($q) => $q->where('cpa.created_at', '>=', $since))
                ->select(
                    'users.plan_id',
                    'cpa.currency',
                    DB::raw('SUM(cpa.amount_minor) AS amount_minor'),
                    DB::raw('SUM(cpa.api_budget_minor) AS api_budget_minor'),
                    DB::raw('SUM(cpa.margin_minor) AS margin_minor'),
                    DB::raw('SUM(cpa.coins) AS coins'),
                    DB::raw('COUNT(*) AS purchases'),
                )
                ->groupBy('users.plan_id', 'cpa.currency')
                ->get();
            foreach ($rows as $r) {
                $coinBuys[$r->plan_id][$r->currency] = [
                    'amount_minor'     => (int) $r->amount_minor,
                    'api_budget_minor' => (int) $r->api_budget_minor,
                    'margin_minor'     => (int) $r->margin_minor,
                    'coins'            => (int) $r->coins,
                    'purchases'        => (int) $r->purchases,
                ];
            }
        }

        // AI coins spent per plan (coins, currency-agnostic).
        $aiSpent = collect();
        if ($this->has('wallet_transactions') && $this->has('users')) {
            $aiSpent = DB::table('wallet_transactions AS wt')
                ->join('users', 'users.id', '=', 'wt.user_id')
                ->whereRaw("(wt.meta->>'ai') = 'true'")
                ->where('wt.type', 'spend')
                ->whereNotNull('users.plan_id')
                ->when($since, fn ($q) => $q->where('wt.created_at', '>=', $since))
                ->select('users.plan_id', DB::raw('SUM(-wt.delta_coins) AS coins'))
                ->groupBy('users.plan_id')
                ->pluck('coins', 'plan_id');
        }

        $out = [];
        foreach ($plans as $plan) {
            $pid = $plan->id;
            $buys = $coinBuys[$pid] ?? [];
            $rev  = $revenue[$pid] ?? [];
            $spentCoins = (int) ($aiSpent[$pid] ?? 0);
            $totalCoinsBought = array_sum(array_column($buys, 'coins'));

            $currencies = array_unique(array_merge(array_keys($rev), array_keys($buys)));
            sort($currencies);

            $rows = [];
            foreach ($currencies as $cur) {
                $revMinor    = (int) ($rev[$cur]['amount_minor'] ?? 0);
                $buyRow      = $buys[$cur] ?? null;
                $buyMinor    = (int) ($buyRow['amount_minor'] ?? 0);
                $apiBudget   = (int) ($buyRow['api_budget_minor'] ?? 0);
                $coinsBought = (int) ($buyRow['coins'] ?? 0);

                // Spent coins attributed to this currency by purchase share,
                // priced at the observed API-budget-per-coin, capped at the
                // full budget.
                $estCost = 0;
                if ($coinsBought > 0 && $totalCoinsBought > 0 && $spentCoins > 0) {
                    $share = $coinsBought / $totalCoinsBought;
                    $estCost = (int) min($apiBudget, round($spentCoins * $share * ($apiBudget / $coinsBought)));
                }

                $rows[$cur] = [
                    'revenue_minor'         => $revMinor,
                    'invoices'              => (int) ($rev[$cur]['invoices'] ?? 0),
                    'coin_amount_minor'     => $buyMinor,
                    'coin_api_budget_minor' => $apiBudget,
                    'coin_margin_minor'     => (int) ($buyRow['margin_minor'] ?? 0),
                    'coins_purchased'       => $coinsBought,
                    'est_ai_cost_minor'     => $estCost,
                    'margin_minor'          => $revMinor + $buyMinor - $estCost,
                ];
            }

            $out[] = [
                'plan'           => $plan,
                'users'          => (int) ($users[$pid] ?? 0),
                'active_subs'    => (int) ($activeSubs[$pid] ?? 0),
                'ai_coins_spent' => $spentCoins,
                'currencies'     => $rows,
            ];
        }

        return $out;
    }

    private function has(string $table): bool
    {
        if (!array_key_exists($table, $this->tables)) {
            try {
                $this->tables[$table] = Schema::hasTable($table);
            } catch (\Throwable $e) {
                $this->tables[$table] = false;
            }
        }
        return $this->tables[$table];
    }

    private function settingInt(\Closure $fn, int $fallback): int
    {
        try { return $fn(); } catch (\Throwable $e) { return $fallback; }
    }

    private function settingFloat(\Closure $fn, float $fallback): float
    {
        try { return $fn(); } catch (\Throwable $e) { return $fallback; }
    }
}
