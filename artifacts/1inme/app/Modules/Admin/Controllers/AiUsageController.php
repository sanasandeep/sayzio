<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiCreditBalance;
use App\Modules\User\Models\AiCreditTransaction;
use App\Modules\User\Models\CardScan;
use App\Modules\User\Models\User;
use App\Services\AI\AiCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per-user AI usage report for admins.
 *
 *   GET  /admin/ai-usage                 cross-user roll-up + filters.
 *   GET  /admin/ai-usage/{user}          single-user transaction history.
 *   POST /admin/ai-usage/{user}/adjust   manual credit grant / debit with reason.
 *
 * The roll-up groups by user so support can see who's burning credits
 * fastest and who's owed a refund. Adjustments go through
 * AiCreditService::adminAdjust so the audit trail / row lock / balance
 * arithmetic match every other movement.
 */
class AiUsageController extends Controller
{
    public function __construct(protected AiCreditService $credits) {}

    public function index(Request $request)
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $since = now()->subDays($days);
        $feature = $request->query('feature');

        // Per-user aggregation. Grouping keeps the result list small
        // even on large ledgers because we cap to top 100 spenders.
        // Feature tags are stored as either a bare product name ("mind")
        // or a "product.sub" form ("persona.profile", "companion.chat",
        // "coach.suggest"). The dropdown lists product-level options, so
        // filter with a prefix match to roll every sub-feature up to its
        // product on the admin index.
        $rows = AiCreditTransaction::query()
            ->where('created_at', '>=', $since)
            ->when($feature, fn($q) => $q->where(function ($q) use ($feature) {
                $q->where('feature', $feature)
                  ->orWhere('feature', 'like', $feature . '.%');
            }))
            ->select(
                'user_id',
                DB::raw("SUM(CASE WHEN type='spend' THEN -delta_credits ELSE 0 END) AS spent"),
                DB::raw("SUM(CASE WHEN type='purchase' THEN delta_credits ELSE 0 END) AS purchased"),
                DB::raw("SUM(CASE WHEN type='refund' THEN delta_credits ELSE 0 END) AS refunded"),
                DB::raw("SUM(CASE WHEN type='admin_adjustment' THEN delta_credits ELSE 0 END) AS adjusted"),
                DB::raw("SUM(tokens_in) AS tokens_in"),
                DB::raw("SUM(tokens_out) AS tokens_out"),
                // Only AI spend rows are real OpenAI calls; purchases /
                // refunds / adjustments are bookkeeping, not API hits.
                DB::raw("SUM(CASE WHEN type='spend' THEN 1 ELSE 0 END) AS calls"),
            )
            ->groupBy('user_id')
            ->orderByDesc('spent')
            ->limit(100)
            ->get();

        $userIds = $rows->pluck('user_id')->all();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Pull each top spender's current ledger snapshot so admins can
        // see live balance + lifetime totals next to window-scoped spend
        // without clicking into per-user detail.
        $balances = AiCreditBalance::whereIn('user_id', $userIds)
            ->get(['user_id', 'balance', 'lifetime_purchased', 'lifetime_spent'])
            ->keyBy('user_id');

        $totals = [
            'spent'     => (int) $rows->sum('spent'),
            'purchased' => (int) $rows->sum('purchased'),
            'refunded'  => (int) $rows->sum('refunded'),
            'adjusted'  => (int) $rows->sum('adjusted'),
            'tokens_in' => (int) $rows->sum('tokens_in'),
            'tokens_out'=> (int) $rows->sum('tokens_out'),
            'calls'     => (int) $rows->sum('calls'),
        ];

        // Per-feature breakdown across the same time window so admins can
        // see which product (mind / persona / coach / companion) is
        // burning the most credits and how much of it OpenAI billed.
        $featureRows = AiCreditTransaction::query()
            ->where('created_at', '>=', $since)
            ->where('type', 'spend')
            ->select(
                DB::raw("COALESCE(feature, '(none)') AS feature"),
                DB::raw("SUM(-delta_credits) AS spent"),
                DB::raw("SUM(tokens_in) AS tokens_in"),
                DB::raw("SUM(tokens_out) AS tokens_out"),
                DB::raw("COUNT(*) AS calls"),
                DB::raw("COUNT(DISTINCT user_id) AS users"),
            )
            ->groupBy('feature')
            ->orderByDesc('spent')
            ->get();

        // Per-product side-stats so admins can see how individual AI
        // products (e.g. card scanner) are being used independently of
        // the credit ledger roll-ups. Easy to extend per feature.
        $cardScanStats = [
            'total'     => CardScan::withoutGlobalScope('workspace')
                                   ->where('created_at', '>=', $since)->count(),
            'completed' => CardScan::withoutGlobalScope('workspace')
                                   ->where('created_at', '>=', $since)
                                   ->where('status', 'completed')->count(),
            'failed'    => CardScan::withoutGlobalScope('workspace')
                                   ->where('created_at', '>=', $since)
                                   ->where('status', 'failed')->count(),
            'users'     => CardScan::withoutGlobalScope('workspace')
                                   ->where('created_at', '>=', $since)
                                   ->distinct('user_id')->count('user_id'),
        ];

        return view('admin.ai-usage.index', [
            'rows'          => $rows,
            'users'         => $users,
            'balances'      => $balances,
            'totals'        => $totals,
            'days'          => $days,
            'feature'       => $feature,
            'features'      => AiCreditTransaction::FEATURES,
            'featureRows'   => $featureRows,
            'cardScanStats' => $cardScanStats,
        ]);
    }

    public function show(User $user)
    {
        $balance = $this->credits->balanceFor($user);
        $transactions = $balance->transactions()->limit(100)->get();
        return view('admin.ai-usage.show', compact('user', 'balance', 'transactions'));
    }

    public function adjust(Request $request, User $user)
    {
        $data = $request->validate([
            'delta'  => 'required|integer|not_in:0',
            'reason' => 'required|string|max:500',
        ]);
        try {
            $this->credits->adminAdjust(
                $user,
                (int) $data['delta'],
                $data['reason'],
                optional(auth()->guard('admin')->user())->id,
            );
        } catch (\App\Services\AI\InsufficientAiCreditsException $e) {
            return back()->with('error',
                "Cannot debit {$e->required} credits — balance is only {$e->balance}.");
        }
        return back()->with('success', 'Credit adjustment recorded.');
    }
}
