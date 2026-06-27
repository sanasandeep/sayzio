<?php

namespace App\Services\Billing;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Orchestrates the refund pipeline regardless of who triggered it
 * (user within policy window, or admin from invoice detail).
 *
 *   1. Create a Refund row in status=pending (inside a DB transaction
 *      with a row-lock on the invoice so a double-click can't issue
 *      two refunds, and the pending+succeeded sum is re-checked inside
 *      the lock).
 *   2. Call adapter.refund(). Gateway adapters that settle instantly
 *      (e.g. a real Stripe refund) return status=succeeded; the
 *      offline adapter returns 'pending' and requires a separate
 *      admin confirmation step (confirmManual()).
 *   3. On succeeded: issue credit note, downgrade subscription if
 *      downgrade_on_success, email the user.
 *   4. On failed: mark refund failed + surface the reason.
 *   5. On pending (offline): leave as-is, email user an
 *      acknowledgement, wait for admin to confirm with a gateway_ref.
 */
class RefundService
{
    public function __construct(
        protected GatewayManager $gateways,
        protected SubscriptionLifecycle $lifecycle,
    ) {}

    public function issue(Invoice $invoice, int $amountMinor, array $opts = []): Refund
    {
        $reason         = (string) ($opts['reason'] ?? '');
        $userInitiated  = (bool) ($opts['user_initiated'] ?? false);
        $adminId        = $opts['admin_id'] ?? null;
        $downgrade      = (bool) ($opts['downgrade_on_success'] ?? true);

        if ($amountMinor <= 0 || $amountMinor > (int) $invoice->grand_total_minor) {
            throw new \InvalidArgumentException('Refund amount out of range.');
        }
        if ($invoice->status !== 'paid') {
            throw new \InvalidArgumentException('Cannot refund an unpaid invoice.');
        }

        $refund = DB::transaction(function () use ($invoice, $amountMinor, $reason, $userInitiated, $adminId, $downgrade) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $already = (int) Refund::where('invoice_id', $locked->id)
                ->whereIn('status', ['pending', 'succeeded'])
                ->sum('amount_minor');
            if ($already + $amountMinor > (int) $locked->grand_total_minor) {
                throw new \InvalidArgumentException('Refund would exceed invoice total.');
            }
            return Refund::create([
                'invoice_id'           => $invoice->id,
                'user_id'              => $invoice->user_id,
                'amount_minor'         => $amountMinor,
                'currency'             => $invoice->currency,
                'status'               => 'pending',
                'gateway'              => $invoice->gateway ?? 'offline',
                'reason'               => $reason,
                'created_by_admin_id'  => $adminId,
                'user_initiated'       => $userInitiated,
                'downgrade_on_success' => $downgrade,
            ]);
        });

