<?php

namespace App\Services\Billing;

use App\Modules\User\Models\CreditNote;
use App\Modules\User\Models\Refund;
use App\Services\InvoiceService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;

/**
 * Credit-note numbering mirrors invoice numbering (same FY + row-locked
 * counter table), but with prefix "CN" so CNs never collide with
 * invoice numbers. Sequence is per-FY, per-prefix, strictly serial.
 *
 * A credit note is an immutable record that a refund happened — so we
 * snapshot the invoice number, amount, and reason into `snapshot` at
 * issue time and never mutate them.
 */
class CreditNoteService
{
    public static function issue(Refund $refund): CreditNote
    {
        $fy     = InvoiceService::financialYearFor(now());
        $prefix = (string) config('billing.credit_note.prefix', 'CN');
        $pad    = (int) config('billing.invoice.pad', 5);

        return DB::transaction(function () use ($refund, $fy, $prefix, $pad) {
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
            $invoice = $refund->invoice;

            return CreditNote::create([
                'number'         => $number,
                'financial_year' => $fy,
                'seq'            => $next,
                'refund_id'      => $refund->id,
                'invoice_id'     => $invoice->id,
                'user_id'        => $refund->user_id,
                'currency'       => $refund->currency,
                'amount_minor'   => $refund->amount_minor,
                'snapshot'       => [
                    'invoice_number' => $invoice->number,
                    'reason'         => $refund->reason,
                    'billing_address' => $invoice->billing_address_snapshot,
                    'merchant'       => $invoice->merchant_snapshot,
                ],
                'issued_at'      => now(),
            ]);
        });
    }

    public static function renderPdf(CreditNote $cn): string
    {
        $html = view('user.credit_notes.pdf', [
            'credit_note' => $cn,
            'invoice'     => $cn->invoice,
            'merchant'    => $cn->snapshot['merchant'] ?? config('billing.merchant'),
            'address'     => $cn->snapshot['billing_address'] ?? [],
        ])->render();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return (string) $dompdf->output();
    }
}
