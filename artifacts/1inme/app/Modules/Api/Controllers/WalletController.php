<?php

namespace App\Modules\Api\Controllers;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\NotImplementedException;
use App\Services\Billing\WalletService;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile parity for the customer wallet:
 *   GET  /api/v1/wallet           balance + low-balance threshold
 *   GET  /api/v1/wallet/transactions[?type=&limit=]
 *   GET  /api/v1/wallet/packages  active coin packages priced for the user
 *   POST /api/v1/wallet/purchase  start a coin-pack checkout, returns gateway handoff
 */
class WalletController extends Controller
{
    use ApiResponses;

    public function __construct(protected WalletService $wallets) {}

    public function balance(Request $request)
    {
        if (!WalletService::isEnabled()) return $this->err('Wallet is disabled.', 404);
        $user = $request->user();
        $w = $this->wallets->walletFor($user);
        $currency = PricingResolver::currencyForUser($user);
        return $this->ok([
            'enabled'                 => true,
            'balance'                 => (int) $w->balance,
            'low_balance_threshold'   => (int) ($w->low_balance_threshold ?? 0),
            'currency'                => $currency,
            'rate_coins_per_unit'     => WalletService::rateFor($currency),
        ]);
    }

    public function transactions(Request $request)
    {
        if (!WalletService::isEnabled()) return $this->err('Wallet is disabled.', 404);
        $user = $request->user();
        $w = $this->wallets->walletFor($user);
        $limit = max(1, min(100, (int) $request->query('limit', 25)));
        $q = $w->transactions();
        if ($t = $request->query('type')) {
            if (in_array($t, \App\Modules\User\Models\WalletTransaction::TYPES, true)) {
                $q->where('type', $t);
            }
        }
        if ($from = $request->query('from')) {
            $q->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->where('created_at', '<=', $to . ' 23:59:59');
        }

        // Period summary over the whole filtered range (not just the
        // returned window) so the mobile statement can render totals.
        $summaryRow = (clone $q)->reorder()
            ->selectRaw('COALESCE(SUM(CASE WHEN delta_coins > 0 THEN delta_coins ELSE 0 END),0) AS coins_in,'
                . 'COALESCE(SUM(CASE WHEN delta_coins < 0 THEN -delta_coins ELSE 0 END),0) AS coins_out,'
                . 'COALESCE(SUM(delta_coins),0) AS net, COUNT(*) AS entries')
            ->first();

        $rows = $q->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
        $mapTx = fn($tx) => [
            'id'            => $tx->id,
            'type'          => $tx->type,
            'delta_coins'   => (int) $tx->delta_coins,
            'balance_after' => (int) $tx->balance_after,
            'reason'        => $tx->reason,
            'created_at'    => optional($tx->created_at)->toIso8601String(),
        ];
        $items = $rows->map($mapTx)->all();

        // Day-grouped statement: group the returned window by calendar day
        // with per-day subtotals (computed from the same window).
        $days = [];
        foreach ($rows as $tx) {
            $key = $tx->created_at ? $tx->created_at->format('Y-m-d') : 'unknown';
            if (!isset($days[$key])) {
                $days[$key] = ['date' => $key, 'coins_in' => 0, 'coins_out' => 0, 'net' => 0, 'items' => []];
            }
            $d = (int) $tx->delta_coins;
            $days[$key]['net'] += $d;
            if ($d >= 0) $days[$key]['coins_in'] += $d; else $days[$key]['coins_out'] += -$d;
            $days[$key]['items'][] = $mapTx($tx);
        }

        return $this->ok([
            'items'   => $items,
            'days'    => array_values($days),
            'summary' => [
                'coins_in'  => (int) ($summaryRow->coins_in ?? 0),
                'coins_out' => (int) ($summaryRow->coins_out ?? 0),
                'net'       => (int) ($summaryRow->net ?? 0),
                'entries'   => (int) ($summaryRow->entries ?? 0),
            ],
        ]);
    }

    public function packages(Request $request)
    {
        if (!WalletService::isEnabled()) return $this->err('Wallet is disabled.', 404);
        $user = $request->user();
        $currency = PricingResolver::currencyForUser($user);
        $items = CoinPackage::active()->ordered()->with('prices')->get()
            ->map(function ($p) use ($currency, $user) {
                $priced = PricingResolver::priceForCurrency($p, $currency, 'monthly');
                $current = (int) ($priced['amount_minor'] ?? 0);
                $orig = $p->originalPriceDisplay($currency, $current);
                $planBonus = \App\Services\Billing\CoinPlanBonus::breakdownFor($user, $p);
                return [
                    'id'                    => $p->id,
                    'slug'                  => $p->slug,
                    'name'                  => $p->name,
                    'description'           => $p->description,
                    'best_for'              => $p->best_for,
                    'coin_amount'           => (int) $p->coin_amount,
                    'bonus_coins'           => (int) $p->bonus_coins,
                    'total_coins'           => $p->totalCoins(),
                    'plan_bonus_pct'        => $planBonus['plan_bonus_pct'],
                    'plan_bonus_coins'      => $planBonus['plan_bonus_coins'],
                    'plan_bonus_plan_name'  => $planBonus['plan_name'],
                    'total_with_plan_bonus' => $planBonus['total_with_plan_bonus'],
                    'currency'              => $currency,
                    'amount_minor'          => $current,
                    'formatted'             => $priced['formatted'] ?? null,
                    'original_amount_minor' => $orig ? (int) $orig['amount_minor'] : null,
                    'original_formatted'    => $orig['formatted'] ?? null,
                ];
            })->all();
        return $this->ok(['items' => $items, 'currency' => $currency]);
    }

    public function purchase(Request $request, GatewayManager $gm)
    {
        if (!WalletService::isEnabled()) return $this->err('Wallet is disabled.', 404);
        $data = $request->validate([
            'coin_package_id' => 'required|integer|exists:coin_packages,id',
            'gateway' => 'required|string|in:razorpay,stripe,paypal,cashfree,payumoney,offline',
        ]);
        $user = $request->user();
        $package = CoinPackage::active()->findOrFail($data['coin_package_id']);
        $currency = PricingResolver::currencyForUser($user);
        $priced = PricingResolver::priceForCurrency($package, $currency, 'monthly');
        if ((int) $priced['amount_minor'] <= 0) {
            return $this->err('Package is not priced in your currency.', 422);
        }
        $enabledSlugs = array_map(fn($a) => $a->slug(), $gm->enabledAdapters());
        if (!in_array($data['gateway'], $enabledSlugs, true)) {
            return $this->err('Gateway not enabled.', 422);
        }
        $items = [[
            'label'        => $package->name . ' (' . $package->totalCoins() . ' coins)',
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
            $result = $gm->for($data['gateway'])->createCheckout($invoice);
        } catch (NotImplementedException $e) {
            $invoice->forceFill(['status' => 'cancelled'])->save();
            return $this->err('Gateway not available.', 503);
        } catch (\Throwable $e) {
            $invoice->forceFill(['status' => 'cancelled'])->save();
            return $this->err('Could not initiate checkout.', 500);
        }
        return $this->ok([
            'invoice_id'   => $invoice->id,
            'invoice_no'   => $invoice->number,
            'amount_minor' => (int) $invoice->grand_total_minor,
            'currency'     => $invoice->currency,
            'handoff'      => $result,
        ]);
    }
}
