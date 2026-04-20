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
 *      two refunds).
 *   2. Call adapter.refund() → receives gateway_ref + reported status.
 *   3. On success: issue the credit note, downgrade the subscription
 *      if downgrade_on_success, email the user.
 *   4. On failure: mark the refund row failed + surface the reason.
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

        // Concurrency-safe pipeline: lock the invoice row, then re-sum
        // succeeded + pending refunds inside the lock, then create the
        // pending refund row. Two simultaneous callers can't both pass
        // this check because the second one waits for the lock and will
        // see the first refund row.
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
            $refund->forceFill([
                'status'       => $status,
                'gateway_ref'  => $result['gateway_ref'] ?? null,
                'processed_at' => now(),
            ])->save();

            if ($status === 'succeeded') {
                CreditNoteService::issue($refund);
                if ($downgrade && $invoice->subscription_id) {
                    $sub = Subscription::find($invoice->subscription_id);
                    if ($sub && in_array($sub->status, ['active', 'past_due', 'grace'], true)) {
                        $this->lifecycle->cancelImmediately($sub, 'refund');
                    }
                }
                $this->notifyRefundSucceeded($refund);
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

    protected function notifyRefundSucceeded(Refund $refund): void
    {
        try {
            $email = optional($refund->user)->email;
            if (!$email) return;
            $amount = number_format($refund->amount_minor / 100, 2);
            Mail::raw(
                "A refund of {$amount} {$refund->currency} has been issued for invoice {$refund->invoice->number}.\n"
                . "A credit note will be available in your billing history.",
                function ($m) use ($email, $refund) {
                    $m->to($email)->subject("Refund issued for invoice {$refund->invoice->number}");
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Refund email failed: ' . $e->getMessage());
        }
    }
}
