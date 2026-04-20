<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Invoice;
use App\Services\Billing\RefundService;
use Illuminate\Http\Request;

/**
 * Admin-side refund tooling. Lives on the invoice detail page
 * (/admin/invoices/{invoice}) as an "Issue refund" form. Can do
 * partial or full refunds; always writes a credit note.
 */
class RefundController extends Controller
{
    public function show(Request $request, Invoice $invoice)
    {
        $invoice->load(['refunds' => function ($q) {
            $q->orderByDesc('id');
        }, 'user', 'paymentAttempts']);
        return view('admin.invoices.show', compact('invoice'));
    }

    public function store(Request $request, Invoice $invoice, RefundService $refunds)
    {
        $data = $request->validate([
            'amount'             => 'required|numeric|min:0.01',
            'reason'             => 'nullable|string|max:1000',
            'downgrade'          => 'nullable|boolean',
        ]);
        $amountMinor = (int) round(((float) $data['amount']) * 100);
        try {
            $refund = $refunds->issue($invoice, $amountMinor, [
                'reason'               => $data['reason'] ?? '',
                'user_initiated'       => false,
                'admin_id'             => $request->user()->id,
                'downgrade_on_success' => (bool) ($data['downgrade'] ?? true),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('status', "Refund {$refund->id} issued.");
    }
}

