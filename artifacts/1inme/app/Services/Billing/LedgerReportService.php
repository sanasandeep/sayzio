<?php

namespace App\Services\Billing;

use App\Modules\User\Models\Expense;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Refund;
use Illuminate\Support\Carbon;

/**
 * Builds an accounting ledger / P&L summary for a user over a date range:
 * income (paid invoices), refunds, expenses, tax collected, and net.
 * Used by web (HTML + CSV), REST API and mobile.
 */
class LedgerReportService
{
    /**
     * @param int         $userId
     * @param Carbon      $from
     * @param Carbon      $to
     * @param int|null    $companyId  Optionally scope to one billing company.
     * @return array
     */
    public function build(int $userId, Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        // Income recognizes every invoice that was paid within the range, even
        // if it was later (partially) refunded — otherwise a refunded invoice
        // would drop out of income while its refund is still subtracted below,
        // double-counting the loss. Refunds are applied once, separately.
        $invoiceQ = Invoice::query()->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('kind', 'client')
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        if ($companyId) $invoiceQ->where('billing_company_id', $companyId);

        $invoices = $invoiceQ->orderBy('paid_at')->get();

        $refundQ = Refund::query()
            ->where('user_id', $userId)
            ->where('status', 'succeeded')
            ->whereBetween('processed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        $refunds = $refundQ->get();

        $expenseQ = Expense::query()
            ->where('user_id', $userId)
            ->whereBetween('spent_at', [$from->copy()->toDateString(), $to->copy()->toDateString()]);
        if ($companyId) $expenseQ->where('billing_company_id', $companyId);
        $expenses = $expenseQ->orderBy('spent_at')->get();

        $incomeMinor   = (int) $invoices->sum('grand_total_minor');
        $taxCollected  = (int) $invoices->sum('tax_total_minor');
        $refundedMinor = (int) $refunds->sum('amount_minor');
        $expenseMinor  = (int) $expenses->sum(fn ($e) => $e->totalMinor());
        $expenseTax    = (int) $expenses->sum('tax_minor');

        $netIncome = $incomeMinor - $refundedMinor;
        $profit    = $netIncome - $expenseMinor;

        $currency = $invoices->first()->currency
            ?? $expenses->first()->currency
            ?? 'USD';

        // Monthly buckets for charting.
        $byMonth = [];
        foreach ($invoices as $inv) {
            $k = Carbon::parse($inv->paid_at)->format('Y-m');
            $byMonth[$k]['income'] = ($byMonth[$k]['income'] ?? 0) + (int) $inv->grand_total_minor;
        }
        foreach ($expenses as $e) {
            $k = Carbon::parse($e->spent_at)->format('Y-m');
            $byMonth[$k]['expense'] = ($byMonth[$k]['expense'] ?? 0) + $e->totalMinor();
        }
        ksort($byMonth);

        return [
            'range'    => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currency' => $currency,
            'totals'   => [
                'income_minor'        => $incomeMinor,
                'refunded_minor'      => $refundedMinor,
                'net_income_minor'    => $netIncome,
                'expense_minor'       => $expenseMinor,
                'expense_tax_minor'   => $expenseTax,
                'tax_collected_minor' => $taxCollected,
                'profit_minor'        => $profit,
                'invoice_count'       => $invoices->count(),
                'expense_count'       => $expenses->count(),
                'refund_count'        => $refunds->count(),
            ],
            'by_month'  => array_map(fn ($k) => [
                'month'         => $k,
                'income_minor'  => (int) ($byMonth[$k]['income'] ?? 0),
                'expense_minor' => (int) ($byMonth[$k]['expense'] ?? 0),
                'profit_minor'  => (int) ($byMonth[$k]['income'] ?? 0) - (int) ($byMonth[$k]['expense'] ?? 0),
            ], array_keys($byMonth)),
            'invoices'  => $invoices->map(fn ($i) => [
                'id' => $i->id, 'number' => $i->number, 'paid_at' => optional($i->paid_at)->toDateString(),
                'recipient' => $i->recipient_email, 'amount_minor' => (int) $i->grand_total_minor,
                'tax_minor' => (int) $i->tax_total_minor, 'currency' => $i->currency,
            ])->values()->all(),
            'expenses'  => $expenses->map(fn ($e) => [
                'id' => $e->id, 'spent_at' => optional($e->spent_at)->toDateString(),
                'vendor' => $e->vendor, 'description' => $e->description,
                'amount_minor' => (int) $e->amount_minor, 'tax_minor' => (int) $e->tax_minor,
                'currency' => $e->currency,
            ])->values()->all(),
        ];
    }

    /** Render the ledger as CSV rows (income + expenses, chronological). */
    public function toCsv(array $report): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Date', 'Type', 'Reference', 'Party', 'Amount', 'Tax', 'Currency']);
        foreach ($report['invoices'] as $i) {
            fputcsv($out, [
                $i['paid_at'], 'Income', $i['number'], $i['recipient'],
                number_format($i['amount_minor'] / 100, 2),
                number_format($i['tax_minor'] / 100, 2), $i['currency'],
            ]);
        }
        foreach ($report['expenses'] as $e) {
            fputcsv($out, [
                $e['spent_at'], 'Expense', $e['description'], $e['vendor'],
                '-' . number_format($e['amount_minor'] / 100, 2),
                number_format($e['tax_minor'] / 100, 2), $e['currency'],
            ]);
        }
        rewind($out);
        return stream_get_contents($out);
    }
}
