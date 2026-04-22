<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\Billing\InsufficientCoinsException;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;

/**
 * Customer-facing AI credits area (web).
 *
 *   GET  /user/ai-credits              dashboard card-style screen.
 *   GET  /user/ai-credits/transactions paginated history.
 *   POST /user/ai-credits/buy          spend wallet coins for a credit pack.
 *
 * Wallet → AI credits is an instant atomic exchange: the wallet debit
 * and credit grant succeed-or-fail together inside a DB transaction.
 * That avoids the user being "charged but not credited" if the
 * pipeline crashes between the two calls.
 */
class AiCreditsController extends Controller
{
    public function __construct(protected AiCreditService $credits) {}

    public function show(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
        $user = $request->user();
        $balance = $this->credits->balanceFor($user);
        $transactions = $balance->transactions()->limit(10)->get();

        return view('user.ai-credits.show', [
            'balance'      => $balance,
            'transactions' => $transactions,
            'walletBalance'=> app(WalletService::class)->getBalance($user),
            'walletRate'   => AiEngineSettings::walletToCreditsRate(),
            'packs'        => AiEngineSettings::packs(),
            'walletEnabled'=> WalletService::isEnabled(),
        ]);
    }

    public function transactions(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
        $user = $request->user();
        $balance = $this->credits->balanceFor($user);

        // Optional filters: type, feature, date range — all bookmarkable
        // via query string. Used by both the HTML view and CSV export.
        $q = $balance->transactions();
        if ($t = $request->query('type'))      $q->where('type', $t);
        if ($f = $request->query('feature'))   $q->where('feature', $f);
        if ($from = $request->query('from'))   $q->where('created_at', '>=', $from);
        if ($to   = $request->query('to'))     $q->where('created_at', '<=', $to . ' 23:59:59');

        if ($request->query('export') === 'csv') {
            $rows = (clone $q)->limit(10000)->get();
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['date','type','feature','model','tokens_in','tokens_out','delta_credits','balance_after','reason']);
                foreach ($rows as $tx) {
                    fputcsv($out, [
                        optional($tx->created_at)->toIso8601String(),
                        $tx->type, $tx->feature, $tx->model,
                        $tx->tokens_in, $tx->tokens_out,
                        $tx->delta_credits, $tx->balance_after,
                        $tx->reason,
                    ]);
                }
                fclose($out);
            }, 'ai-credit-transactions.csv', ['Content-Type' => 'text/csv']);
        }

        $page = $q->paginate(25)->withQueryString();
        $featureOptions = $balance->transactions()->whereNotNull('feature')->distinct()->pluck('feature');
        return view('user.ai-credits.transactions', [
            'balance'        => $balance,
            'page'           => $page,
            'featureOptions' => $featureOptions,
            'filters'        => [
                'type'    => $request->query('type', ''),
                'feature' => $request->query('feature', ''),
                'from'    => $request->query('from', ''),
                'to'      => $request->query('to', ''),
            ],
        ]);
    }

    public function buy(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
        if (!WalletService::isEnabled()) {
            return back()->with('error', 'Wallet is currently disabled.');
        }
        $user = $request->user();
        $rate = AiEngineSettings::walletToCreditsRate();

        // Stable idempotency key — the buy form submits a hidden token
        // generated once per page render, so a double-click or browser
        // retry collapses to a single ledger row instead of double-charging.
        $clientIdem = trim((string) $request->input('idempotency_key', ''));
        $clientIdem = $clientIdem !== '' ? substr($clientIdem, 0, 80) : null;

        // Two purchase shapes: predefined pack OR custom credit amount.
        // The custom path computes wallet cost from the admin's
        // conversion rate so the price the user sees in the UI matches
        // exactly what we'll debit.
        if ($request->filled('pack_id')) {
            $pack = AiEngineSettings::pack((string) $request->input('pack_id'));
            if (!$pack) return back()->with('error', 'Unknown credit pack.');
            $credits    = (int) $pack['credits'];
            $walletCost = (int) $pack['wallet_cost'];
            $reason     = "AI credits pack: {$pack['label']}";
            $idemKey    = 'ai-pack:' . $user->id . ':' . $pack['id'] . ':'
                . ($clientIdem ?? bin2hex(random_bytes(8)));
            $meta       = ['pack_id' => $pack['id']];
        } else {
            $data = $request->validate([
                'credits' => 'required|integer|min:100|max:1000000',
            ]);
            $credits    = (int) $data['credits'];
            if ($rate <= 0) return back()->with('error', 'Conversion rate is not configured.');
            $walletCost = (int) ceil($credits / $rate);
            $reason     = "AI credits — custom top-up ({$credits} ✦)";
            $idemKey    = 'ai-custom:' . $user->id . ':' . $credits . ':'
                . ($clientIdem ?? bin2hex(random_bytes(8)));
            $meta       = ['kind' => 'custom', 'rate' => $rate];
        }

        try {
            $this->credits->purchaseWithWallet($user, $credits, $walletCost, [
                'reason'          => $reason,
                'idempotency_key' => $idemKey,
                'meta'            => $meta,
            ]);
        } catch (InsufficientCoinsException $e) {
            return redirect()->route('user.wallet.buy')
                ->with('error', "Need {$e->required} coins — only {$e->balance} available. Top up wallet first.");
        }
        return back()->with('status', 'Added ' . number_format($credits) . ' AI credits.');
    }
}
