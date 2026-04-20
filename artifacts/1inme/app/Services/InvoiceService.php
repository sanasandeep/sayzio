<?php

namespace App\Services;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;

/**
 * Issue tax invoices and render them as PDFs.
 *
 * Numbering scheme: <prefix>/<FY>/<padded-seq>
 *   e.g. INV/2025-26/00001
 *
 * `seq` is reserved by row-locking the matching `invoice_counters`
 * row inside a transaction. That guarantees no duplicates even under
 * concurrent issue requests, AND no gaps inside a financial year
 * (the counter is incremented strictly serially before the invoice
 * row is written).
 *
 * Address + line items + tax breakdown + merchant data are JSON
 * SNAPSHOTS — later edits to user profile or admin settings never
 * mutate past invoices.
 */
class InvoiceService
{
    /**
     * Issue an invoice for `$user` with the given calculator output.
     * `$calc` should be the array returned by TaxCalculator::calculate().
     */
    public static function issue(User $user, array $calc, array $billingAddress): Invoice
    {
        $fy     = self::financialYearFor(now());
        $prefix = (string) config('billing.invoice.prefix', 'INV');
        $pad    = (int) config('billing.invoice.pad', 5);

        return DB::transaction(function () use ($user, $calc, $billingAddress, $fy, $prefix, $pad) {
            // Reserve the next sequence with a row lock. The
            // updateOrCreate-then-lockForUpdate pattern matters: if no
            // counter row exists yet for this (FY, prefix), create it
            // with seq=0 first, then lock and bump.
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
                'user_id'                  => $user->id,
                'currency'                 => $calc['currency'],
                'subtotal_minor'           => $calc['subtotal_minor'],
                'tax_total_minor'          => $calc['tax_total_minor'],
                'grand_total_minor'        => $calc['grand_total_minor'],
                'billing_address_snapshot' => $billingAddress,
                'merchant_snapshot'        => config('billing.merchant'),
                'line_items'               => $calc['line_items'],
                'tax_breakdown'            => $calc['tax_breakdown'],
                'reverse_charge_note'      => $calc['reverse_charge_note'],
                'place_of_supply'          => $calc['place_of_supply'],
                'issued_at'                => now(),
            ]);
        });
    }

    /**
     * Indian-style FY label (Apr–Mar): "2025-26".
     * Calendar-year jurisdictions can set FY_START_MONTH=1 → "2025-25".
     */
    public static function financialYearFor(\DateTimeInterface $when): string
    {
        $startMonth = (int) config('billing.financial_year.start_month', 4);
        $year = (int) $when->format('Y');
        $month = (int) $when->format('n');
        $fyStart = $month >= $startMonth ? $year : $year - 1;
        $fyEnd = ($fyStart + 1) % 100;
        return sprintf('%d-%02d', $fyStart, $fyEnd);
    }

    /** Render an invoice as a PDF binary string. */
    public static function renderPdf(Invoice $invoice): string
    {
        $html = view('user.invoices.pdf', [
            'invoice'  => $invoice,
            'merchant' => $invoice->merchant_snapshot ?? config('billing.merchant'),
            'address'  => $invoice->billing_address_snapshot ?? [],
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
