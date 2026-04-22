<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiCreditBalance;
use App\Modules\User\Models\AiCreditTransaction;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Services\Billing\InsufficientCoinsException;
use App\Services\Billing\WalletService;
use Illuminate\Support\Facades\DB;

/**
 * Single chokepoint for every AI credit movement.
 *
 * All charge/refund/grant/purchase paths flow through here. Each call
 * locks the balance row so concurrent feature calls can't interleave
 * and corrupt the running total. `idempotency_key` (unique on
 * ai_credit_transactions) absorbs retried purchases.
 */
class AiCreditService
{
    public function __construct(protected WalletService $wallets) {}

    public function balanceFor(User $user): AiCreditBalance
    {
        $b = AiCreditBalance::where('user_id', $user->id)->first();
        if ($b) return $b;
        return AiCreditBalance::create(['user_id' => $user->id, 'balance' => 0]);
    }

    public function getBalance(User $user): int
    {
        return (int) $this->balanceFor($user)->balance;
    }

    /**
     * Spend credits for an AI call. Throws InsufficientAiCreditsException
     * when the user doesn't have enough.
     */
    public function charge(User $user, int $credits, array $opts = []): AiCreditTransaction
    {
        if ($credits <= 0) {
            throw new \InvalidArgumentException('Charge amount must be positive.');
        }
        return $this->record($user, -$credits, 'spend', $opts);
    }

    /** Refund a previous charge. */
    public function refund(User $user, int $credits, array $opts = []): AiCreditTransaction
    {
        if ($credits <= 0) {
            throw new \InvalidArgumentException('Refund amount must be positive.');
        }
        return $this->record($user, $credits, 'refund', $opts);
    }

    /** Free grant (signup bonus, promo, etc). */
    public function grant(User $user, int $credits, array $opts = []): AiCreditTransaction
    {
        if ($credits <= 0) {
            throw new \InvalidArgumentException('Grant amount must be positive.');
        }
        return $this->record($user, $credits, 'grant', $opts);
    }

    /** Signed admin adjustment, requires reason. */
    public function adminAdjust(User $user, int $delta, string $reason, ?int $adminId = null, array $opts = []): AiCreditTransaction
    {
        if ($delta === 0) {
            throw new \InvalidArgumentException('Adjustment delta cannot be zero.');
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Adjustment reason is required.');
        }
        $opts = array_merge(['reason' => $reason, 'admin_id' => $adminId], $opts);
        return $this->record($user, $delta, 'admin_adjustment', $opts);
    }

    /**
     * Purchase credits by spending wallet coins atomically.
     *
     * Either both (wallet debit + credit grant) succeed or both roll
     * back, so the user can never end up "charged but not credited".
     */
    public function purchaseWithWallet(User $user, int $credits, int $walletCost, array $opts = []): AiCreditTransaction
    {
        if ($credits <= 0 || $walletCost <= 0) {
            throw new \InvalidArgumentException('Credits and wallet cost must be positive.');
        }
        $idem = $opts['idempotency_key'] ?? null;
        if ($idem) {
            $existing = AiCreditTransaction::where('idempotency_key', $idem)->first();
            if ($existing) return $existing;
        }

        return DB::transaction(function () use ($user, $credits, $walletCost, $opts, $idem) {
            // Debit wallet first — it raises InsufficientCoinsException
            // when balance is too low; the transaction wrapper rolls
            // back so no AI credits are issued.
            $walletTx = $this->wallets->debit($user, $walletCost, [
                'reason'          => $opts['reason'] ?? 'AI credits purchase',
                'idempotency_key' => $idem ? $idem . ':wallet' : null,
                'meta'            => array_merge(
                    ['kind' => 'ai_credits', 'credits' => $credits],
                    $opts['meta'] ?? []
                ),
            ]);

            return $this->record($user, $credits, 'purchase', array_merge($opts, [
                'wallet_transaction_id' => $walletTx->id,
            ]));
        });
    }

    /**
     * Core ledger writer. Locks the balance row, enforces non-negative
     * balance for spends, computes balance_after, and writes the
     * transaction atomically.
     */
    protected function record(User $user, int $delta, string $type, array $opts): AiCreditTransaction
    {
        $idem = isset($opts['idempotency_key']) && $opts['idempotency_key'] !== ''
            ? (string) $opts['idempotency_key'] : null;

        if ($idem) {
            $existing = AiCreditTransaction::where('idempotency_key', $idem)->first();
            if ($existing) return $existing;
        }

        return DB::transaction(function () use ($user, $delta, $type, $opts, $idem) {
            $row = AiCreditBalance::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$row) {
                $row = AiCreditBalance::create(['user_id' => $user->id, 'balance' => 0]);
                $row = AiCreditBalance::whereKey($row->id)->lockForUpdate()->first();
            }

            $newBalance = (int) $row->balance + $delta;
            if ($newBalance < 0) {
                throw new InsufficientAiCreditsException(abs($delta), (int) $row->balance);
            }

            $row->forceFill([
                'balance' => $newBalance,
                'lifetime_purchased' => (int) $row->lifetime_purchased
                    + ($type === 'purchase' && $delta > 0 ? $delta : 0),
                'lifetime_spent' => (int) $row->lifetime_spent
                    + ($type === 'spend' && $delta < 0 ? abs($delta) : 0),
            ])->save();

            try {
                return AiCreditTransaction::create([
                    'balance_id'            => $row->id,
                    'user_id'               => $user->id,
                    'type'                  => $type,
                    'delta_credits'         => $delta,
                    'balance_after'         => $newBalance,
                    'idempotency_key'       => $idem,
                    'feature'               => $opts['feature'] ?? null,
                    'related_id'            => $opts['related_id'] ?? null,
                    'model'                 => $opts['model'] ?? null,
                    'tokens_in'             => $opts['tokens_in'] ?? null,
                    'tokens_out'            => $opts['tokens_out'] ?? null,
                    'wallet_transaction_id' => $opts['wallet_transaction_id'] ?? null,
                    'admin_id'              => $opts['admin_id'] ?? null,
                    'reason'                => $opts['reason'] ?? null,
                    'meta'                  => $opts['meta'] ?? null,
                    'created_at'            => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Idempotency-key race: another request committed the
                // matching ledger row between our lookup and insert.
                if ($idem && ($winner = AiCreditTransaction::where('idempotency_key', $idem)->first())) {
                    $row->forceFill([
                        'balance' => (int) $row->balance - $delta,
                        'lifetime_purchased' => (int) $row->lifetime_purchased
                            - ($type === 'purchase' && $delta > 0 ? $delta : 0),
                        'lifetime_spent' => (int) $row->lifetime_spent
                            - ($type === 'spend' && $delta < 0 ? abs($delta) : 0),
                    ])->save();
                    return $winner;
                }
                throw $e;
            }
        });
    }
}
