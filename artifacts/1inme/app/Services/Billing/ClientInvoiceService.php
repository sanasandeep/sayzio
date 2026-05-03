<?php

namespace App\Services\Billing;

use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskTimeEntry;
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
    public function markPaid(Invoice $invoice, string $gateway, ?string $gatewayRef = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $gateway, $gatewayRef) {
            /** @var Invoice $fresh */
            $fresh = Invoice::query()->withoutGlobalScopes()
                ->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status === 'paid' && $fresh->paid_at) {
                return $fresh;
            }

            $fresh->forceFill([
                'status'  => 'paid',
                'gateway' => $gateway,
                'paid_at' => now(),
            ])->save();

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

    /** Same numbering scheme as subscription invoices, but kind=client. */
    protected function reserveDraftInvoice(Workspace $ws, int $userId, string $currency): Invoice
    {
        $fy     = InvoiceService::financialYearFor(now());
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
            'user_id'                  => $userId,
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