        try {
            $adapter = $this->gateways->for($refund->gateway);
            $result  = $adapter->refund($invoice, $amountMinor, $reason);
            $status  = $result['status'] ?? 'succeeded';

            if ($status === 'pending') {
                // Offline / manual flow. Acknowledge to the user; admin
                // will call confirmManual() once the money is out.
                $refund->forceFill([
                    'gateway_ref' => $result['gateway_ref'] ?? null,
                ])->save();
                $this->notifyRefundAcknowledged($refund);
                return $refund->fresh();
            }

            $refund->forceFill([
                'status'       => $status,
                'gateway_ref'  => $result['gateway_ref'] ?? null,
                'processed_at' => now(),
            ])->save();

            if ($status === 'succeeded') {
                $this->finalizeSucceeded($refund->fresh());
            }
        } catch (\Throwable $e) {
            Log::error('Refund failed: ' . $e->getMessage(), ['refund_id' => $refund->id]);
            $refund->forceFill([
                'status'       => 'failed',
                'processed_at' => now(),
            ])->save();
            throw $e;
        }
        return $refund->fresh();
    }

    /**
     * Admin-only: confirm that a pending offline refund has actually
     * been paid out, capturing the merchant's bank/UPI reversal
     * reference. Transitions the refund to 'succeeded' atomically and
     * then runs the post-success pipeline (credit note + downgrade +
     * email).
     */
    public function confirmManual(Refund $refund, string $gatewayRef, ?int $adminId = null): Refund
    {
        if (trim($gatewayRef) === '') {
            throw new \InvalidArgumentException('A payout reference is required.');
        }

        $refund = DB::transaction(function () use ($refund, $gatewayRef, $adminId) {
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw new \InvalidArgumentException('Only pending refunds can be confirmed.');
            }
            $locked->forceFill([
                'status'              => 'succeeded',
                'gateway_ref'         => $gatewayRef,
                'processed_at'        => now(),
                'created_by_admin_id' => $locked->created_by_admin_id ?? $adminId,
            ])->save();
            return $locked;
        });

        $this->finalizeSucceeded($refund->fresh());
        return $refund->fresh();
    }

    /**
     * Called by a gateway webhook adapter when an asynchronous refund
     * transitions from pending to succeeded (e.g. Razorpay's
     * `refund.processed`). Transitions the row atomically and then
     * runs the same CN / downgrade / email pipeline as the sync path.
     * Idempotent: a refund that's already succeeded is a no-op.
     */
    public function handleGatewaySuccess(Refund $refund, string $gatewayRef): Refund
    {
        $fresh = DB::transaction(function () use ($refund, $gatewayRef) {
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'succeeded') return $locked;
            if (!in_array($locked->status, ['pending', 'failed'], true)) return $locked;
            $locked->forceFill([
                'status'       => 'succeeded',
                'gateway_ref'  => $gatewayRef ?: $locked->gateway_ref,
                'processed_at' => now(),
            ])->save();
            return $locked;
        });
        if ($fresh->wasChanged('status')) {
            $this->finalizeSucceeded($fresh->fresh());
        }
        return $fresh->fresh();
    }

    /** CN + downgrade + user email. Shared by both entry points. */
    protected function finalizeSucceeded(Refund $refund): void
    {
        CreditNoteService::issue($refund);
        $invoice = $refund->invoice;

        // Coin-pack invoice: reverse the grant so the user can't keep
        // the coins after getting their money back. Per spec, this
        // hard-fails (and the refund is rolled back to failed) if the
        // user already spent the coins — admin must intervene.
        if ($invoice && self::isCoinPackInvoice($invoice)) {
            $coins = self::coinsFromInvoice($invoice);
            if ($coins > 0) {
                try {
                    app(WalletService::class)->reverseGrant($invoice->user, $coins, [
                        'reason'          => 'Refund for invoice ' . $invoice->number,
                        'invoice_id'      => $invoice->id,
                        'idempotency_key' => 'refund:' . $refund->id,
                    ]);
                } catch (InsufficientCoinsException $e) {
                    $refund->forceFill(['status' => 'failed'])->save();
                    Log::error('Coin-pack refund blocked: insufficient balance', [
                        'refund_id' => $refund->id,
                        'invoice'   => $invoice->number,
                        'required'  => $e->required,
                        'balance'   => $e->balance,
                    ]);
                    throw $e;
                }
            }
            $this->notifyRefundSucceeded($refund);
            return;
        }

        if ($refund->downgrade_on_success && $invoice && $invoice->subscription_id) {
            $sub = Subscription::find($invoice->subscription_id);
            if ($sub && in_array($sub->status, ['active', 'past_due', 'grace'], true)) {
                $this->lifecycle->cancelImmediately($sub, 'refund');
            }
        }
        $this->notifyRefundSucceeded($refund);
    }

    public static function isCoinPackInvoice(Invoice $invoice): bool
    {
        foreach ((array) ($invoice->line_items ?? []) as $li) {
            if (($li['meta']['kind'] ?? null) === 'coin_package') return true;
        }
        return false;
    }

    public static function coinsFromInvoice(Invoice $invoice): int
    {
        $sum = 0;
        foreach ((array) ($invoice->line_items ?? []) as $li) {
            if (($li['meta']['kind'] ?? null) !== 'coin_package') continue;
            $sum += (int) ($li['meta']['coins'] ?? 0) + (int) ($li['meta']['bonus'] ?? 0);
        }
        return $sum;
    }

    protected function notifyRefundSucceeded(Refund $refund): void
    {
        try {
            $email = optional($refund->user)->email;
            if (!$email) return;
            $amount = number_format($refund->amount_minor / 100, 2);
            \App\Modules\Common\Services\Emailer::send('billing.refund_issued', $email, [
                'amount'         => $amount,
                'currency'       => $refund->currency,
                'invoice_number' => $refund->invoice->number,
            ], [
                'user'    => optional($refund->user)->id,
                'related' => $refund,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Refund email failed: ' . $e->getMessage());
        }
    }

    protected function notifyRefundAcknowledged(Refund $refund): void
    {
        try {
            $email = optional($refund->user)->email;
            if (!$email) return;
            $amount = number_format($refund->amount_minor / 100, 2);
            \App\Modules\Common\Services\Emailer::send('billing.refund_acknowledged', $email, [
                'amount'         => $amount,
                'currency'       => $refund->currency,
                'invoice_number' => $refund->invoice->number,
            ], [
                'user'    => optional($refund->user)->id,
                'related' => $refund,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Refund ack email failed: ' . $e->getMessage());
        }
    }
}
