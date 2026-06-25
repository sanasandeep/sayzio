<?php

namespace App\Services\Billing\Adapters;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\Subscription;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Offline / manual-payment gateway.
 *
 * Flow:
 *   1. User selects "Pay manually" at checkout.
 *   2. createCheckout() marks the invoice awaiting_admin_approval, opens
 *      a PaymentAttempt(status=requires_review), and returns a view
 *      showing the merchant's bank-transfer / UPI instructions (these
 *      come from `gateway_settings.credentials.instructions`, admin-set).
 *   3. Buyer pays through an out-of-band channel.
 *   4. Admin opens /admin/payments/pending, clicks "Mark as paid" with
 *      an optional reference number → ActivateSubscription runs.
 *
 * refund(): admin records the refund as a manual action; status stays
 * 'pending' until the admin separately confirms it's been paid out (the
 * /admin refund detail page flips the switch). For the purposes of the
 * credit-note pipeline we return status=succeeded immediately so the
 * credit note is generated and the user can see it; a real bank-side
 * reversal is tracked out-of-band.
 *
 * chargeRecurring(): issues an awaiting-approval renewal invoice for
 * the subscription's next period. The cron task picks this up and
 * emails the user payment instructions.
 */
class OfflineAdapter extends AbstractAdapter
{
    public function slug(): string { return 'offline'; }
    public function displayName(): string { return 'Pay manually (bank transfer / UPI)'; }

    public function createCheckout(Invoice $invoice): array
    {
        $invoice->forceFill([
            'status'  => 'awaiting_admin_approval',
            'gateway' => $this->slug(),
        ])->save();

        PaymentAttempt::create([
            'invoice_id'  => $invoice->id,
            'gateway'     => $this->slug(),
            'gateway_ref' => 'offline-' . $invoice->number,
            'status'      => 'requires_review',
            'raw_response' => ['note' => 'Awaiting manual admin approval.'],
        ]);

        return [
            'kind' => 'view',
            'view' => 'user.checkout.offline',
            'data' => $this->offlineViewData($invoice),
        ];
    }

    /**
     * Build the data the offline checkout page needs, including a standard
     * `upi://pay` deep link + the values a browser QR engine encodes. This
     * is side-effect free so it can be reused when re-rendering the page
     * after a buyer submits their transaction reference.
     */
    public function offlineViewData(Invoice $invoice): array
    {
        $upiId     = trim((string) $this->cred('upi_id', ''));
        $payeeName = (string) $this->cred('payee_name', config('billing.merchant.name'));
        $amount    = number_format(((int) $invoice->grand_total_minor) / 100, 2, '.', '');
        $currency  = (string) $invoice->currency;
        $note      = 'Invoice ' . $invoice->number;

        // upi://pay deep link — pa(payee VPA), pn(payee name), am(amount),
        // cu(currency), tn(note). Only built when the admin set a UPI ID so
        // the page degrades gracefully (no broken button/QR) otherwise.
        $upiLink = '';
        if ($upiId !== '') {
            $upiLink = 'upi://pay?' . http_build_query([
                'pa' => $upiId,
                'pn' => $payeeName,
                'am' => $amount,
                'cu' => $currency,
                'tn' => $note,
            ], '', '&', PHP_QUERY_RFC3986);
        }

        return [
            'invoice'         => $invoice,
            'instructions'    => (string) $this->cred('instructions', "Please transfer the amount to the account shown on your invoice.\nEmail the transaction reference to billing@1inme.com."),
            'payee_name'      => $payeeName,
            'bank_details'    => (string) $this->cred('bank_details', ''),
            'upi_id'          => $upiId,
            'upi_link'        => $upiLink,
            'upi_amount'      => $amount,
            'upi_note'        => $note,
            'buyer_reference' => self::buyerReferenceFor($invoice),
        ];
    }

