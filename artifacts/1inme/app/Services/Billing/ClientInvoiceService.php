<?php

namespace App\Services\Billing;

use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Receipt;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\TaxRule;
use App\Modules\User\Models\Workspace;
use App\Services\InvoiceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds & maintains "client invoices" derived from kanban cards.
 *
 * A client invoice is a regular row in the `invoices` table but with
 * `kind = 'client'` (sibling to subscription invoices). Each card on
 * the invoice contributes one line item:
 *   - hourly cards roll up the un-invoiced ended TimeEntries into
 *     hours × rate (rounded UP to the nearest whole minute -> hours).
 *   - flat-fee cards become a single fixed line.
 *
 * Numbering reuses InvoiceService's reservation pattern (sequential,
 * gap-free per financial year) so client invoice numbers and
 * subscription invoice numbers come from the same counter — admins
 * looking at the books see one continuous sequence.
 */
class ClientInvoiceService
{
    /** Build (or refresh) a draft client invoice for a set of card ids. */
    public function draftFromCards(array $cardIds, Workspace $workspace, int $userId): Invoice
    {
        $cards = TaskCard::query()
            ->whereIn('id', $cardIds)
            ->where('workspace_id', $workspace->id)
            ->where('billable', true)
            ->whereNull('client_invoice_id')
            ->get();

        if ($cards->isEmpty()) {
            abort(422, 'No billable, un-invoiced cards selected.');
        }

        $currency = (string) ($workspace->currency ?? config('billing.merchant.currency', 'USD'));
        $items = $this->buildLineItems($cards);

        return DB::transaction(function () use ($cards, $userId, $workspace, $currency, $items) {
            $invoice = $this->reserveDraftInvoice($workspace, $userId, $currency);
            $invoice->forceFill([
                'line_items'        => $items,
                'subtotal_minor'    => $this->subtotal($items),
                'tax_total_minor'   => 0,
                'tax_breakdown'     => [],
                'discount_minor'    => 0,
                'grand_total_minor' => $this->subtotal($items),
            ])->save();

            $invoice->sourceCards()->sync($cards->pluck('id')->all());
            return $invoice;
        });
    }

    /** Replace line items + recompute totals from already-stored discount. */
    public function recalculate(Invoice $invoice, ?array $items = null): Invoice
    {
        $items = $items ?? (is_array($invoice->line_items) ? $invoice->line_items : []);
        $subtotal = $this->subtotal($items);
        $discount = max(0, min((int) $invoice->discount_minor, $subtotal));
        $tax = (int) ($invoice->tax_total_minor ?? 0);
        $invoice->forceFill([
            'line_items'        => $items,
            'subtotal_minor'    => $subtotal,
            'discount_minor'    => $discount,
            'grand_total_minor' => max(0, $subtotal - $discount + $tax),
        ])->save();
        return $invoice;
    }

