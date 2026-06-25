<?php

namespace App\Modules\Admin\Controllers;

use App\Actions\Billing\ActivateSubscription;
use App\Http\Controllers\Controller;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin approval queue. Lists invoices awaiting manual approval (from
 * the offline gateway) plus any PaymentAttempt a real gateway flagged as
 * `requires_review`. "Mark paid" runs ActivateSubscription, which is
 * re-entrant so a double-click is safe.
 */
class PendingPaymentController extends Controller
{
    public function index()
    {
        // Only queue invoices that are genuinely awaiting a human — plain
        // `pending` means "in-flight, gateway hasn't responded yet" and is
        // surfaced through `requires_review` attempts instead.
        $invoices = Invoice::where('status', 'awaiting_admin_approval')
            ->with(['user', 'paymentAttempts'])
            ->orderByDesc('created_at')
            ->paginate(50);

        $reviewAttempts = PaymentAttempt::where('status', 'requires_review')
            ->with('invoice.user')
            ->orderByDesc('created_at')
            ->limit(100)->get();

        // Buyer-submitted UPI transaction reference / UTR per invoice (read
        // from the offline attempt's raw_response) so the approver can match
        // it against their bank/UPI statement and pre-fill the reference box.
        $buyerRefs = [];
        foreach ($invoices as $inv) {
            foreach ($inv->paymentAttempts as $pa) {
                $ref = $pa->raw_response['buyer_reference'] ?? null;
                if (is_string($ref) && trim($ref) !== '') {
                    $buyerRefs[$inv->id] = trim($ref);
                    break;
                }
            }
        }

        return view('admin.payments.pending', [
            'invoices'       => $invoices,
            'reviewAttempts' => $reviewAttempts,
            'buyerRefs'      => $buyerRefs,
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice, ActivateSubscription $activator)
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:190'],
            'note'      => ['nullable', 'string', 'max:500'],
        ]);

        // Scope the uniqueness to THIS invoice so reused bank references
        // across different invoices don't collide on the (gateway,ref)
        // unique index and attach to the wrong attempt row.
        $rawRef = trim((string) ($data['reference'] ?? ''));
        $reference = 'inv' . $invoice->id . ':' . ($rawRef !== '' ? $rawRef : ('admin-' . $invoice->number));

        $attempt = PaymentAttempt::firstOrCreate(
            ['gateway' => 'offline', 'gateway_ref' => $reference],
            [
                'invoice_id'            => $invoice->id,
                'status'                => 'succeeded',
                'raw_response'          => ['admin_id' => $request->user()?->id, 'note' => $data['note'] ?? null],
                'signature_verified_at' => now(),
            ]
        );
        if ($attempt->status !== 'succeeded') {
            $attempt->update(['status' => 'succeeded', 'signature_verified_at' => now()]);
        }

        $subscription = $activator->run($invoice, 'offline', $reference);

        Log::info('Admin marked invoice paid', [
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->number,
            'admin_id'       => $request->user()?->id,
            'reference'      => $reference,
            'subscription'   => $subscription->id,
        ]);

        return redirect()->route('admin.payments.pending')
            ->with('success', "Invoice {$invoice->number} marked paid. Subscription activated.");
    }
}
