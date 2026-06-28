<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BillingCompany;
use App\Services\Billing\LedgerReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Accounting ledger / P&L report (income, refunds, expenses, tax, net). */
class LedgerController extends Controller
{
    public function index(Request $request, LedgerReportService $svc)
    {
        [$from, $to, $companyId] = $this->range($request);
        $report    = $svc->build((int) auth()->id(), $from, $to, $companyId);
        $companies = BillingCompany::where('user_id', auth()->id())->orderBy('name')->get();
        return view('user.billing.ledger.index', compact('report', 'companies', 'from', 'to', 'companyId'));
    }

    public function export(Request $request, LedgerReportService $svc)
    {
        [$from, $to, $companyId] = $this->range($request);
        $report = $svc->build((int) auth()->id(), $from, $to, $companyId);
        $csv = $svc->toCsv($report);
        $name = 'ledger_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.csv';
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    /** @return array{0:Carbon,1:Carbon,2:?int} */
    protected function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : now()->startOfYear();
        $to   = $request->filled('to')   ? Carbon::parse($request->query('to'))   : now()->endOfDay();
        $companyId = $request->integer('company') ?: null;
        return [$from, $to, $companyId];
    }
}
