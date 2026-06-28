<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\ClientInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class BillingController extends Controller
{
    use ApiResponses;

    public function __construct(protected WorkspaceContext $ctx) {}

    public function subscription(Request $request)
    {
        $sub = Subscription::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->first();
        if (!$sub) return $this->ok(['subscription' => null]);
        return $this->ok(['subscription' => [
            'id'                    => $sub->id,
            'plan_id'               => $sub->plan_id,
            'status'                => $sub->status,
            'billing_cycle'         => $sub->billing_cycle,
            'current_period_start'  => optional($sub->current_period_start)->toIso8601String(),
            'current_period_end'    => optional($sub->current_period_end)->toIso8601String(),
            'cancel_at'             => optional($sub->cancel_at)->toIso8601String(),
            'cancel_at_period_end'  => (bool) $sub->cancel_at_period_end,
            'gateway'               => $sub->gateway,
            'currency'              => $sub->currency,
        ]]);
    }

    public function invoices(Request $request)
    {
        $page = Invoice::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));
        return $this->ok([
            'items' => collect($page->items())->map(fn ($i) => $this->transformInvoice($i))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function showInvoice(Request $request, int $id)
    {
        $invoice = Invoice::where('user_id', $request->user()->id)->find($id);
        if (!$invoice) return $this->notFound('Invoice not found');

        $rawLines = is_array($invoice->line_items) ? $invoice->line_items : [];
        $lines = [];
        foreach ($rawLines as $idx => $li) {
            $qty   = (float) ($li['quantity'] ?? 1);
            $amt   = (int)   ($li['amount_minor'] ?? 0);
            $unit  = $qty > 0 ? (int) round($amt / $qty) : $amt;
            $lines[] = [
                'id'           => (int) ($li['id'] ?? $idx + 1),
                'description'  => (string) ($li['label'] ?? $li['description'] ?? 'Line item'),
                'quantity'     => $qty,
                'unit_minor'   => $unit,
                'amount_minor' => $amt,
            ];
        }

        $pdfUrl = null;
        try {
            $pdfUrl = route('user.invoices.pdf', ['invoice' => $invoice->id]);
        } catch (\Throwable $e) {
            $pdfUrl = null;
        }

        return $this->ok(['invoice' => array_merge($this->transformInvoice($invoice), [
            'lines'   => $lines,
            'pdf_url' => $pdfUrl,
        ])]);
    }

    /**
     * Create a draft client invoice in the active workspace. Mobile screens
     * can then PATCH it to add line items, set a recipient and discount,
     * and POST /send to email the hosted pay link.
     */
    public function storeInvoice(Request $request, ClientInvoiceService $svc)
    {
        $data = $request->validate([
            'currency'                     => 'nullable|string|size:3',
            'recipient_email'              => 'nullable|email|max:190',
            'vault_client_id'              => 'nullable|integer',
            'billing_company_id'           => 'nullable|integer',
            'notes_md'                     => 'nullable|string|max:4000',
            'due_date'                     => 'nullable|date',
            'discount_minor'               => 'nullable|integer|min:0',
            'inbox_thread_id'              => 'nullable|integer',
            'line_items'                   => 'nullable|array',
            'line_items.*.label'           => 'required_with:line_items|string|max:240',
            'line_items.*.amount_minor'    => 'required_with:line_items|integer|min:0',
            'line_items.*.quantity'        => 'nullable|integer|min:1|max:9999',
            'line_items.*.tax_rate_bps'    => 'nullable|integer|min:0|max:100000',
            'line_items.*.tax_name'        => 'nullable|string|max:64',
            'line_items.*.tax_inclusive'   => 'nullable|boolean',
            'line_items.*.catalog_item_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $ws   = $this->ctx->resolve($user);
        if (!$ws) return $this->fail('No active workspace.', 422);

        // Standalone create: when line items are supplied, build a fully
        // costed invoice via the shared calculator (web/API/mobile parity).
        if (!empty($data['line_items'])) {
            $invoice = $svc->createStandalone($data, $ws, (int) $user->id);
            if (($thread = (int) ($data['inbox_thread_id'] ?? 0)) && \Illuminate\Support\Facades\Schema::hasTable('inbox_thread_conversions')) {
                DB::table('inbox_thread_conversions')->where('thread_id', $thread)->update(['invoice_id' => $invoice->id]);
            }
            return $this->created(['invoice' => $this->transformInvoice($invoice)]);
        }

        $invoice = DB::transaction(function () use ($user, $ws, $data) {
            $fy     = \App\Services\InvoiceService::financialYearFor(now());
            $prefix = (string) config('billing.invoice.prefix', 'INV');
            $pad    = (int) config('billing.invoice.pad', 5);

            DB::table('invoice_counters')->insertOrIgnore([
                'financial_year' => $fy,
                'prefix'         => $prefix,
                'last_seq'       => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $row = DB::table('invoice_counters')
                ->where('financial_year', $fy)
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();
            $next = ((int) $row->last_seq) + 1;
            DB::table('invoice_counters')
                ->where('id', $row->id)
                ->update(['last_seq' => $next, 'updated_at' => now()]);

            $number = sprintf('%s/%s/%s', $prefix, $fy, str_pad((string) $next, $pad, '0', STR_PAD_LEFT));

            return Invoice::create([
                'number'                   => $number,
                'financial_year'           => $fy,
                'seq'                      => $next,
                'kind'                     => 'client',
                'workspace_id'             => $ws->id,
                'user_id'                  => $user->id,
                'currency'                 => strtoupper($data['currency'] ?? ($ws->currency ?? 'USD')),
                'subtotal_minor'           => 0,
                'tax_total_minor'          => 0,
                'grand_total_minor'        => 0,
                'discount_minor'           => 0,
                'billing_address_snapshot' => [],
                'merchant_snapshot'        => (array) config('billing.merchant', []),
                'line_items'               => [],
                'tax_breakdown'            => [],
                'status'                   => 'draft',
                'issued_at'                => now(),
                'recipient_email'          => $data['recipient_email'] ?? null,
                'vault_client_id'          => $data['vault_client_id'] ?? null,
                'notes_md'                 => $data['notes_md'] ?? null,
                'due_date'                 => $data['due_date'] ?? null,
            ]);
        });

        return $this->created(['invoice' => $this->transformInvoice($invoice)]);
    }

    public function updateInvoice(Request $request, ClientInvoiceService $svc, int $id)
    {
        $invoice = $this->findClientInvoice($request, $id);
        if (!$invoice) return $this->notFound('Invoice not found');
        if ($invoice->status === 'paid') return $this->fail('Paid invoices cannot be edited.', 422);

        $data = $request->validate([
            'line_items'                  => 'array',
            'line_items.*.label'          => 'required|string|max:240',
            'line_items.*.amount_minor'   => 'required|integer|min:0',
            'line_items.*.quantity'       => 'nullable|integer|min:1|max:9999',
            'discount_minor'              => 'nullable|integer|min:0',
            'tax_total_minor'             => 'nullable|integer|min:0',
            'notes_md'                    => 'nullable|string|max:4000',
            'due_date'                    => 'nullable|date',
            'vault_client_id'             => 'nullable|integer',
            'recipient_email'             => 'nullable|email|max:190',
        ]);

        $invoice->forceFill([
            'discount_minor'  => (int) ($data['discount_minor'] ?? $invoice->discount_minor ?? 0),
            'tax_total_minor' => (int) ($data['tax_total_minor'] ?? $invoice->tax_total_minor ?? 0),
            'notes_md'        => array_key_exists('notes_md', $data) ? $data['notes_md'] : $invoice->notes_md,
            'due_date'        => array_key_exists('due_date', $data) ? $data['due_date'] : $invoice->due_date,
            'vault_client_id' => array_key_exists('vault_client_id', $data) ? $data['vault_client_id'] : $invoice->vault_client_id,
            'recipient_email' => array_key_exists('recipient_email', $data) ? $data['recipient_email'] : $invoice->recipient_email,
        ])->save();

        if (array_key_exists('line_items', $data)) {
            $items = [];
            foreach ((array) $data['line_items'] as $li) {
                $items[] = [
                    'label'        => $li['label'],
                    'amount_minor' => (int) $li['amount_minor'],
                    'quantity'     => (int) ($li['quantity'] ?? 1),
                    'meta'         => ['kind' => 'manual'],
                ];
            }
            $svc->recalculate($invoice, $items);
        }

        return $this->ok(['invoice' => $this->transformInvoice($invoice->refresh())]);
    }

    public function destroyInvoice(Request $request, int $id)
    {
        $invoice = $this->findClientInvoice($request, $id);
        if (!$invoice) return $this->notFound('Invoice not found');
        if ($invoice->status === 'paid') return $this->fail('Paid invoices cannot be deleted.', 422);
        $invoice->delete();
        return $this->noContent();
    }

    public function sendInvoice(Request $request, ClientInvoiceService $svc, int $id)
    {
        $invoice = $this->findClientInvoice($request, $id);
        if (!$invoice) return $this->notFound('Invoice not found');
        if ($invoice->status === 'paid') return $this->fail('Invoice already paid.', 422);

        $data = $request->validate([
            'recipient_email' => 'nullable|email|max:190',
        ]);
        if (!empty($data['recipient_email'])) {
            $invoice->forceFill(['recipient_email' => $data['recipient_email']])->save();
        }
        if (!$invoice->recipient_email) {
            return $this->fail('Pick a recipient email before sending.', 422);
        }

        // Reuse the shared send path (delivers, then stamps sent_at) so a
        // transport failure is surfaced to the caller instead of swallowed.
        $payUrl = URL::signedRoute('client-invoice.pay', ['invoice' => $invoice->id]);
        try {
            $svc->markSent($invoice);
        } catch (\Throwable $e) {
            report($e);
            return $this->fail('Could not send the invoice email. The pay link is still available to share manually.', 502, null, [
                'pay_url' => $payUrl,
            ]);
        }

        return $this->ok([
            'invoice' => $this->transformInvoice($invoice->refresh()),
            'pay_url' => $payUrl,
        ]);
    }

    /** Owner marks an invoice paid manually (cash / bank transfer / etc). */
    public function markPaidInvoice(Request $request, ClientInvoiceService $svc, int $id)
    {
        $invoice = $this->findClientInvoice($request, $id);
        if (!$invoice) return $this->notFound('Invoice not found');
        if ($invoice->status === 'paid') return $this->fail('Invoice already paid.', 422);

        $data = $request->validate([
            'method'        => 'nullable|string|max:32',
            'reference'     => 'nullable|string|max:190',
            'email_receipt' => 'nullable|boolean',
        ]);
        $svc->markPaidManual($invoice, $data['method'] ?? 'manual', $data['reference'] ?? null);
        $receiptEmailed = null;
        if (!empty($data['email_receipt'])) {
            // The payment is already recorded; a receipt-email transport failure
            // must NOT roll that back or 500 the request. Report the outcome in a
            // field so the owner knows to re-send (admin email log Resend).
            try {
                $svc->emailReceipt($invoice->fresh());
                $receiptEmailed = true;
            } catch (\App\Modules\Common\Exceptions\EmailDeliveryException $e) {
                report($e);
                $receiptEmailed = false;
            }
        }
        return $this->ok([
            'invoice'         => $this->transformInvoice($invoice->refresh()),
            'receipt_emailed' => $receiptEmailed,
        ]);
    }

    /** Issue a full or partial refund against a paid invoice. */
    public function refundInvoice(Request $request, ClientInvoiceService $svc, int $id)
    {
        $invoice = $this->findClientInvoice($request, $id);
        if (!$invoice) return $this->notFound('Invoice not found');
        // A partially_refunded invoice can still be refunded down to zero —
        // mirror ClientInvoiceService::refund() (and the web controller) so
        // the partial -> full refund flow can complete over the API too.
        if (!in_array($invoice->status, ['paid', 'partially_refunded'], true)) {
            return $this->fail('Only paid invoices can be refunded.', 422);
        }

        $data = $request->validate([
            'amount_minor' => 'nullable|integer|min:0',
            'reason'       => 'nullable|string|max:240',
        ]);
        $svc->refund($invoice, (int) ($data['amount_minor'] ?? 0), $data['reason'] ?? null, true);
        return $this->ok(['invoice' => $this->transformInvoice($invoice->refresh())]);
    }

    /** Latest receipt for a paid invoice. */
    public function invoiceReceipt(Request $request, int $id)
    {
        $invoice = $this->findClientInvoice($request, $id);
        if (!$invoice) return $this->notFound('Invoice not found');
        $receipt = \App\Modules\User\Models\Receipt::where('invoice_id', $invoice->id)->latest('id')->first();
        if (!$receipt) return $this->notFound('No receipt yet.');
        return $this->ok(['receipt' => [
            'id'         => $receipt->id,
            'number'     => $receipt->number,
            'method'     => $receipt->method,
            'gateway'    => $receipt->gateway,
            'gateway_ref'=> $receipt->gateway_ref,
            'created_at' => optional($receipt->created_at)->toIso8601String(),
            'invoice'    => $this->transformInvoice($invoice),
        ]]);
    }

    protected function findClientInvoice(Request $request, int $id): ?Invoice
    {
        $user = $request->user();
        $ws   = $this->ctx->resolve($user);
        if (!$ws) return null;
        return Invoice::where('id', $id)
            ->where('workspace_id', $ws->id)
            ->where('kind', 'client')
            ->first();
    }

    protected function transformInvoice(Invoice $i): array
    {
        return [
            'id'                => $i->id,
            'number'            => $i->number,
            'status'            => $i->status,
            'currency'          => $i->currency,
            'subtotal_minor'    => (int) ($i->subtotal_minor ?? 0),
            'tax_total_minor'   => (int) ($i->tax_total_minor ?? 0),
            'grand_total_minor' => (int) ($i->grand_total_minor ?? 0),
            'issued_at'         => optional($i->issued_at)->toIso8601String(),
            'paid_at'           => optional($i->paid_at)->toIso8601String(),
            'due_at'            => optional($i->due_date)->toIso8601String(),
            'recipient_email'   => $i->recipient_email,
            'kind'              => $i->kind,
        ];
    }
}
