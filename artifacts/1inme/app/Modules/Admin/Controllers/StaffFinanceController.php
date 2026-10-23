<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CoinPurchaseAllocation;
use App\Modules\Admin\Support\StaffPermissionCatalog;
use App\Modules\User\Models\Invoice;
use Illuminate\Http\Request;

/** Summary queries and record queries have independent capabilities and payloads. */
class StaffFinanceController extends Controller
{
    public function index(Request $request)
    {
        $actor = auth('admin')->user();
        $actor?->loadMissing('role.permissions');
        abort_unless($actor?->hasAnyPermission([
            'finance.invoices.totals', 'finance.coins.totals', 'finance.invoices.view', 'finance.coins.view',
            ...\App\Modules\Admin\Support\StaffPermissionCatalog::aiSlugs(),
        ]), 403);
        $filters = $this->filters($request);
        $invoiceTotals = $actor->hasPermission('finance.invoices.totals')
            ? $this->invoices($filters)->selectRaw('currency, status, COUNT(*) AS records, SUM(grand_total_minor) AS total_minor')
                ->groupBy('currency', 'status')->orderBy('currency')->get() : collect();
        $coinTotals = $actor->hasPermission('finance.coins.totals')
            ? $this->purchases($filters)->selectRaw('currency, COUNT(*) AS records, SUM(amount_minor) AS total_minor, SUM(coins) AS coins')
                ->groupBy('currency')->orderBy('currency')->get() : collect();
        // Aggregate-only viewers never load customer models or individual records.
        return view('admin.finance.index', compact('actor', 'filters', 'invoiceTotals', 'coinTotals'));
    }

    public function records(Request $request)
    {
        $actor = auth('admin')->user();
        $actor?->loadMissing('role.permissions');
        $section = $request->validate(['section' => 'required|in:invoices,coins'])['section'];
        abort_unless($actor?->hasPermission('finance.'.$section.'.view'), 403);
        $filters = $this->filters($request);
        $query = $section === 'invoices' ? $this->invoices($filters) : $this->purchases($filters);
        $rows = $query->with('user.plan')->latest('id')->paginate(30)->withQueryString();
        $rows->through(function ($row) use ($section, $actor) {
            $record = [
                'id' => $row->id, 'date' => $row->created_at,
                'account' => $row->user ? StaffPermissionCatalog::userRow($actor, $row->user, true) : ['label' => 'Deleted account'],
                'currency' => $row->currency,
                'amount_minor' => $section === 'invoices' ? $row->grand_total_minor : $row->amount_minor,
            ];
            if ($section === 'invoices') {
                $record['number'] = $row->number;
                $record['status'] = $row->status;
                $record['kind'] = $row->kind;
            } else {
                $record['coins'] = $row->coins;
                if ($actor->hasPermission('finance.margin.view')) {
                    $record['api_budget_minor'] = $row->api_budget_minor;
                    $record['margin_minor'] = $row->margin_minor;
                }
            }
            return $record;
        });
        return view('admin.finance.records', compact('actor', 'rows', 'section', 'filters'));
    }

    public function ai(Request $request)
    {
        $actor = auth('admin')->user();
        $actor?->loadMissing('role.permissions');
        $data = $request->validate(['feature' => 'required|string|max:100', 'mode' => 'required|in:totals,records']);
        $filters = $this->filters($request);
        $feature = $data['feature'];
        $mode = $data['mode'];
        $query = \App\Modules\Admin\Support\StaffAiFinance::apply(
            $this->dates(\App\Modules\User\Models\WalletTransaction::query(), $filters), $actor, $feature, $mode
        );
        $totals = null;
        $rows = null;
        if ($mode === 'totals') {
            $totals = $query->selectRaw("COUNT(*) AS records, COALESCE(SUM(CASE WHEN delta_coins < 0 THEN -delta_coins ELSE 0 END),0) AS spent, COALESCE(SUM(CASE WHEN delta_coins > 0 THEN delta_coins ELSE 0 END),0) AS credited")->first();
        } else {
            $rows = $query->with('user.plan')->latest('id')->paginate(30)->withQueryString();
            $rows->through(fn ($tx) => [
                'date' => $tx->created_at, 'type' => $tx->type, 'coins' => $tx->delta_coins,
                'account' => $tx->user ? StaffPermissionCatalog::userRow($actor, $tx->user, true) : ['label' => 'Deleted account'],
            ]);
        }
        return view('admin.finance.ai', compact('actor', 'feature', 'mode', 'filters', 'totals', 'rows'));
    }

    private function filters(Request $request): array
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        if ($request->filled('from') && $request->filled('to')) $request->validate(['to' => 'after_or_equal:from']);
        return $data;
    }

    private function invoices(array $filters)
    {
        // Exclude creators' own client invoices from platform-purchase reporting.
        return $this->dates(Invoice::where(fn ($q) => $q->whereNull('kind')->orWhere('kind', '!=', 'client')), $filters);
    }

    private function purchases(array $filters)
    {
        return $this->dates(CoinPurchaseAllocation::query(), $filters);
    }

    private function dates($query, array $filters)
    {
        return $query->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
    }
}
