<?php

namespace App\Services\Billing;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Wallet;
use App\Modules\User\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Single chokepoint for every coin movement.
 *
 * - All credits/debits/adjustments/refunds go through here.
 * - Each call wraps the wallet row in a `lockForUpdate()` so concurrent
 *   webhook deliveries / double-clicks can't interleave and corrupt
 *   the running balance.
 * - `idempotency_key` (unique on wallet_transactions) absorbs retried
 *   payment-success webhooks: the second call returns the existing
 *   transaction instead of double-crediting.
 *
 * Currency only applies to PURCHASE transactions (they're priced in
 * USD/INR via the `prices` table). The balance itself is just an
 * integer count of coins.
 */
class WalletService
{
    public const FEATURE_KEY      = 'wallet.enabled';
    public const RATE_KEY         = 'wallet.coin_rates';
    public const API_OVERAGE_KEY  = 'wallet.api_overage_calls_per_coin';

    /** Default included-allowance overage rate: 1 coin buys this many API calls. */
    public const API_OVERAGE_DEFAULT = 100;

    public static function isEnabled(): bool
    {
        return (bool) AppSetting::get(self::FEATURE_KEY, false);
    }

    /**
     * How many extra API calls 1 coin buys once a user exceeds their plan's
     * monthly included allowance. Admin-configurable on the Wallet settings
     * screen. Always >= 1.
     */
    public static function apiOverageCallsPerCoin(): int
    {
        $v = (int) AppSetting::get(self::API_OVERAGE_KEY, self::API_OVERAGE_DEFAULT);
        return $v > 0 ? $v : self::API_OVERAGE_DEFAULT;
    }

