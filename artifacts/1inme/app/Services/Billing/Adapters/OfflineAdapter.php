<?php

namespace App\Services\Billing\Adapters;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\Subscription;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
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
            'data' => [
                'invoice'      => $invoice,
                'instructions' => (string) $this->cred('instructions', "Please transfer the amount to the account shown on your invoice.\nEmail the transaction reference to billing@1inme.com."),
                'payee_name'   => (string) $this->cred('payee_name', config('billing.merchant.name')),
                'bank_details' => (string) $this->cred('bank_details', ''),
                'upi_id'       => (string) $this->cred('upi_id', ''),
            ],
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseEvent(Request $request): array
    {
        abort(404);
    }

    public function refund(Invoice $invoice, int $amountMinor, string $reason = ''): array
    {
        return [
            'gateway_ref' => 'offline-refund-' . $invoice->number . '-' . Str::random(6),
            'status'      => 'succeeded',
        ];
    }

    public function chargeRecurring(Subscription $subscription): array
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        $price = PricingResolver::priceFor($plan, $user, $subscription->billing_cycle);
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

        return ['kind' => 'pending_offline', 'invoice_id' => $invoice->id];
    }
}
