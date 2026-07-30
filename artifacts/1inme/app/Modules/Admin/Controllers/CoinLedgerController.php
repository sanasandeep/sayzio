<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use Illuminate\Http\Request;

/**
 * Platform-wide coin ledger: every user's wallet transactions in one
 * day-grouped, filterable statement so admins can audit coin flow.
 *
 * Read-only — all writes stay in WalletService. Filters: date range,
 * transaction type, and user (exact id via drill-down, or name/email
 * search). CSV export mirrors the on-screen filtered set.
 */
class CoinLedgerController extends Controller
{
    /** Shared filter pipeline for the page, day totals, summary and CSV. */
    protected function filtered(Request $request)
    {
        $q = WalletTransaction::query();

        if (($t = $request->query('type')) && in_array($t, WalletTransaction::TYPES, true)) {
            $q->where('type', $t);
        }
        if ($from = $request->query('from')) {
            $q->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->where('created_at', '<=', $to . ' 23:59:59');
        }
        if ($userId = $request->query('user_id')) {
            $q->where('user_id', (int) $userId);
        } elseif ($search = trim((string) $request->query('q'))) {
            $q->whereIn('user_id', User::query()
                ->where(function ($u) use ($search) {
                    $u->where('name', 'ILIKE', "%{$search}%")
                      ->orWhere('email', 'ILIKE', "%{$search}%");
                })
                ->limit(500)->pluck('id'));
        }
        return $q;
    }

    public function index(Request $request)
    {
        $summary = (clone $this->filtered($request))
            ->selectRaw('COALESCE(SUM(CASE WHEN delta_coins > 0 THEN delta_coins ELSE 0 END),0) AS coins_in,'
                . 'COALESCE(SUM(CASE WHEN delta_coins < 0 THEN -delta_coins ELSE 0 END),0) AS coins_out,'
                . 'COALESCE(SUM(delta_coins),0) AS net, COUNT(*) AS entries')
            ->first();

        $page = $this->filtered($request)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        // Day-group the current page; platform-wide subtotals per visible
        // day come from a filtered aggregate so split pages stay correct.
        $days = [];
        foreach ($page->items() as $tx) {
            $key = $tx->created_at ? $tx->created_at->format('Y-m-d') : 'unknown';
            $days[$key][] = $tx;
        }
        $dayKeys = array_keys($days);
        $dayTotals = collect();
        if ($dayKeys) {
            $dayTotals = $this->filtered($request)
                ->selectRaw("to_char(created_at, 'YYYY-MM-DD') AS day,"
                    . 'COALESCE(SUM(CASE WHEN delta_coins > 0 THEN delta_coins ELSE 0 END),0) AS coins_in,'
                    . 'COALESCE(SUM(CASE WHEN delta_coins < 0 THEN -delta_coins ELSE 0 END),0) AS coins_out,'
                    . 'COALESCE(SUM(delta_coins),0) AS net')
                ->whereRaw("to_char(created_at, 'YYYY-MM-DD') IN ('" . implode("','", $dayKeys) . "')")
                ->groupBy('day')->get()->keyBy('day');
        }

        $drillUser = null;
        if ($userId = $request->query('user_id')) {
            $drillUser = User::select('id', 'name', 'email')->find((int) $userId);
        }

        return view('admin.coin-ledger.index', [
            'page'      => $page,
            'days'      => $days,
            'dayTotals' => $dayTotals,
            'summary'   => $summary,
            'drillUser' => $drillUser,
            'filters'   => $request->only(['type', 'from', 'to', 'q', 'user_id']),
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->filtered($request)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')->orderByDesc('id');

        $filename = 'coin-ledger-all-users-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Time', 'User', 'Email', 'Type', 'Description', 'Coins', 'Balance after']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $tx) {
                    fputcsv($out, [
                        optional($tx->created_at)->format('Y-m-d'),
                        optional($tx->created_at)->format('H:i:s'),
                        $tx->user->name ?? ('#' . $tx->user_id),
                        $tx->user->email ?? '',
                        $tx->type,
                        $tx->reason ?? '',
                        (int) $tx->delta_coins,
                        (int) $tx->balance_after,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
