<?php

namespace App\Services\Billing\Adapters;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use Illuminate\Http\Request;

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
 * There is no webhook for this gateway — its approval is the admin
 * action — so verifyWebhook() always returns false and parseEvent()
 * throws.
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
}
