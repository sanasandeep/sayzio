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

    /** CN + downgrade + user email. Shared by both entry points. */
    protected function finalizeSucceeded(Refund $refund): void
    {
        CreditNoteService::issue($refund);
        $invoice = $refund->invoice;
        if ($refund->downgrade_on_success && $invoice && $invoice->subscription_id) {
            $sub = Subscription::find($invoice->subscription_id);
            if ($sub && in_array($sub->status, ['active', 'past_due', 'grace'], true)) {
                $this->lifecycle->cancelImmediately($sub, 'refund');
            }
        }
        $this->notifyRefundSucceeded($refund);
    }

    protected function notifyRefundSucceeded(Refund $refund): void
    {
        try {
            $email = optional($refund->user)->email;
            if (!$email) return;
            $amount = number_format($refund->amount_minor / 100, 2);
            Mail::raw(
                "A refund of {$amount} {$refund->currency} has been issued for invoice {$refund->invoice->number}.\n"
                . "A credit note is available in your billing history.",
                function ($m) use ($email, $refund) {
                    $m->to($email)->subject("Refund issued for invoice {$refund->invoice->number}");
                }
            );
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
            Mail::raw(
                "We've received your refund request of {$amount} {$refund->currency} for invoice {$refund->invoice->number}.\n"
                . "Offline refunds are processed manually; you'll get a second email with the credit note once the payout is confirmed.",
                function ($m) use ($email, $refund) {
                    $m->to($email)->subject("Refund request received for invoice {$refund->invoice->number}");
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Refund ack email failed: ' . $e->getMessage());
        }
    }
}
