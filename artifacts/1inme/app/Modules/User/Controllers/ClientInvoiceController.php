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

    public function send(Request $request, Invoice $invoice, ClientInvoiceService $svc)
    {
        $this->authorizeInvoice($invoice);
        if ($invoice->status === 'paid') abort(422);
        if (!$invoice->recipient_email) {
            return back()->with('error', 'Pick a recipient email before sending.');
        }
        $svc->markSent($invoice);
        return back()->with('success', 'Invoice emailed to ' . $invoice->recipient_email);
    }

    /** Standalone invoice creation form (not derived from kanban cards). */
    public function create(Request $request)
    {
        $ws = app('current_workspace');
        $clients   = VaultClient::query()->where('workspace_id', $ws->id)->orderBy('name')->get(['id', 'name']);
        $emails    = VaultClientEmail::query()->where('workspace_id', $ws->id)->get();
        $companies = \App\Modules\User\Models\BillingCompany::where('user_id', auth()->id())->orderByDesc('is_default')->orderBy('name')->get();
        $catalog   = \App\Modules\User\Models\CatalogItem::where('user_id', auth()->id())->where('is_active', true)->orderBy('name')->get();
        $taxRules  = \App\Modules\User\Models\TaxRule::where('user_id', auth()->id())->where('is_active', true)->orderBy('name')->get();
        $prefill   = [
            'vault_client_id' => $request->integer('vault_client_id') ?: null,
            'recipient_email' => $request->query('recipient_email'),
            'inbox_thread_id' => $request->integer('inbox_thread_id') ?: null,
        ];
        return view('user.client_invoices.create', compact('clients', 'emails', 'companies', 'catalog', 'taxRules', 'prefill'));
    }

    public function store(Request $request, ClientInvoiceService $svc)
    {
        $ws = app('current_workspace');
        $data = $request->validate([
            'billing_company_id'        => 'nullable|integer',
            'vault_client_id'           => 'nullable|integer',
            'recipient_email'           => 'nullable|email|max:190',
            'currency'                  => 'nullable|string|size:3',
            'due_date'                  => 'nullable|date',
            'notes_md'                  => 'nullable|string|max:4000',
            'discount_minor'            => 'nullable|integer|min:0',
            'inbox_thread_id'           => 'nullable|integer',
            'line_items'                => 'required|array|min:1',
            'line_items.*.label'        => 'required|string|max:240',
            'line_items.*.amount_minor' => 'required|integer|min:0',
            'line_items.*.quantity'     => 'nullable|integer|min:1|max:9999',
            'line_items.*.tax_rate_bps' => 'nullable|integer|min:0|max:100000',
            'line_items.*.tax_name'     => 'nullable|string|max:64',
            'line_items.*.tax_inclusive'=> 'nullable|boolean',
            'line_items.*.catalog_item_id' => 'nullable|integer',
        ]);
        $invoice = $svc->createStandalone($data, $ws, (int) auth()->id());
        if (($thread = $request->integer('inbox_thread_id')) && \Illuminate\Support\Facades\Schema::hasTable('inbox_thread_conversions')) {
            \Illuminate\Support\Facades\DB::table('inbox_thread_conversions')
                ->where('thread_id', $thread)->update(['invoice_id' => $invoice->id]);
        }
        return redirect()->route('user.client-invoices.edit', $invoice)->with('success', 'Invoice created.');
    }

    /** Owner marks an invoice paid manually (cash/bank transfer/etc). */
    public function markPaid(Request $request, Invoice $invoice, ClientInvoiceService $svc)
    {
        $this->authorizeInvoice($invoice);
        if ($invoice->status === 'paid') return back()->with('error', 'Already paid.');
        $data = $request->validate([
            'method'    => 'nullable|string|max:32',
            'reference' => 'nullable|string|max:190',
        ]);
        $svc->markPaidManual($invoice, $data['method'] ?? 'manual', $data['reference'] ?? null);
        if ($request->boolean('email_receipt')) {
            $svc->emailReceipt($invoice->fresh());
        }
        return back()->with('success', 'Invoice marked as paid.');
    }

    /** Issue a full/partial refund against a paid invoice. */
    public function refund(Request $request, Invoice $invoice, ClientInvoiceService $svc)
    {
        $this->authorizeInvoice($invoice);
        $data = $request->validate([
            'amount_minor' => 'nullable|integer|min:0',
            'reason'       => 'nullable|string|max:240',
        ]);
        $svc->refund($invoice, (int) ($data['amount_minor'] ?? 0), $data['reason'] ?? null, true);
        return back()->with('success', 'Refund issued.');
    }

    /** Owner-side receipt view for a paid invoice. */
    public function receipt(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);
        $receipt = \App\Modules\User\Models\Receipt::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
        return view('user.client_invoices.receipt', compact('invoice', 'receipt'));
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
