<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiCreditTransaction;
use App\Modules\User\Models\CardScan;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Wallet;
use App\Modules\User\Models\WalletTransaction;
use App\Services\Billing\InsufficientCoinsException;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per-user AI usage report for admins.
 *
 *   GET  /admin/ai-usage                 cross-user roll-up + filters.
 *   GET  /admin/ai-usage/{user}          single-user transaction history.
 *   POST /admin/ai-usage/{user}/adjust   manual coin grant / debit with reason.
 *
 * AI usage is now paid for in COINS straight from the coin wallet, so
 * this report reads the AI-tagged slice of `wallet_transactions`
 * (meta.ai = true). Attribution (feature / model / token counts) lives
 * in the transaction `meta` payload. The roll-up groups by user so
 * support can see who's burning coins fastest. Adjustments go through
 * WalletService::adjust (row-locked, audited) and are AI-tagged so they
 * stay visible in this report.
 *
 * The feature catalog/labels are still sourced from
 * {@see AiCreditTransaction} (kept as a read-only constant map).
 */
class AiUsageController extends Controller
{
    public function __construct(protected WalletService $wallets) {}

    public function index(Request $request)
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $since = now()->subDays($days);
        $feature = $request->query('feature');

        // AI-only slice of the coin ledger.
        $base = fn () => DB::table('wallet_transactions')
            ->whereRaw("(meta->>'ai') = 'true'")
            ->where('created_at', '>=', $since);

        // Per-user aggregation. Feature tags are stored as either a bare
        // product name ("mind") or a "product.sub" form ("persona.profile",
        // "companion.chat", "coach.suggest"). The dropdown lists
        // product-level options, so filter with a prefix match to roll
        // every sub-feature up to its product on the admin index.
        $rows = $base()
            ->when($feature, fn ($q) => $q->whereRaw(
                "((meta->>'feature') = ? OR (meta->>'feature') LIKE ?)",
                [$feature, $feature . '.%']
            ))
            ->select(
                'user_id',
                DB::raw("SUM(CASE WHEN type='spend' THEN -delta_coins ELSE 0 END) AS spent"),
                DB::raw("SUM(CASE WHEN type='refund' THEN delta_coins ELSE 0 END) AS refunded"),
                DB::raw("SUM(CASE WHEN type='adjustment' THEN delta_coins ELSE 0 END) AS adjusted"),
                DB::raw("COALESCE(SUM((meta->>'tokens_in')::int), 0) AS tokens_in"),
                DB::raw("COALESCE(SUM((meta->>'tokens_out')::int), 0) AS tokens_out"),
                // Only AI spend rows are real OpenAI calls; refunds /
                // adjustments are bookkeeping, not API hits.
                DB::raw("SUM(CASE WHEN type='spend' THEN 1 ELSE 0 END) AS calls"),
            )
            ->groupBy('user_id')
            ->orderByDesc('spent')
            ->limit(100)
            ->get();

        $userIds = $rows->pluck('user_id')->all();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Live coin balance per top spender.
        $balances = Wallet::whereIn('user_id', $userIds)
            ->get(['user_id', 'balance'])
            ->keyBy('user_id');

        $totals = [
            'spent'     => (int) $rows->sum('spent'),
            'refunded'  => (int) $rows->sum('refunded'),
            'adjusted'  => (int) $rows->sum('adjusted'),
            'tokens_in' => (int) $rows->sum('tokens_in'),
            'tokens_out'=> (int) $rows->sum('tokens_out'),
            'calls'     => (int) $rows->sum('calls'),
        ];

        // Per-feature breakdown across the same time window so admins can
        // see which product (mind / persona / coach / companion) is
        // burning the most coins and how much of it OpenAI billed.
        $featureRows = $base()
            ->where('type', 'spend')
            ->select(
                DB::raw("COALESCE(meta->>'feature', '(none)') AS feature"),
                DB::raw("SUM(-delta_coins) AS spent"),
                DB::raw("COALESCE(SUM((meta->>'tokens_in')::int), 0) AS tokens_in"),
                DB::raw("COALESCE(SUM((meta->>'tokens_out')::int), 0) AS tokens_out"),
                DB::raw("COUNT(*) AS calls"),
                DB::raw("COUNT(DISTINCT user_id) AS users"),
            )
            ->groupBy(DB::raw("meta->>'feature'"))
            ->orderByDesc('spent')
            ->get();

        // Per-product side-stats so admins can see how individual AI
        // products (e.g. card scanner) are being used independently of
        // the coin ledger roll-ups. Easy to extend per feature.
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
        $balance = $this->wallets->getBalance($user);
        $transactions = WalletTransaction::where('user_id', $user->id)
            ->whereRaw("(meta->>'ai') = 'true'")
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        return view('admin.ai-usage.show', compact('user', 'balance', 'transactions'));
    }

    public function adjust(Request $request, User $user)
    {
        $data = $request->validate([
            'delta'  => 'required|integer|not_in:0',
            'reason' => 'required|string|max:500',
        ]);
        try {
            $this->wallets->adjust(
                $user,
                (int) $data['delta'],
                $data['reason'],
                optional(auth()->guard('admin')->user())->id,
                ['meta' => ['ai' => true, 'feature' => 'admin_adjustment', 'source' => 'admin_ai_usage']],
            );
        } catch (InsufficientCoinsException $e) {
            return back()->with('error',
                "Cannot debit {$e->required} coins — balance is only {$e->balance}.");
        }
        return back()->with('success', 'Coin adjustment recorded.');
    }
}
