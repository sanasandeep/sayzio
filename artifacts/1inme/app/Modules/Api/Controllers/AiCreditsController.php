<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\Billing\InsufficientCoinsException;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile parity for the AI credits area:
 *   GET  /api/v1/ai/credits                balance + lifetime totals.
 *   GET  /api/v1/ai/credits/transactions   paginated history.
 *   GET  /api/v1/ai/credits/packs          buyable wallet→credit packs.
 *   POST /api/v1/ai/credits/purchase       atomic wallet→credits exchange.
 */
class AiCreditsController extends Controller
{
    use ApiResponses;

    public function __construct(protected AiCreditService $credits) {}

    public function balance(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $user = $request->user();
        $b = $this->credits->balanceFor($user);
        return $this->ok([
            'enabled'             => true,
            'balance'             => (int) $b->balance,
            'lifetime_purchased'  => (int) $b->lifetime_purchased,
            'lifetime_spent'      => (int) $b->lifetime_spent,
            'wallet_to_credits_rate' => AiEngineSettings::walletToCreditsRate(),
        ]);
    }

    public function transactions(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        $user = $request->user();
        $b = $this->credits->balanceFor($user);
        $limit = max(1, min(100, (int) $request->query('limit', 25)));
        $items = $b->transactions()->limit($limit)->get()->map(fn($tx) => [
            'id'             => $tx->id,
            'type'           => $tx->type,
            'delta_credits'  => (int) $tx->delta_credits,
            'balance_after'  => (int) $tx->balance_after,
            'feature'        => $tx->feature,
            'model'          => $tx->model,
            'tokens_in'      => $tx->tokens_in,
            'tokens_out'     => $tx->tokens_out,
            'reason'         => $tx->reason,
            'created_at'     => optional($tx->created_at)->toIso8601String(),
        ])->all();
        return $this->ok(['items' => $items]);
    }

    public function packs(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        return $this->ok([
            'items'    => array_values(AiEngineSettings::packs()),
            'rate'     => AiEngineSettings::walletToCreditsRate(),
        ]);
    }

    public function purchase(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) return $this->fail('AI Engine is disabled.', 404);
        if (!WalletService::isEnabled())    return $this->fail('Wallet is disabled.', 422);

        $user = $request->user();
        $rate = AiEngineSettings::walletToCreditsRate();

        // Stable client-supplied idempotency key (header takes precedence).
        // Repeated submissions with the same key collapse to one purchase.
        $clientIdem = trim((string) ($request->header('Idempotency-Key')
            ?? $request->input('idempotency_key', '')));
        $clientIdem = $clientIdem !== '' ? substr($clientIdem, 0, 80) : null;

        // Accept either a pack_id or a custom credits amount.
        if ($request->filled('pack_id')) {
            $data = $request->validate(['pack_id' => 'required|string']);
            $pack = AiEngineSettings::pack($data['pack_id']);
            if (!$pack) return $this->fail('Unknown credit pack.', 404);
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
            if ($rate <= 0) return $this->fail('Conversion rate is not configured.', 422);
            $credits    = (int) $data['credits'];
            $walletCost = (int) ceil($credits / $rate);
            $reason     = "AI credits — custom top-up ({$credits} ✦)";
            $idemKey    = 'ai-custom:' . $user->id . ':' . $credits . ':'
                . ($clientIdem ?? bin2hex(random_bytes(8)));
            $meta       = ['kind' => 'custom', 'rate' => $rate];
        }

        try {
            $tx = $this->credits->purchaseWithWallet(
                $user, $credits, $walletCost,
                ['reason' => $reason, 'idempotency_key' => $idemKey, 'meta' => $meta]
            );
        } catch (InsufficientCoinsException $e) {
            return $this->fail(
                "Need {$e->required} coins, have {$e->balance}.",
                402,
                'insufficient_coins',
                ['required' => $e->required, 'balance' => $e->balance]
            );
        }
        $b = $this->credits->balanceFor($user);
        return $this->ok([
            'transaction_id' => $tx->id,
            'credits_added'  => (int) $tx->delta_credits,
            'wallet_cost'    => $walletCost,
            'balance'        => (int) $b->balance,
        ]);
    }
}
