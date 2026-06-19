<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Services\Billing\InsufficientCoinsException;
use App\Services\Billing\WalletService;

/**
 * AI usage charger — bills AI calls straight from the coin wallet.
 *
 * IMPORTANT: there is no separate "AI credit" balance any more (the old
 * credit ledger has been retired). Every AI call is paid for in COINS from the
 * user's single coin wallet at call time. This class is the thin
 * AI-facing adapter over {@see WalletService}: it tags each wallet
 * transaction so AI spend can be reported on (meta.ai = true, plus
 * feature / model / token counts), and translates the wallet's
 * InsufficientCoinsException into the AI-flavoured
 * {@see InsufficientCoinsForAiException} that feature UIs already catch.
 *
 * Amounts are whole coins (the wallet balance is an integer). Callers
 * compute the per-call coin cost from fractional coins-per-1k-token
 * rates and ceil() it before handing it here.
 */
class AiUsageCharger
{
    public function __construct(protected WalletService $wallets) {}

    /** Current spendable coin balance. */
    public function getBalance(User $user): int
    {
        return $this->wallets->getBalance($user);
    }

    /**
     * Spend coins for an AI call. Throws InsufficientCoinsForAiException
     * when the wallet can't cover it (the worst-case pre-gate in
     * OpenAiService normally prevents ever reaching that path).
     */
    public function charge(User $user, int $coins, array $opts = []): WalletTransaction
    {
        if ($coins <= 0) {
            throw new \InvalidArgumentException('Charge amount must be positive.');
        }
        try {
            return $this->wallets->debit($user, $coins, $this->walletOpts($opts));
        } catch (InsufficientCoinsException $e) {
            throw new InsufficientCoinsForAiException($e->required, $e->balance);
        }
    }

    /** Refund coins for a failed/partial AI call (positive delta, silent). */
    public function refund(User $user, int $coins, array $opts = []): WalletTransaction
    {
        if ($coins <= 0) {
            throw new \InvalidArgumentException('Refund amount must be positive.');
        }
        return $this->wallets->refundCoins($user, $coins, $this->walletOpts($opts));
    }

    /**
     * Translate the AI-call option bag into the wallet transaction shape,
     * folding the AI attribution (feature / model / tokens / related id)
     * into the transaction `meta` and stamping meta.ai = true so usage
     * analytics can isolate AI spend from coin purchases and other
     * coin-priced add-ons.
     */
    protected function walletOpts(array $opts): array
    {
        $meta = is_array($opts['meta'] ?? null) ? $opts['meta'] : [];
        foreach (['feature', 'model', 'tokens_in', 'tokens_out', 'related_id'] as $k) {
            if (array_key_exists($k, $opts) && $opts[$k] !== null) {
                $meta[$k] = $opts[$k];
            }
        }
        $meta['ai'] = true;

        return [
            'reason'          => $opts['reason'] ?? null,
            'idempotency_key' => $opts['idempotency_key'] ?? null,
            'admin_id'        => $opts['admin_id'] ?? null,
            'meta'            => $meta,
        ];
    }
}
