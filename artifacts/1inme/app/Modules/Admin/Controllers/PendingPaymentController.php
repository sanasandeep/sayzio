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
        $invoices = Invoice::whereIn('status', ['awaiting_admin_approval', 'pending'])
            ->with(['user', 'paymentAttempts'])
            ->orderByDesc('created_at')
            ->paginate(50);

        $reviewAttempts = PaymentAttempt::where('status', 'requires_review')
            ->with('invoice.user')
            ->orderByDesc('created_at')
            ->limit(100)->get();

        return view('admin.payments.pending', [
            'invoices'       => $invoices,
            'reviewAttempts' => $reviewAttempts,
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice, ActivateSubscription $activator)
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:190'],
            'note'      => ['nullable', 'string', 'max:500'],
        ]);

        $reference = $data['reference'] ?? ('admin-' . $invoice->number);

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
