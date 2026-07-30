<?php

namespace App\Modules\User\Controllers;

use App\Actions\Billing\ActivateSubscription;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\User\Models\SubscriptionAddon;
use App\Modules\User\Models\Subscription;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\InsufficientCoinsException;
use App\Services\Billing\NotImplementedException;
use App\Services\Billing\WalletService;
use App\Services\PricingResolver;
use Illuminate\Http\Request;

/**
 * Customer-facing wallet:
 *   - GET  /user/wallet            balance + recent transactions + Buy CTA
 *   - GET  /user/wallet/transactions  paginated history with filters
 *   - GET  /user/wallet/buy        list active coin packages in user's currency
 *   - POST /user/wallet/buy        gateway handoff for chosen package
 *   - POST /user/addons/{addon}/activate   spend coins to attach a coin-priced addon
 */
class WalletController extends Controller
{
    public function __construct(protected WalletService $wallets) {}

    public function show(Request $request)
    {
        // When the admin has turned the Wallet & Coins feature off we render
        // the page in a "locked by admin" state instead of a bare 404, so the
        // user understands the feature exists but is currently disabled.
        if (!WalletService::isEnabled()) {
            return view('user.wallet.show', ['locked' => true]);
        }
        $user = $request->user();
        $wallet = $this->wallets->walletFor($user);
        $transactions = $wallet->transactions()->limit(10)->get();
        return view('user.wallet.show', [
            'locked'       => false,
            'wallet'       => $wallet,
            'transactions' => $transactions,
            'currency'     => PricingResolver::currencyForUser($user),
            'rate'         => WalletService::rateFor(PricingResolver::currencyForUser($user)),
        ]);
    }

    /**
     * Lightweight JSON balance probe for the sidebar coin badge so it can
     * refresh after a coin-changing action (spend/credit) without a full
     * page reload. Returns the raw integer plus pre-formatted display
     * strings that mirror the Blade badge ("1.2k" abbreviation + a
     * thousands-separated title).
     */
    public function balance(Request $request)
    {
        if (!WalletService::isEnabled()) {
            return response()->json(['enabled' => false]);
        }
        $wallet = $this->wallets->walletFor($request->user());
        $coins = (int) $wallet->balance;
        $threshold = (int) ($wallet->low_balance_threshold ?? 100);
        return response()->json([
            'enabled'   => true,
            'balance'   => $coins,
            'threshold' => $threshold,
            'low'       => $threshold > 0 && $coins < $threshold,
            'display'   => $coins >= 1000 ? round($coins / 1000, 1) . 'k' : (string) $coins,
            'formatted' => number_format($coins),
        ]);
    }

