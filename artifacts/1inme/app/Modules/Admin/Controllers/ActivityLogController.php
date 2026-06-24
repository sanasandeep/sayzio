<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AdminActionAudit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Activity log" viewer for the admin/staff user-management suite. Reads
 * the append-only {@see AdminActionAudit} ledger with basic filtering
 * (operator, target, action, date range). Gated by `users.view` at the
 * route layer — the same read access as the user list.
 */
class ActivityLogController extends Controller
{
    /** Action filter options surfaced in the viewer. */
    public const ACTIONS = [
        'plan.assigned'       => 'Plan assigned',
        'coins.granted'       => 'Coins granted',
        'coins.deducted'      => 'Coins deducted',
        'account.created'     => 'Account created',
        'account.suspended'   => 'Account suspended',
        'account.reactivated' => 'Account reactivated',
    ];

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $audits = $this->query($filters)
            ->with(['admin:id,name,email', 'targetUser:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.activity-log.index', [
            'audits'  => $audits,
            'filters' => $filters,
            'actions' => self::ACTIONS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->query($filters)->orderByDesc('created_at');

        $filename = 'activity-log-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Operator', 'Operator email', 'Action', 'Target', 'Target email', 'IP', 'Details']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        optional($row->created_at)->toDateTimeString(),
                        $row->admin_name,
                        $row->admin_email,
                        $row->action,
                        $row->target_name,
                        $row->target_email,
                        $row->ip,
                        $row->details ? json_encode($row->details) : '',
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array{operator:string,target:string,action:string,from:string,to:string}  $filters
     */
    protected function query(array $filters)
    {
        return AdminActionAudit::query()
            ->when($filters['operator'] !== '', function ($q) use ($filters) {
                $term = $filters['operator'];
                $q->where(function ($w) use ($term) {
                    $w->where('admin_name', 'ilike', "%{$term}%")
                      ->orWhere('admin_email', 'ilike', "%{$term}%");
                });
            })
            ->when($filters['target'] !== '', function ($q) use ($filters) {
                $term = $filters['target'];
                $q->where(function ($w) use ($term) {
                    $w->where('target_name', 'ilike', "%{$term}%")
                      ->orWhere('target_email', 'ilike', "%{$term}%");
                    if (ctype_digit($term)) {
                        $w->orWhere('target_user_id', (int) $term);
                    }
                });
            })
            ->when($filters['action'] !== '' && isset(self::ACTIONS[$filters['action']]), function ($q) use ($filters) {
                $q->where('action', $filters['action']);
            })
            ->when($filters['from'] !== '', function ($q) use ($filters) {
                $q->whereDate('created_at', '>=', $filters['from']);
            })
            ->when($filters['to'] !== '', function ($q) use ($filters) {
                $q->whereDate('created_at', '<=', $filters['to']);
            });
    }

    /**
     * @return array{operator:string,target:string,action:string,from:string,to:string}
     */
    protected function filters(Request $request): array
    {
        return [
            'operator' => trim((string) $request->get('operator', '')),
            'target'   => trim((string) $request->get('target', '')),
            'action'   => (string) $request->get('action', ''),
            'from'     => trim((string) $request->get('from', '')),
            'to'       => trim((string) $request->get('to', '')),
        ];
    }
}
