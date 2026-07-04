<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultClientEmail;
use App\Services\Billing\ClientInvoiceService;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\LetterheadValidator;
use App\Services\Billing\NotImplementedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

        // Persistent "last send attempt failed" signal per row (batched).
        $sendFailedMap = Invoice::sendFailedMap($invoices->items());
        // Signed manual pay links, only for the rows whose last send failed, so
        // the list offers the same retry + share affordance as the edit screen.
        $payUrls = [];
        foreach ($invoices->items() as $inv) {
            if (!empty($sendFailedMap[$inv->id])) {
                $payUrls[$inv->id] = URL::signedRoute('client-invoice.pay', ['invoice' => $inv->id]);
            }
        }

        $totals = Invoice::query()
            ->where('workspace_id', $ws->id)->where('kind', 'client')
            ->selectRaw("status, SUM(grand_total_minor) AS amt, COUNT(*) AS c")
            ->groupBy('status')->get()->keyBy('status');

        return view('user.client_invoices.dashboard', compact('invoices', 'totals', 'status', 'sendFailedMap', 'payUrls'));
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
        $contacts = Contact::query()->where('workspace_id', $ws->id)->orderBy('display_name')->get();
        // Persistent "last send failed" signal + the manual pay link to share.
        $lastSendFailed = $invoice->lastSendFailed();
        // Human-friendly, sanitized reason for the latest failed send (if any).
        $lastSendReason = $lastSendFailed ? $invoice->lastSendFailedReason() : null;
        $payUrl = URL::signedRoute('client-invoice.pay', ['invoice' => $invoice->id]);
        return view('user.client_invoices.edit', compact('invoice', 'clients', 'emails', 'contacts', 'lastSendFailed', 'lastSendReason', 'payUrl'));
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
            'contact_id'              => 'nullable|integer',
            'recipient_email'         => 'nullable|email|max:190',
            'recipient_name'          => 'nullable|string|max:190',
            'recipient_address'       => 'nullable|string|max:2000',
            'letterhead_orientation'  => 'nullable|in:portrait,landscape',
            'remove_letterhead'       => 'nullable|boolean',
        ]);
        $this->validateLetterhead($request, $data['letterhead_orientation'] ?? 'portrait');

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

        $ws = app('current_workspace');
        $contact = !empty($data['contact_id'])
            ? Contact::withoutWorkspaceScope()->where('workspace_id', $ws->id)->find($data['contact_id'])
            : null;

        $invoice->forceFill([
            'discount_minor'    => (int) ($data['discount_minor'] ?? 0),
            'tax_total_minor'   => (int) ($data['tax_total_minor'] ?? 0),
            'notes_md'          => $data['notes_md'] ?? null,
            'due_date'          => $data['due_date'] ?? null,
            'vault_client_id'   => $data['vault_client_id'] ?? null,
            'contact_id'        => $contact?->id,
            'recipient_email'   => $data['recipient_email'] ?? ($contact ? optional($contact->emails()->orderByDesc('is_primary')->first())->value : null),
            'recipient_name'    => $data['recipient_name'] ?? ($contact ? $contact->nameForDisplay() : null),
            'recipient_address' => $data['recipient_address'] ?? ($contact && is_array($contact->manual_profile) ? ($contact->manual_profile['location']['address'] ?? null) : null),
            'letterhead_orientation' => $data['letterhead_orientation'] ?? $invoice->letterhead_orientation,
        ])->save();
        $svc->recalculate($invoice, $items);
        $this->applyLetterhead($invoice, $request, $data['letterhead_orientation'] ?? ($invoice->letterhead_orientation ?: 'portrait'));

        return back()->with('success', 'Invoice saved.');
    }

    public function send(Request $request, Invoice $invoice, ClientInvoiceService $svc)
    {
        $this->authorizeInvoice($invoice);
        if ($invoice->status === 'paid') abort(422);
        if (!$invoice->recipient_email) {
            return back()->with('error', 'Pick a recipient email before sending.');
        }
        // markSent delivers first and only stamps "sent" once delivery succeeds;
        // a genuine transport failure now raises instead of silently marking the
        // invoice sent. Surface it to the owner (with the pay link to share
        // manually) rather than 500ing.
        try {
            $svc->markSent($invoice);
        } catch (\Throwable $e) {
            report($e);
            $payUrl = \Illuminate\Support\Facades\URL::signedRoute('client-invoice.pay', ['invoice' => $invoice->id]);
            return back()
                ->with('error', 'Could not email the invoice — the email failed to send. Share this pay link manually: ' . $payUrl)
                ->with('pay_url', $payUrl);
        }
        return back()->with('success', 'Invoice emailed to ' . $invoice->recipient_email);
    }

    /** Email the client a payment reminder for an unpaid/overdue invoice. */
    public function sendReminder(Request $request, Invoice $invoice, ClientInvoiceService $svc)
    {
        $this->authorizeInvoice($invoice);
        if (!$invoice->recipient_email) {
            return back()->with('error', 'Pick a recipient email before sending a reminder.');
        }
        if (in_array($invoice->status, ['paid', 'refunded', 'partially_refunded'], true)) {
            return back()->with('error', 'This invoice is already settled.');
        }
        if (!$invoice->sent_at) {
            return back()->with('error', 'Send the invoice before reminding about it.');
        }
        $svc->sendReminder($invoice);
        return back()->with('success', 'Payment reminder sent to ' . $invoice->recipient_email);
    }

    /** Standalone invoice creation form (not derived from kanban cards). */
    public function create(Request $request)
    {
        $ws = app('current_workspace');
        $clients   = VaultClient::query()->where('workspace_id', $ws->id)->orderBy('name')->get(['id', 'name']);
        $emails    = VaultClientEmail::query()->where('workspace_id', $ws->id)->get();
        $contacts  = Contact::query()->where('workspace_id', $ws->id)->orderBy('display_name')->get();
        $companies = \App\Modules\User\Models\BillingCompany::where('user_id', auth()->id())->orderByDesc('is_default')->orderBy('name')->get();
        $catalog   = \App\Modules\User\Models\CatalogItem::where('user_id', auth()->id())->where('is_active', true)->orderBy('name')->get();
        $taxRules  = \App\Modules\User\Models\TaxRule::where('user_id', auth()->id())->where('is_active', true)->orderBy('name')->get();
        $prefill   = [
            'vault_client_id' => $request->integer('vault_client_id') ?: null,
            'contact_id'      => $request->integer('contact_id') ?: null,
            'recipient_email' => $request->query('recipient_email'),
            'inbox_thread_id' => $request->integer('inbox_thread_id') ?: null,
        ];
        return view('user.client_invoices.create', compact('clients', 'emails', 'contacts', 'companies', 'catalog', 'taxRules', 'prefill'));
    }

    public function store(Request $request, ClientInvoiceService $svc)
    {
        $ws = app('current_workspace');
        $data = $this->validateStandalone($request);
        $this->validateLetterhead($request, $data['letterhead_orientation'] ?? 'portrait');
        $data = $this->resolveRecipient($data, $ws);
        $invoice = $svc->createStandalone($data, $ws, (int) auth()->id());
        $this->applyLetterhead($invoice, $request, $data['letterhead_orientation'] ?? 'portrait');
        if (($thread = $request->integer('inbox_thread_id')) && \Illuminate\Support\Facades\Schema::hasTable('inbox_thread_conversions')) {
            \Illuminate\Support\Facades\DB::table('inbox_thread_conversions')
                ->where('thread_id', $thread)->update(['invoice_id' => $invoice->id]);
        }
        return redirect()->route('user.client-invoices.edit', $invoice)->with('success', 'Invoice created.');
    }

    /** Standalone receipt creation form (no invoice-first workflow — instantly paid). */
    public function createReceipt(Request $request)
    {
        $ws = app('current_workspace');
        $clients   = VaultClient::query()->where('workspace_id', $ws->id)->orderBy('name')->get(['id', 'name']);
        $contacts  = Contact::query()->where('workspace_id', $ws->id)->orderBy('display_name')->get();
        $companies = \App\Modules\User\Models\BillingCompany::where('user_id', auth()->id())->orderByDesc('is_default')->orderBy('name')->get();
        $catalog   = \App\Modules\User\Models\CatalogItem::where('user_id', auth()->id())->where('is_active', true)->orderBy('name')->get();
        return view('user.client_invoices.create_receipt', compact('clients', 'contacts', 'companies', 'catalog'));
    }

    /** Persist + immediately mark-paid a standalone receipt. */
    public function storeReceipt(Request $request, ClientInvoiceService $svc)
    {
        $ws = app('current_workspace');
        $data = $this->validateStandalone($request);
        $data['method'] = $request->validate(['method' => 'nullable|string|max:32'])['method'] ?? 'manual';
        $data['reference'] = $request->validate(['reference' => 'nullable|string|max:190'])['reference'] ?? null;
        $this->validateLetterhead($request, $data['letterhead_orientation'] ?? 'portrait');
        $data = $this->resolveRecipient($data, $ws);

        if (empty($data['vault_client_id']) && empty($data['contact_id']) && empty($data['recipient_email'])) {
            return back()->withErrors(['recipient' => 'Pick a client, contact, or recipient email for the receipt.'])->withInput();
        }

        $invoice = $svc->createStandaloneReceipt($data, $ws, (int) auth()->id());
        $this->applyLetterhead($invoice, $request, $data['letterhead_orientation'] ?? 'portrait');

        return redirect()->route('user.client-invoices.receipt', $invoice)->with('success', 'Receipt created.');
    }

    protected function validateStandalone(Request $request): array
    {
        return $request->validate([
            'billing_company_id'        => 'nullable|integer',
            'vault_client_id'           => 'nullable|integer',
            'contact_id'                => 'nullable|integer',
            'recipient_email'           => 'nullable|email|max:190',
            'recipient_name'            => 'nullable|string|max:190',
            'recipient_address'         => 'nullable|string|max:2000',
            'currency'                  => 'nullable|string|size:3',
            'due_date'                  => 'nullable|date',
            'notes_md'                  => 'nullable|string|max:4000',
            'discount_minor'            => 'nullable|integer|min:0',
            'inbox_thread_id'           => 'nullable|integer',
            'letterhead_orientation'    => 'nullable|in:portrait,landscape',
            'line_items'                => 'required|array|min:1',
            'line_items.*.label'        => 'required|string|max:240',
            'line_items.*.amount_minor' => 'required|integer|min:0',
            'line_items.*.quantity'     => 'nullable|integer|min:1|max:9999',
            'line_items.*.tax_rate_bps' => 'nullable|integer|min:0|max:100000',
            'line_items.*.tax_name'     => 'nullable|string|max:64',
            'line_items.*.tax_inclusive'=> 'nullable|boolean',
            'line_items.*.catalog_item_id' => 'nullable|integer',
        ]);
    }

    /**
     * Fill recipient_name/email/address from the chosen Contact when not
     * explicitly given. The lookup is explicitly scoped to $ws so a stray
     * contact_id from another workspace can never leak recipient data in.
     */
    protected function resolveRecipient(array $data, $ws): array
    {
        if (empty($data['contact_id'])) {
            return $data;
        }
        $contact = Contact::withoutWorkspaceScope()->where('workspace_id', $ws->id)->find($data['contact_id']);
        if (!$contact) {
            $data['contact_id'] = null;
            return $data;
        }
        $data['recipient_name']  = $data['recipient_name']  ?? $contact->nameForDisplay();
        $data['recipient_email'] = $data['recipient_email'] ?? optional($contact->emails()->orderByDesc('is_primary')->first())->value;
        $data['recipient_address'] = $data['recipient_address'] ?? (is_array($contact->manual_profile) ? ($contact->manual_profile['location']['address'] ?? null) : null);
        return $data;
    }

    /** Validate the optional per-invoice letterhead override upload. */
    protected function validateLetterhead(Request $request, string $orientation): void
    {
        $request->validate([
            'letterhead' => LetterheadValidator::rules(),
        ]);
        if ($request->hasFile('letterhead')) {
            $error = LetterheadValidator::validateDimensions($request->file('letterhead'), $orientation);
            if ($error) {
                throw \Illuminate\Validation\ValidationException::withMessages(['letterhead' => $error]);
            }
        }
    }

    /** Persist (or clear) the per-invoice letterhead override on the public disk. */
    protected function applyLetterhead(Invoice $invoice, Request $request, string $orientation): void
    {
        if ($request->boolean('remove_letterhead') && $invoice->letterhead_path) {
            $this->deleteLetterheadFile($invoice->letterhead_path);
            $invoice->forceFill(['letterhead_path' => null, 'letterhead_width' => null, 'letterhead_height' => null])->save();
            return;
        }

        if ($request->hasFile('letterhead')) {
            $old = $invoice->letterhead_path;
            $file = $request->file('letterhead');
            $dims = LetterheadValidator::dimensions($file);
            $invoice->forceFill([
                'letterhead_path'        => $file->store('billing/letterheads', 'public'),
                'letterhead_orientation' => $orientation,
                'letterhead_width'       => $dims['width'] ?? null,
                'letterhead_height'      => $dims['height'] ?? null,
            ])->save();
            if ($old) {
                $this->deleteLetterheadFile($old);
            }
        }
    }

    private function deleteLetterheadFile(string $path): void
    {
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            // ignore — the row no longer references it
        }
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
            // The payment is already recorded; a receipt-email transport failure
            // must NOT roll that back. Surface it to the owner (who can re-send
            // from the admin email log) instead of silently claiming delivery.
            try {
                $svc->emailReceipt($invoice->fresh());
            } catch (\App\Modules\Common\Exceptions\EmailDeliveryException $e) {
                report($e);
                return back()
                    ->with('success', 'Invoice marked as paid.')
                    ->with('error', 'The receipt email could not be sent. You can re-send it from the email log.');
            }
        }
        return back()->with('success', 'Invoice marked as paid.');
    }

    /** Issue a full/partial refund against a paid invoice. */
    public function refund(Request $request, Invoice $invoice, ClientInvoiceService $svc)
    {
        $this->authorizeInvoice($invoice);
        $data = $request->validate([
            'amount_minor'    => 'nullable|integer|min:0',
            'reason'          => 'nullable|string|max:240',
            'idempotency_key' => 'nullable|string|max:80',
        ]);
        // Accept an idempotency key from a hidden form field or the standard
        // Idempotency-Key header; the service also has a short dedupe window so
        // a plain double-submit without a key is still a no-op.
        $idem = $data['idempotency_key'] ?? $request->header('Idempotency-Key');
        $svc->refund($invoice, (int) ($data['amount_minor'] ?? 0), $data['reason'] ?? null, true, $idem);
        return back()->with('success', 'Refund issued.');
    }

    /** Owner-side receipt view for a paid invoice. */
    public function receipt(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);
        $receipt = \App\Modules\User\Models\Receipt::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
        return view('user.client_invoices.receipt', compact('invoice', 'receipt'));
    }

    /**
     * Signed, downloadable PDF of the invoice. The signed-URL HMAC is the
     * only authorization (no session needed) so the in-app button, the
     * REST/mobile clients and emailed links can all share one route.
     */
    public function pdf(Request $request, Invoice $invoice, \App\Services\Billing\ClientInvoicePdfRenderer $renderer)
    {
        if (!$request->hasValidSignature()) abort(401, 'Download link expired.');
        if ($invoice->kind !== 'client') abort(404);

        $out = $renderer->renderInvoice($invoice);
        return response($out['body'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $out['filename'] . '"',
            'Content-Length'      => (string) strlen($out['body']),
            'Cache-Control'       => 'private, max-age=0, no-store',
        ]);
    }

    /** Signed, downloadable PDF of the latest receipt for a paid invoice. */
    public function receiptPdf(Request $request, Invoice $invoice, \App\Services\Billing\ClientInvoicePdfRenderer $renderer)
    {
        if (!$request->hasValidSignature()) abort(401, 'Download link expired.');
        if ($invoice->kind !== 'client') abort(404);

        $receipt = \App\Modules\User\Models\Receipt::where('invoice_id', $invoice->id)->latest('id')->first();
        if (!$receipt) abort(404, 'No receipt for this invoice yet.');

        $out = $renderer->renderReceipt($invoice, $receipt);
        return response($out['body'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $out['filename'] . '"',
            'Content-Length'      => (string) strlen($out['body']),
            'Cache-Control'       => 'private, max-age=0, no-store',
        ]);
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