    /**
     * Mark a client invoice paid and synchronise the originating cards:
     *  - flip card.client_invoice_id (drives the "Paid" badge in UI)
     *  - move card to the board's billed_column_id (when set)
     *  - log a card activity entry per moved card
     *  - post a workspace feed event
     *
     * Idempotent: re-entrant deliveries return immediately.
     */
    public function markPaid(Invoice $invoice, string $gateway, ?string $gatewayRef = null, bool $manual = false): Invoice
    {
        return DB::transaction(function () use ($invoice, $gateway, $gatewayRef, $manual) {
            /** @var Invoice $fresh */
            $fresh = Invoice::query()->withoutGlobalScopes()
                ->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status === 'paid' && $fresh->paid_at) {
                return $fresh;
            }

            // A manual payment can carry a specific method label (bank_transfer,
            // cash, card, other) in $gateway while the category stays "manual".
            $isManual = $manual || $gateway === 'manual';

            $fresh->forceFill([
                'status'            => 'paid',
                'gateway'           => $gateway,
                'paid_at'           => now(),
                'amount_paid_minor' => (int) $fresh->grand_total_minor,
                'paid_method'       => $isManual ? 'manual' : 'online',
            ])->save();

            $this->generateReceipt($fresh, $isManual ? 'manual' : 'online', $gateway, $gatewayRef);

            $cards = TaskCard::query()->withoutGlobalScopes()
                ->whereIn('id', DB::table('client_invoice_cards')->where('invoice_id', $fresh->id)->pluck('card_id'))
                ->get();

            foreach ($cards as $card) {
                $payload = ['client_invoice_id' => $fresh->id];
                if ($card->board && $card->board->billed_column_id
                    && (int) $card->column_id !== (int) $card->board->billed_column_id) {
                    $payload['column_id'] = (int) $card->board->billed_column_id;
                    $payload['position']  = (int) (TaskCard::query()->withoutGlobalScopes()
                        ->where('column_id', $card->board->billed_column_id)->max('position') ?? 0) + 1;
                }
                $card->forceFill($payload)->save();
                TaskActivity::query()->forceCreate([
                    'workspace_id' => $card->workspace_id,
                    'card_id'      => $card->id,
                    'user_id'      => null,
                    'type'         => 'invoice_paid',
                    'data'         => ['invoice_number' => $fresh->number, 'gateway' => $gateway],
                    'created_at'   => now(),
                ]);
            }

            // Mark every TimeEntry that fed this invoice so re-billing
            // the same card later doesn't double-charge minutes.
            TaskTimeEntry::query()->withoutGlobalScopes()
                ->whereIn('card_id', $cards->pluck('id'))
                ->whereNull('client_invoice_id')
                ->update(['client_invoice_id' => $fresh->id]);

            FeedEvent::create([
                'user_id'     => $fresh->user_id,
                'type'        => 'client_invoice.paid',
                'subject_id'  => $fresh->id,
                'subject_type'=> Invoice::class,
                'data'        => [
                    'number'   => $fresh->number,
                    'amount'   => $fresh->grand_total_minor,
                    'currency' => $fresh->currency,
                    'gateway'  => $gateway,
                ],
                'occurred_at' => now(),
                'visibility'  => 'registered',
            ]);

            return $fresh;
        });
    }

    /**
     * Create a standalone client invoice (not derived from kanban cards):
     * pick a billing company + client + free-form/catalog line items + tax
     * + discount + due date + notes. Numbering reuses the shared counter.
     */
    public function createStandalone(array $data, Workspace $ws, int $userId): Invoice
    {
        $company = isset($data['billing_company_id'])
            ? BillingCompany::query()->where('user_id', $userId)->find($data['billing_company_id'])
            : null;

        $currency = strtoupper((string) ($data['currency']
            ?? $company?->default_currency
            ?? $ws->currency
            ?? config('billing.merchant.currency', 'USD')));

        return DB::transaction(function () use ($data, $ws, $userId, $currency, $company) {
            $invoice = $this->reserveDraftInvoice($ws, $userId, $currency, $company);
            $invoice->forceFill(array_filter([
                'billing_company_id' => $company?->id,
                'vault_client_id'    => $data['vault_client_id'] ?? null,
                'contact_id'         => $data['contact_id'] ?? null,
                'recipient_email'    => $data['recipient_email'] ?? null,
                'recipient_name'     => $data['recipient_name'] ?? null,
                'recipient_address'  => $data['recipient_address'] ?? null,
                'due_date'           => $data['due_date'] ?? null,
                'notes_md'           => $data['notes_md'] ?? null,
                'inbox_thread_id'    => $data['inbox_thread_id'] ?? null,
            ], fn ($v) => $v !== null));
            if ($company) {
                $invoice->merchant_snapshot = $company->toSnapshot();
            }
            $invoice->save();

            $this->applyEdits($invoice, $data, $company);
            return $invoice->fresh();
        });
    }

    /**
     * Recompute line items + tax + discount via the shared calculator and
     * persist totals. `$data['line_items']` each may carry tax_rate_bps /
     * tax_inclusive / tax_name or a catalog_item_id; otherwise the company
     * default tax rule applies.
     */
    public function applyEdits(Invoice $invoice, array $data, ?BillingCompany $company = null): Invoice
    {
        $company = $company
            ?: ($invoice->billing_company_id ? BillingCompany::find($invoice->billing_company_id) : null);

        $fallbackRule = $company?->default_tax_rule_id
            ? TaxRule::find($company->default_tax_rule_id)
            : null;

        $items = is_array($data['line_items'] ?? null)
            ? array_values($data['line_items'])
            : (is_array($invoice->line_items) ? $invoice->line_items : []);

        $discount = (int) ($data['discount_minor'] ?? $invoice->discount_minor ?? 0);

        $calc = app(InvoiceCalculator::class)->compute($items, $discount, $fallbackRule);

        $invoice->forceFill([
            'line_items'        => $calc['line_items'],
            'subtotal_minor'    => $calc['subtotal_minor'],
            'discount_minor'    => $calc['discount_minor'],
            'tax_total_minor'   => $calc['tax_total_minor'],
            'tax_breakdown'     => $calc['tax_breakdown'],
            'grand_total_minor' => $calc['grand_total_minor'],
        ])->save();

        return $invoice;
    }

    /**
     * Owner-initiated "mark as paid": records method/date manually and
     * generates a receipt. Idempotent on already-paid invoices.
     */
    public function markPaidManual(Invoice $invoice, ?string $method = 'manual', ?string $reference = null): Invoice
    {
        // markPaid() records amount_paid_minor + paid_method=manual and issues
        // the receipt; the specific $method (bank_transfer/cash/card/other) is
        // preserved as the gateway label, and $reference as the gateway ref.
        return $this->markPaid($invoice, $method ?: 'manual', $reference, true);
    }

    /**
     * Create a standalone receipt (no separate invoice-first workflow needed)
     * against a Contact/lead or a VaultClient. Builds a fully-paid client
     * invoice from the given line items and immediately generates its
     * receipt, reusing the same numbering + PDF pipeline as a normal
     * invoice payment — so a creator who was paid off-platform (cash,
     * bank transfer, etc.) can hand over a proper receipt without first
     * drafting and sending an invoice.
     */
    public function createStandaloneReceipt(array $data, Workspace $ws, int $userId): Invoice
    {
        $invoice = $this->createStandalone($data, $ws, $userId);
        return $this->markPaidManual(
            $invoice,
            $data['method'] ?? 'manual',
            $data['reference'] ?? null
        );
    }

    /**
     * Stamp an invoice as sent and email a hosted pay link. Centralizes the
     * web controller's send flow so the API + recurring auto-send reuse it.
     */
    public function markSent(Invoice $invoice): Invoice
    {
        if (!$invoice->recipient_email) {
            abort(422, 'Pick a recipient email before sending.');
        }

        // Deliver first so a transport failure surfaces to the caller (the
        // sent_at stamp is only written once delivery succeeds). The central
        // Emailer otherwise swallows transport failures (logs a `failed` row and
        // returns), so opt in to throw_on_failure: a genuine SMTP failure now
        // raises EmailDeliveryException instead of letting us stamp "sent" for
        // an email the client never received.
        $payUrl = \Illuminate\Support\Facades\URL::signedRoute('client-invoice.pay', ['invoice' => $invoice->id]);
        \App\Modules\Common\Services\Emailer::send('billing.client_invoice', $invoice->recipient_email, [
            'invoice_number' => $invoice->number,
            'pay_url'        => $payUrl,
        ], array_merge([
            'user'             => $invoice->user_id,
            'related'          => $invoice,
            'view_data'        => ['invoice' => $invoice, 'payUrl' => $payUrl],
            'throw_on_failure' => true,
        ], $this->companyEmailOpts($invoice, 'billing.client_invoice')));

        $invoice->forceFill([
            'status'  => $invoice->status === 'draft' ? 'sent' : $invoice->status,
            'sent_at' => now(),
        ])->save();

        return $invoice;
    }

    /**
     * Email a payment reminder for an unpaid/overdue invoice, nudging the
     * client with the hosted pay link. Routes through the issuing company's
     * SMTP + per-company template override exactly like the invoice/receipt
     * sends. The invoice must already be sent (not a draft) and not settled.
     */
    public function sendReminder(Invoice $invoice): Invoice
    {
        if (!$invoice->recipient_email) {
            abort(422, 'Pick a recipient email before sending a reminder.');
        }
        if (in_array($invoice->status, ['paid', 'refunded', 'partially_refunded'], true)) {
            abort(422, 'This invoice is already settled.');
        }
        if (!$invoice->sent_at) {
            abort(422, 'Send the invoice before reminding about it.');
        }

        $payUrl = \Illuminate\Support\Facades\URL::signedRoute('client-invoice.pay', ['invoice' => $invoice->id]);
        \App\Modules\Common\Services\Emailer::send('billing.payment_reminder', $invoice->recipient_email, [
            'invoice_number' => $invoice->number,
            'amount'         => number_format((int) $invoice->grand_total_minor / 100, 2),
            'currency'       => strtoupper((string) $invoice->currency),
            'due_date'       => $invoice->due_date ? $invoice->due_date->format('M j, Y') : '—',
            'pay_url'        => $payUrl,
        ], array_merge([
            'user'      => $invoice->user_id,
            'related'   => $invoice,
        ], $this->companyEmailOpts($invoice, 'billing.payment_reminder')));

        return $invoice;
    }

    /**
     * Email a receipt PDF/link to the invoice recipient (after payment).
     *
     * Opts in to throw_on_failure: the central Emailer otherwise swallows a
     * transport failure (logs a `failed` email_logs row and returns), which
     * would let a caller believe the receipt was delivered when it never left
     * the building. Because this runs AFTER the payment is already recorded,
     * the throw does NOT roll anything back — the payment and receipt row stay
     * intact; callers catch EmailDeliveryException to surface the failure (and
     * the owner can re-send from the admin email log's per-row Resend).
     */
    public function emailReceipt(Invoice $invoice): ?Receipt
    {
        $receipt = Receipt::where('invoice_id', $invoice->id)->latest('id')->first();
        if (!$receipt || !$invoice->recipient_email) return $receipt;

        \App\Modules\Common\Services\Emailer::send('billing.receipt', $invoice->recipient_email, [
            'receipt_number' => $receipt->number,
            'invoice_number' => $invoice->number,
            'amount'         => number_format($receipt->amount_minor / 100, 2),
            'currency'       => $receipt->currency,
        ], array_merge([
            'user'             => $invoice->user_id,
            'related'          => $invoice,
            'view_data'        => ['invoice' => $invoice, 'receipt' => $receipt],
            'throw_on_failure' => true,
        ], $this->companyEmailOpts($invoice, 'billing.receipt')));

        return $receipt;
    }

    /**
     * Build the per-company Emailer options for a client-facing accounting
     * email: deliver through the issuing BillingCompany's own SMTP (with its
     * "from") when it has one enabled, and apply the creator's per-company
     * subject/body override for the template. Falls back to the platform
     * MailSettings transport + admin/registry template when the company has no
     * SMTP / no override — so nothing changes for companies that don't opt in,
     * and platform/global emails are untouched.
     *
     * @return array<string,mixed>
     */
    private function companyEmailOpts(Invoice $invoice, string $key): array
    {
        $companyId = $invoice->billing_company_id ?? null;
        if (!$companyId) {
            return [];
        }

        $company = \App\Modules\User\Models\BillingCompany::find($companyId);
        if (!$company) {
            return [];
        }

        // Transport: company SMTP when configured, otherwise platform default
        // (emailOpts() returns a 'system' transport label in that case).
        $opts = \App\Services\Billing\CompanyMailSettings::for($company)->emailOpts();

        // Template: the creator's per-company override (wins over admin/global).
        $override = \App\Services\Billing\CompanyEmailTemplateSettings::get($company->id, $key);
        if ($override && !empty($override['body'])) {
            $opts['template_override'] = $override;
        }

        return $opts;
    }

    /**
     * Generate (once) a receipt for a paid invoice. Returns the existing
     * receipt on re-entry so online + manual paths can't double-issue.
     */
    public function generateReceipt(Invoice $invoice, string $methodKind, string $gateway, ?string $ref = null): Receipt
    {
        $existing = Receipt::where('invoice_id', $invoice->id)->first();
        if ($existing) return $existing;

        return DB::transaction(function () use ($invoice, $methodKind, $gateway, $ref) {
            $fy     = InvoiceService::financialYearFor(now());
            $prefix = (string) config('billing.receipt.prefix', 'RCP');
            $pad    = (int) config('billing.invoice.pad', 5);

            DB::table('invoice_counters')->insertOrIgnore([
                'financial_year' => $fy, 'prefix' => $prefix, 'last_seq' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('invoice_counters')
                ->where('financial_year', $fy)->where('prefix', $prefix)
                ->lockForUpdate()->first();
            $next = ((int) $row->last_seq) + 1;
            DB::table('invoice_counters')->where('id', $row->id)
                ->update(['last_seq' => $next, 'updated_at' => now()]);

            $number = sprintf('%s/%s/%s', $prefix, $fy, str_pad((string) $next, $pad, '0', STR_PAD_LEFT));

            return Receipt::create([
                'number'             => $number,
                'financial_year'     => $fy,
                'seq'                => $next,
                'invoice_id'         => $invoice->id,
                'user_id'            => $invoice->user_id,
                'billing_company_id' => $invoice->billing_company_id,
                'currency'           => $invoice->currency,
                'amount_minor'       => (int) $invoice->grand_total_minor,
                'method'             => $methodKind === 'manual' ? 'manual' : 'online',
                'gateway'            => $gateway,
                'gateway_ref'        => $ref,
                'paid_at'            => $invoice->paid_at ?: now(),
                'snapshot'           => [
                    'invoice_number' => $invoice->number,
                    'line_items'     => $invoice->line_items,
                    'merchant'       => $invoice->merchant_snapshot,
                    'recipient'      => $invoice->recipient_email,
                ],
                'issued_at'          => now(),
            ]);
        });
    }

    /**
     * Issue a full/partial refund against a paid invoice. Records a Refund
     * + CreditNote (the reversing ledger entry) and downgrades the invoice
     * status to refunded / partially_refunded.
     *
     * Idempotency: a double-click, impatient retry, or webhook re-delivery must
     * NOT create two Refund rows + two credit notes for the same intended
     * refund. Two guards prevent that:
     *   1. An explicit `$idempotencyKey` (when the caller supplies one) is
     *      unique per invoice — a repeat with the same key returns the original
     *      Refund untouched (backed by the UNIQUE (invoice_id, idempotency_key)
     *      index as a race backstop).
     *   2. When no key is supplied (e.g. a plain web double-submit), a short
     *      dedupe window collapses an identical succeeded refund (same amount +
     *      reason) created within the last few seconds into a no-op.
     * Both run under a row lock on the invoice so concurrent attempts serialize
     * and see each other's writes rather than both passing the balance check.
     */
    public function refund(Invoice $invoice, int $amountMinor, ?string $reason = null, bool $userInitiated = true, ?string $idempotencyKey = null): Refund
    {
        if ($invoice->status !== 'paid' && $invoice->status !== 'partially_refunded') {
            abort(422, 'Only paid invoices can be refunded.');
        }

        $idempotencyKey = ($idempotencyKey !== null && $idempotencyKey !== '') ? $idempotencyKey : null;

        return DB::transaction(function () use ($invoice, $amountMinor, $reason, $userInitiated, $idempotencyKey) {
            // Serialize concurrent refunds for this invoice (double-click,
            // retry, webhook re-delivery) so duplicates see each other's
            // committed writes instead of both passing the balance check.
            /** @var Invoice $fresh */
            $fresh = Invoice::query()->withoutGlobalScopes()
                ->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            // Guard 1: explicit idempotency key -> return the original refund.
            if ($idempotencyKey !== null) {
                $prior = Refund::where('invoice_id', $fresh->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($prior) return $prior;
            }

            $alreadyRefunded = (int) $fresh->refunds()->where('status', 'succeeded')->sum('amount_minor');
            $remaining = max(0, (int) $fresh->grand_total_minor - $alreadyRefunded);
            $amountMinor = (int) $amountMinor;
            if ($amountMinor <= 0) $amountMinor = $remaining;
            if ($amountMinor > $remaining) {
                abort(422, 'Refund exceeds the refundable balance.');
            }

            // Guard 2: no explicit key -> short dedupe window on an identical
            // succeeded refund (same amount + reason + initiator).
            if ($idempotencyKey === null) {
                $window = (int) config('billing.refund.dedupe_seconds', 10);
                if ($window > 0) {
                    $dupe = Refund::where('invoice_id', $fresh->id)
                        ->where('status', 'succeeded')
                        ->where('amount_minor', $amountMinor)
                        ->where('user_initiated', $userInitiated)
                        ->when(
                            $reason === null,
                            fn ($q) => $q->whereNull('reason'),
                            fn ($q) => $q->where('reason', $reason)
                        )
                        ->where('created_at', '>=', now()->subSeconds($window))
                        ->latest('id')
                        ->first();
                    if ($dupe) return $dupe;
                }
            }

            $refund = Refund::create([
                'invoice_id'      => $fresh->id,
                'user_id'         => $fresh->user_id,
                'amount_minor'    => $amountMinor,
                'currency'        => $fresh->currency,
                'status'          => 'succeeded',
                'gateway'         => $fresh->gateway ?: 'manual',
                'gateway_ref'     => 'refund_' . \Illuminate\Support\Str::random(12),
                'reason'          => $reason,
                'user_initiated'  => $userInitiated,
                'idempotency_key' => $idempotencyKey,
                'processed_at'    => now(),
            ]);

            // Reversing ledger entry. Numbering goes through CreditNoteService
            // so it shares the row-locked, gap-free per-FY counter (prefix "CN")
            // with the subscription refund path — instead of the old id-keyed
            // 'CN/<fy>/<refund_id>' scheme, which produced gaps (refund ids are
            // global, not per-FY), emitted unpadded numbers that diverged from
            // invoices/receipts, and could collide against the unique
            // credit_notes.number column once a refund id reached the counter's
            // pad width.
            CreditNoteService::issue($refund);

            $totalRefunded = $alreadyRefunded + $amountMinor;
            $fresh->forceFill([
                'status'            => $totalRefunded >= (int) $fresh->grand_total_minor ? 'refunded' : 'partially_refunded',
                'amount_paid_minor' => max(0, (int) $fresh->grand_total_minor - $totalRefunded),
            ])->save();

            return $refund;
        });
    }

    /** Same numbering scheme as subscription invoices, but kind=client. */
    protected function reserveDraftInvoice(Workspace $ws, int $userId, string $currency, ?BillingCompany $company = null): Invoice
    {
        $fy     = InvoiceService::financialYearFor(now());
        $prefix = $company && $company->invoice_prefix
            ? (string) $company->invoice_prefix
            : (string) config('billing.invoice.prefix', 'INV');
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
            'user_id'                  => $userId,
            'billing_company_id'       => $company?->id,
            'currency'                 => $currency,
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
        ]);
    }

    /**
     * Build invoice line items from billable cards.
     * Hourly: round logged minutes UP per card to the nearest minute,
     * convert to hours with two decimals × rate. Flat: single rate line.
     */
    protected function buildLineItems(Collection $cards): array
    {
        $items = [];
        foreach ($cards as $card) {
            $rate = (int) ($card->rate_amount_minor ?? 0);
            if ($card->rate_type === 'hourly') {
                $minutes = (int) $card->timeEntries()
                    ->whereNull('client_invoice_id')
                    ->whereNotNull('ended_at')
                    ->sum('minutes');
                if ($minutes <= 0) continue;
                $hours = round($minutes / 60, 2);
                $line  = (int) round($hours * $rate);
                $items[] = [
                    'label'        => $card->title,
                    'amount_minor' => $line,
                    'quantity'     => 1,
                    'meta'         => [
                        'kind'          => 'card_hourly',
                        'card_id'       => $card->id,
                        'minutes'       => $minutes,
                        'hours'         => $hours,
                        'rate_minor'    => $rate,
                    ],
                ];
            } else {
                $items[] = [
                    'label'        => $card->title,
                    'amount_minor' => $rate,
                    'quantity'     => 1,
                    'meta'         => [
                        'kind'       => 'card_flat',
                        'card_id'    => $card->id,
                        'rate_minor' => $rate,
                    ],
                ];
            }
        }
        return $items;
    }

    protected function subtotal(array $items): int
    {
        $sum = 0;
        foreach ($items as $i) {
            $sum += (int) ($i['amount_minor'] ?? 0) * (int) ($i['quantity'] ?? 1);
        }
        return $sum;
    }
}