    /** Coins-per-currency-unit map, e.g. ['USD' => 100, 'INR' => 1]. */
    public static function rates(): array
    {
        $defaults = ['USD' => 100, 'INR' => 1];
        $stored = AppSetting::get(self::RATE_KEY, []);
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    public static function rateFor(string $currency): float
    {
        $r = self::rates();
        return (float) ($r[strtoupper($currency)] ?? 0);
    }

    /** Get-or-create the user's wallet (idempotent). */
    public function walletFor(User $user): Wallet
    {
        $w = Wallet::where('user_id', $user->id)->first();
        if ($w) return $w;
        return Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    }

    public function getBalance(User $user): int
    {
        return (int) ($this->walletFor($user)->balance ?? 0);
    }

    /** Add coins. Idempotent on `idempotency_key` if provided. */
    public function credit(User $user, int $coins, array $opts = []): WalletTransaction
    {
        if ($coins <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }
        return $this->record($user, $coins, 'purchase', $opts);
    }

    /**
     * Spend coins. Throws InsufficientCoinsException when balance < cost.
     * The whole check-and-deduct runs inside a row-locked transaction.
     */
    public function debit(User $user, int $coins, array $opts = []): WalletTransaction
    {
        if ($coins <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }
        return $this->record($user, -$coins, 'spend', $opts);
    }

    /** Admin manual adjustment — signed delta, requires a reason. */
    public function adjust(User $user, int $delta, string $reason, ?int $adminId = null, array $opts = []): WalletTransaction
    {
        if ($delta === 0) {
            throw new \InvalidArgumentException('Adjustment delta cannot be zero.');
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Adjustment reason is required.');
        }
        $opts = array_merge(['reason' => $reason, 'admin_id' => $adminId], $opts);
        return $this->record($user, $delta, 'adjustment', $opts);
    }

    /** Reverse a coin grant (e.g. invoice refunded). Negative delta. */
    public function reverseGrant(User $user, int $coins, array $opts = []): WalletTransaction
    {
        if ($coins <= 0) {
            throw new \InvalidArgumentException('Reverse amount must be positive.');
        }
        return $this->record($user, -$coins, 'refund', $opts);
    }

    /**
     * Core ledger writer. Locks the wallet row, enforces non-negative
     * balance for spends/refunds, computes balance_after, and writes
     * the transaction atomically.
     */
    protected function record(User $user, int $delta, string $type, array $opts): WalletTransaction
    {
        $idem = isset($opts['idempotency_key']) && $opts['idempotency_key'] !== ''
            ? (string) $opts['idempotency_key'] : null;

        if ($idem) {
            $existing = WalletTransaction::where('idempotency_key', $idem)->first();
            if ($existing) return $existing;
        }

        return DB::transaction(function () use ($user, $delta, $type, $opts, $idem) {
            // Take an exclusive lock on the wallet row so concurrent
            // credits/debits serialize.
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
                $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->first();
            }

            $newBalance = (int) $wallet->balance + (int) $delta;
            if ($newBalance < 0) {
                throw new InsufficientCoinsException(abs($delta), (int) $wallet->balance);
            }

            $wallet->forceFill(['balance' => $newBalance])->save();

            try {
                $tx = WalletTransaction::create([
                    'wallet_id'             => $wallet->id,
                    'user_id'               => $user->id,
                    'type'                  => $type,
                    'delta_coins'           => $delta,
                    'balance_after'         => $newBalance,
                    'idempotency_key'       => $idem,
                    'reason'                => $opts['reason'] ?? null,
                    'invoice_id'            => $opts['invoice_id'] ?? null,
                    'coin_package_id'       => $opts['coin_package_id'] ?? null,
                    'addon_id'              => $opts['addon_id'] ?? null,
                    'subscription_addon_id' => $opts['subscription_addon_id'] ?? null,
                    'admin_id'              => $opts['admin_id'] ?? null,
                    'meta'                  => $opts['meta'] ?? null,
                    'created_at'            => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Race on the unique idempotency_key — another request
                // wrote the row between our get-or-create check and now.
                // Roll back our balance change and return the winner.
                if ($idem && ($winner = WalletTransaction::where('idempotency_key', $idem)->first())) {
                    // Undo the balance bump we just applied.
                    $wallet->forceFill(['balance' => (int) $wallet->balance - $delta])->save();
                    return $winner;
                }
                throw $e;
            }

            // Send notifications outside of the wallet lock implications
            // by deferring to a method that swallows its own errors.
            $this->afterTransaction($user, $wallet->fresh(), $tx, $type);
            return $tx;
        });
    }

    /** Notification dispatch. Best-effort — failures don't roll back. */
    protected function afterTransaction(User $user, Wallet $wallet, WalletTransaction $tx, string $type): void
    {
        try {
            if ($type === 'purchase') {
                $this->notify($user, 'wallet.purchased', [
                    'coins'   => (int) $tx->delta_coins,
                    'balance' => (int) $tx->balance_after,
                    'tx_id'   => $tx->id,
                ], 'You bought ' . number_format(abs((int) $tx->delta_coins)) . ' coins',
                    "Your purchase succeeded. New balance: " . number_format($tx->balance_after) . " coins.");
            }
            if ($type === 'adjustment') {
                $sign = $tx->delta_coins >= 0 ? 'credited' : 'debited';
                $this->notify($user, 'wallet.adjusted', [
                    'delta'   => (int) $tx->delta_coins,
                    'balance' => (int) $tx->balance_after,
                    'reason'  => $tx->reason,
                ], "Wallet adjustment",
                    "An admin {$sign} " . number_format(abs((int) $tx->delta_coins))
                    . " coins. Reason: " . ($tx->reason ?? '—')
                    . ". New balance: " . number_format($tx->balance_after) . " coins.");
            }

            // Low-balance warning: only fire once per dip below threshold;
            // re-arm when balance recovers above threshold.
            $threshold = (int) ($wallet->low_balance_threshold ?? 100);
            $balance = (int) $wallet->balance;
            if ($threshold > 0 && $balance > 0 && $balance < $threshold) {
                if (!$wallet->low_balance_notified_at) {
                    $this->notify($user, 'wallet.low_balance', [
                        'balance' => $balance, 'threshold' => $threshold,
                    ], "Coin balance is running low",
                        "Your wallet is at " . number_format($balance) . " coins (warning threshold: "
                        . number_format($threshold) . "). Top up to keep using coin-priced add-ons.");
                    $wallet->forceFill(['low_balance_notified_at' => now()])->save();
                }
            } elseif ($balance >= $threshold && $wallet->low_balance_notified_at) {
                // Re-arm so we'll alert again on the next dip.
                $wallet->forceFill(['low_balance_notified_at' => null])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Wallet notification failed: ' . $e->getMessage(), ['user_id' => $user->id]);
        }
    }

    protected function notify(User $user, string $type, array $data, string $subject, string $body): void
    {
        UserNotification::create([
            'user_id' => $user->id,
            'type'    => $type,
            'data'    => $data,
            'created_at' => now(),
        ]);
        if ($user->email) {
            try {
                Mail::raw($body, function ($m) use ($user, $subject) {
                    $m->to($user->email)->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::info('Wallet email skipped: ' . $e->getMessage());
            }
        }
    }
}
