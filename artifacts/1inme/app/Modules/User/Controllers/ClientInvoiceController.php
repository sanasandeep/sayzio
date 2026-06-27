<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultClientEmail;
use App\Services\Billing\ClientInvoiceService;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\NotImplementedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Workspace-scoped UI for kanban-derived "client invoices":
 *   - dashboard:  list outstanding/paid client invoices with totals
 *   - createDraft: build a draft from one or more selected card ids
 *   - edit / update: modify lines, discount, tax, notes, recipient
 *   - send: stamp sent_at + email a hosted pay link
 *   - pay: public hosted pay page (signed URL) → Stripe Checkout
 *   - receipt: post-payment landing page
 */
class ClientInvoiceController extends Controller
{
    public function dashboard(Request $request)
    {
        $ws  = app('current_workspace');
        $status = $request->query('status');

        $q = Invoice::query()->where('workspace_id', $ws->id)->where('kind', 'client');
        if ($status) $q->where('status', $status);

        $invoices = $q->orderByDesc('id')->paginate(25)->withQueryString();

        $totals = Invoice::query()
            ->where('workspace_id', $ws->id)->where('kind', 'client')
            ->selectRaw("status, SUM(grand_total_minor) AS amt, COUNT(*) AS c")
            ->groupBy('status')->get()->keyBy('status');

        return view('user.client_invoices.dashboard', compact('invoices', 'totals', 'status'));
    }

    public function createDraft(Request $request, ClientInvoiceService $svc)
    {
        $data = $request->validate([
            'card_ids'   => 'required|array|min:1',
            'card_ids.*' => 'integer',
        ]);
        $ws = app('current_workspace');
        $invoice = $svc->draftFromCards($data['card_ids'], $ws, (int) auth()->id());
        return redirect()->route('user.client-invoices.edit', $invoice);
    }

    public function edit(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);
        $ws = app('current_workspace');
        $clients = VaultClient::query()->where('workspace_id', $ws->id)->orderBy('name')->get(['id','name']);
        $emails  = VaultClientEmail::query()->where('workspace_id', $ws->id)->get();
        return view('user.client_invoices.edit', compact('invoice', 'clients', 'emails'));
    }

    public function update(Request $request, Invoice $invoice, ClientInvoiceService $svc)
    {
        $this->authorizeInvoice($invoice);
        if ($invoice->status === 'paid') abort(422, 'Paid invoices cannot be edited.');

        $data = $request->validate([
            'line_items'              => 'array',
            'line_items.*.label'      => 'required|string|max:240',
            'line_items.*.amount_minor' => 'required|integer|min:0',
            'line_items.*.quantity'   => 'nullable|integer|min:1|max:9999',
            'discount_minor'          => 'nullable|integer|min:0',
            'tax_total_minor'         => 'nullable|integer|min:0',
            'notes_md'                => 'nullable|string|max:4000',
            'due_date'                => 'nullable|date',
            'vault_client_id'         => 'nullable|integer',
            'recipient_email'         => 'nullable|email|max:190',
        ]);

        $items = [];
        $existingByCard = collect(is_array($invoice->line_items) ? $invoice->line_items : [])
            ->filter(fn($i) => isset($i['meta']['card_id']))
            ->keyBy(fn($i) => 'card:' . $i['meta']['card_id']);

        foreach ((array) ($data['line_items'] ?? []) as $idx => $li) {
            $row = [
                'label'        => $li['label'],
                'amount_minor' => (int) $li['amount_minor'],
                'quantity'     => (int) ($li['quantity'] ?? 1),
                'meta'         => $existingByCard->values()->get($idx)['meta'] ?? ['kind' => 'manual'],
            ];
            $items[] = $row;
        }

        $invoice->forceFill([
            'discount_minor'  => (int) ($data['discount_minor'] ?? 0),
            'tax_total_minor' => (int) ($data['tax_total_minor'] ?? 0),
            'notes_md'        => $data['notes_md'] ?? null,
            'due_date'        => $data['due_date'] ?? null,
            'vault_client_id' => $data['vault_client_id'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
        ])->save();
        $svc->recalculate($invoice, $items);

        return back()->with('success', 'Invoice saved.');
    }

    public function send(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);
        if ($invoice->status === 'paid') abort(422);
        if (!$invoice->recipient_email) {
            return back()->with('error', 'Pick a recipient email before sending.');
        }
        $invoice->forceFill([
            'status'  => $invoice->status === 'draft' ? 'sent' : $invoice->status,
            'sent_at' => now(),
        ])->save();

        $payUrl = URL::signedRoute('client-invoice.pay', ['invoice' => $invoice->id]);
        \App\Modules\Common\Services\Emailer::send('billing.client_invoice', $invoice->recipient_email, [
            'invoice_number' => $invoice->number,
            'pay_url'        => $payUrl,
        ], [
            'user'      => $invoice->user_id,
            'related'   => $invoice,
            'view_data' => ['invoice' => $invoice, 'payUrl' => $payUrl],
        ]);

        return back()->with('success', 'Invoice emailed to ' . $invoice->recipient_email);
    }

    /** Public, signed pay page — no auth required. */
    public function payPage(Request $request, Invoice $invoice)
    {
        if (!$request->hasValidSignature()) abort(401, 'Pay link expired.');
        if ($invoice->kind !== 'client') abort(404);
        return view('user.client_invoices.pay', ['invoice' => $invoice, 'paid' => $invoice->status === 'paid']);
    }

    /** Public, signed pay handoff — creates a Stripe Checkout session. */
    public function payHandoff(Request $request, Invoice $invoice, GatewayManager $gm)
    {
        if (!$request->hasValidSignature()) abort(401, 'Pay link expired.');
        if ($invoice->kind !== 'client') abort(404);
        if ($invoice->status === 'paid') {
            return redirect()->signedRoute('client-invoice.pay', ['invoice' => $invoice->id]);
        }
        try {
            $adapter = $gm->for('stripe');
            $result  = $adapter->createCheckout($invoice);
        } catch (NotImplementedException $e) {
            return back()->with('error', 'Stripe is not configured for payments yet.');
        }
        if (($result['kind'] ?? null) === 'redirect') {
            return redirect()->away((string) $result['url']);
        }
        return back();
    }

    protected function authorizeInvoice(Invoice $invoice): void
    {
        $ws = app('current_workspace');
        abort_unless($ws && (int) $invoice->workspace_id === (int) $ws->id && $invoice->kind === 'client', 404);
    }
}