    /**
     * The buyer-submitted UPI transaction reference / UTR, read from the
     * most recent offline payment attempt's raw_response. Buyer-reported
     * only — never validated against a real UPI ledger.
     */
    public static function buyerReferenceFor(Invoice $invoice): ?string
    {
        $attempt = $invoice->paymentAttempts()
            ->where('gateway', 'offline')
            ->orderByDesc('id')
            ->first();

        $ref = $attempt?->raw_response['buyer_reference'] ?? null;
        $ref = is_string($ref) ? trim($ref) : '';

        return $ref !== '' ? $ref : null;
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseEvent(Request $request): array
    {
        abort(404);
    }

    /**
     * Offline refunds are a manual action: admin must enter the
     * bank/UPI reversal reference on the admin refund page. We stay
     * in 'pending' until admin confirms — only then does the credit
     * note get issued and the user downgraded. See
     * RefundService::confirmManual() for the confirmation step.
     */
    public function refund(Invoice $invoice, int $amountMinor, string $reason = ''): array
    {
        return [
            'gateway_ref' => null,
            'status'      => 'pending',
        ];
    }

    public function chargeRecurring(Subscription $subscription): array
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        // Currency is LOCKED on the subscription at creation time.
        // Recurring charges MUST resolve pricing in that currency — we
        // never re-derive from the user's country/session at renewal,
        // otherwise a user who changed country mid-cycle would be
        // charged in the wrong currency.
        $price = PricingResolver::priceForCurrency(
            $plan,
            (string) $subscription->currency,
            $subscription->billing_cycle
        );
        $items = [[
            'label'        => $plan->name . ' (' . $subscription->billing_cycle . ' renewal)',
            'amount_minor' => (int) $price['amount_minor'],
            'quantity'     => 1,
            'meta'         => [
                'kind'                    => 'plan_renewal',
                'plan_id'                 => $plan->id,
                'cycle'                   => $subscription->billing_cycle,
                'renew_subscription_id'   => $subscription->id,
            ],
        ]];

        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            $items,
            $subscription->currency
        );
        $invoice->forceFill([
            'status'          => 'awaiting_admin_approval',
            'gateway'         => $this->slug(),
            'subscription_id' => $subscription->id,
        ])->save();

        PaymentAttempt::create([
            'invoice_id'  => $invoice->id,
            'gateway'     => $this->slug(),
            'gateway_ref' => 'offline-renewal-' . $invoice->number,
            'status'      => 'requires_review',
            'raw_response' => ['note' => 'Offline renewal awaiting manual payment.'],
        ]);

        $this->sendOfflineRenewalEmail($subscription, $invoice);

        return ['kind' => 'pending_offline', 'invoice_id' => $invoice->id];
    }

    /**
     * Email the user payment instructions for an offline renewal invoice.
     * Requirement: "email user payment link/instructions for offline renewals".
     * Failure to send must NOT break the cron run — the invoice is already
     * persisted and the admin will see it in /admin/payments/pending.
     */
    protected function sendOfflineRenewalEmail(Subscription $subscription, Invoice $invoice): void
    {
        try {
            $email = optional($subscription->user)->email;
            if (!$email) return;

            $planName    = $subscription->plan?->name ?? 'your plan';
            $amount      = number_format(((int) $invoice->grand_total_minor) / 100, 2) . ' ' . $invoice->currency;
            $dueDate     = $subscription->current_period_end
                ? \Carbon\Carbon::parse($subscription->current_period_end)->toFormattedDateString()
                : 'the end of your current period';
            $payeeName   = (string) $this->cred('payee_name', config('billing.merchant.name'));
            $bankDetails = trim((string) $this->cred('bank_details', ''));
            $upiId       = trim((string) $this->cred('upi_id', ''));
            $instr       = trim((string) $this->cred('instructions', ''));
            $invoiceUrl  = url('/user/billing');

            $lines   = [];
            $lines[] = "Your {$planName} renewal invoice {$invoice->number} is ready.";
            $lines[] = "Amount due: {$amount}";
            $lines[] = "Please pay by: {$dueDate}";
            $lines[] = "";
            $lines[] = "Payment method: manual bank transfer / UPI";
            if ($payeeName !== '')   $lines[] = "Payee: {$payeeName}";
            if ($bankDetails !== '') $lines[] = "Bank details:\n{$bankDetails}";
            if ($upiId !== '')       $lines[] = "UPI: {$upiId}";
            if ($instr !== '')       $lines[] = "\n{$instr}";
            $lines[] = "";
            $lines[] = "View invoice & payment instructions: {$invoiceUrl}";
            $lines[] = "Once we confirm payment, your subscription renews automatically.";

            $body    = implode("\n", $lines);
            $subject = "Action required: pay your {$planName} renewal ({$invoice->number})";

            Mail::raw($body, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Offline renewal email failed: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'invoice_id'      => $invoice->id,
            ]);
        }
    }
}