    /**
     * Apply the shared type / from / to filters to a wallet-transactions
     * query. Used by the ledger page, its CSV export, and the summary
     * aggregates so all three always agree on what "the filtered set" is.
     */
    protected function applyLedgerFilters($query, Request $request)
    {
        if ($t = $request->query('type')) {
            if (in_array($t, \App\Modules\User\Models\WalletTransaction::TYPES, true)) {
                $query->where('type', $t);
            }
        }
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }
        return $query;
    }

    public function transactions(Request $request)
    {
        if (!WalletService::isEnabled()) abort(404);
        $user = $request->user();
        $wallet = $this->wallets->walletFor($user);
        $query = $this->applyLedgerFilters($wallet->transactions(), $request);

        // Period summary tiles over the WHOLE filtered range (not just
        // the current page): coins purchased (all positive deltas) vs
        // coins spent (all negative deltas) and the resulting net.
        $summary = (clone $query)->reorder()
            ->selectRaw('COALESCE(SUM(CASE WHEN delta_coins > 0 THEN delta_coins ELSE 0 END),0) AS coins_in,'
                . 'COALESCE(SUM(CASE WHEN delta_coins < 0 THEN -delta_coins ELSE 0 END),0) AS coins_out,'
                . 'COALESCE(SUM(delta_coins),0) AS net, COUNT(*) AS entries')
            ->first();

        $page = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();

        // Day-group the current page for the statement view. Subtotals are
        // computed over the full day (a filtered aggregate query), so a day
        // split across two pages still shows correct daily totals.
        $days = self::groupByDay(collect($page->items()));
        $dayKeys = array_keys($days);
        if ($dayKeys) {
            $dayTotals = $this->applyLedgerFilters($wallet->transactions(), $request)->reorder()
                ->selectRaw("to_char(created_at, 'YYYY-MM-DD') AS day,"
                    . 'COALESCE(SUM(CASE WHEN delta_coins > 0 THEN delta_coins ELSE 0 END),0) AS coins_in,'
                    . 'COALESCE(SUM(CASE WHEN delta_coins < 0 THEN -delta_coins ELSE 0 END),0) AS coins_out,'
                    . 'COALESCE(SUM(delta_coins),0) AS net')
                ->whereRaw("to_char(created_at, 'YYYY-MM-DD') IN ('" . implode("','", $dayKeys) . "')")
                ->groupBy('day')->get()->keyBy('day');
        } else {
            $dayTotals = collect();
        }

        return view('user.wallet.transactions', [
            'wallet'    => $wallet,
            'page'      => $page,
            'days'      => $days,
            'dayTotals' => $dayTotals,
            'summary'   => $summary,
            'filters'   => $request->only(['type', 'from', 'to']),
        ]);
    }

    /** Group a collection of transactions under their YYYY-MM-DD day key. */
    public static function groupByDay($transactions): array
    {
        $out = [];
        foreach ($transactions as $tx) {
            $key = $tx->created_at ? $tx->created_at->format('Y-m-d') : 'unknown';
            $out[$key][] = $tx;
        }
        return $out;
    }

    /** CSV export of the user's filtered ledger (no pagination cap abuse: streamed). */
    public function transactionsExport(Request $request)
    {
        if (!WalletService::isEnabled()) abort(404);
        $wallet = $this->wallets->walletFor($request->user());
        $query = $this->applyLedgerFilters($wallet->transactions(), $request)
            ->orderByDesc('created_at')->orderByDesc('id');

        $filename = 'coin-ledger-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Time', 'Type', 'Description', 'Coins', 'Balance after']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $tx) {
                    fputcsv($out, [
                        optional($tx->created_at)->format('Y-m-d'),
                        optional($tx->created_at)->format('H:i:s'),
                        $tx->type,
                        $tx->reason ?? '',
                        (int) $tx->delta_coins,
                        (int) $tx->balance_after,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function buy(Request $request, GatewayManager $gm)
    {
        if (!WalletService::isEnabled()) abort(404);
        $user = $request->user();
        $currency = PricingResolver::currencyForUser($user);
        $packages = CoinPackage::active()->ordered()->with('prices')->get()
            ->map(function ($p) use ($user, $currency) {
                $priced = PricingResolver::priceForCurrency($p, $currency, 'monthly');
                return ['model' => $p, 'priced' => $priced];
            });
        return view('user.wallet.buy', [
            'packages' => $packages,
            'currency' => $currency,
            'gateways' => $gm->enabledAdapters(),
        ]);
    }

    public function buyHandoff(Request $request, GatewayManager $gm)
    {
        if (!WalletService::isEnabled()) abort(404);
        $data = $request->validate([
            'coin_package_id' => 'required|integer|exists:coin_packages,id',
            'gateway' => 'required|string|in:razorpay,stripe,paypal,cashfree,payumoney,offline',
        ]);
        $user = $request->user();
        $package = CoinPackage::active()->findOrFail($data['coin_package_id']);
        $currency = PricingResolver::currencyForUser($user);
        $priced = PricingResolver::priceForCurrency($package, $currency, 'monthly');
        if ((int) $priced['amount_minor'] <= 0) {
            return back()->with('error', 'This package isn\'t priced in your currency yet.');
        }

        $enabledSlugs = array_map(fn($a) => $a->slug(), $gm->enabledAdapters());
        if (!in_array($data['gateway'], $enabledSlugs, true)) {
            return back()->with('error', 'That payment method is not available right now.');
        }

        $items = [[
            'label'        => $package->name . ' (' . number_format($package->totalCoins()) . ' coins)',
            'amount_minor' => (int) $priced['amount_minor'],
            'quantity'     => 1,
            'meta'         => [
                'kind'            => 'coin_package',
                'coin_package_id' => $package->id,
                'coins'           => (int) $package->coin_amount,
                'bonus'           => (int) $package->bonus_coins,
            ],
        ]];
        $invoice = ActivateSubscription::issuePendingInvoice($user, $items, $currency);

        try {
            $adapter = $gm->for($data['gateway']);
            $result  = $adapter->createCheckout($invoice);
        } catch (NotImplementedException $e) {
            $invoice->forceFill(['status' => 'cancelled'])->save();
            return redirect()->route('user.wallet.buy')->with('error', 'That gateway is not available yet.');
        } catch (\Throwable $e) {
            $invoice->forceFill(['status' => 'cancelled'])->save();
            return redirect()->route('user.wallet.buy')
                ->with('error', "We couldn't start your payment. Please try again.");
        }
        if (($result['kind'] ?? null) === 'redirect') return redirect()->away((string) $result['url']);
        if (($result['kind'] ?? null) === 'view') return view($result['view'], $result['data']);
        return redirect()->route('user.wallet.show');
    }

    /**
     * Spend coins to attach a coin-priced add-on to the user's active
     * subscription. The whole flow runs inside a DB transaction so the
     * debit and the SubscriptionAddon insert succeed-or-fail together.
     */
    public function activateAddon(Request $request, Addon $addon)
    {
        if (!WalletService::isEnabled()) abort(404);
        $user = $request->user();
        if (is_null($addon->coin_cost) || $addon->coin_cost <= 0) {
            return back()->with('error', 'This add-on isn\'t coin-priced.');
        }
        $sub = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due', 'grace'])->latest('id')->first();
        if (!$sub) {
            return redirect()->route('user.upgrade')
                ->with('error', 'You need an active plan before adding coin-priced add-ons.');
        }

        try {
            \DB::transaction(function () use ($user, $addon, $sub) {
                $sa = SubscriptionAddon::create([
                    'subscription_id' => $sub->id,
                    'addon_id'        => $addon->id,
                    'qty'             => 1,
                ]);
                $this->wallets->debit($user, (int) $addon->coin_cost, [
                    'reason'                => "Activated add-on: {$addon->name}",
                    'addon_id'              => $addon->id,
                    'subscription_addon_id' => $sa->id,
                    'meta' => ['subscription_id' => $sub->id],
                ]);
            });
        } catch (InsufficientCoinsException $e) {
            return redirect()->route('user.wallet.buy')->with('error',
                "You need {$e->required} coins to activate {$addon->name} but only have {$e->balance}. Top up to continue.");
        }
        return back()->with('status', "{$addon->name} activated. {$addon->coin_cost} coins spent.");
    }
}
