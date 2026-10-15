<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AiStaffReports;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AiStaffReportController extends Controller
{
    public function index(Request $request, AiStaffReports $reports)
    {
        return $this->report($request, $reports, $request->user()->id, 'user.layouts.app');
    }

    public function admin(Request $request, AiStaffReports $reports)
    {
        return $this->report($request, $reports, null, 'admin.layouts.app');
    }

    private function report(Request $r, AiStaffReports $reports, ?int $userId, string $layout)
    {
        $data = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'export' => 'nullable|in:csv']);
        $from = Carbon::parse($data['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();
        abort_if($from > $to, 422, 'Invalid date range.');
        $result = $reports->data($userId, $from->toDateTimeString(), $to->toDateTimeString());
        if (($data['export'] ?? '') === 'csv') {
            return response()->streamDownload(function () use ($result) {
                $f = fopen('php://output', 'w');
                fputcsv($f, ['Date (server timezone)', 'AI charges', 'Coins']);
                foreach ($result['daily'] as $row) {
                    fputcsv($f, [$row->day, $row->charges, $row->coins]);
                }fclose($f);
            }, 'ai-staff-report.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'private, no-store']);
        }

        return view('user.ai.staff.reports', $result + ['reportLayout' => $layout, 'adminReport' => $userId === null]);
    }
}
