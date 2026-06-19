<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\ExpectedSchemaHealth;
use App\Modules\Common\Support\SchemaHealth;
use App\Modules\Common\Support\WorkspaceColumnHealth;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_staff' => Admin::count(),
            'total_plans' => Plan::where('status', 'active')->count(),
            'recent_users' => User::latest()->take(5)->get(),
            'users_today' => User::whereDate('created_at', today())->count(),
            'users_this_month' => User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        // Proactive out-of-date-schema warning (Task #1679). Cached so it
        // adds at most one cheap query every couple of minutes to the
        // dashboard render.
        $schemaHealth = SchemaHealth::cached();

        // Proactive half-applied-migration warning: workspace-scoping columns
        // that are missing from the live DB despite their migration being logged
        // as ran (the failure class SchemaHealth is blind to). Cached.
        $workspaceColumnHealth = WorkspaceColumnHealth::cached();

        // Proactive edited-after-applied drift warning: critical tables/columns
        // the code depends on that are missing despite their migration being
        // logged as ran (a recorded migration later edited to add columns is
        // never re-run). Cached.
        $expectedSchemaHealth = ExpectedSchemaHealth::cached();

        return view('admin.dashboard.index', compact('stats', 'schemaHealth', 'workspaceColumnHealth', 'expectedSchemaHealth'));
    }

    /**
     * One-click auto-repair for edited-after-applied column drift surfaced by the
     * dashboard banner. Adds + backfills the missing expected columns in place
     * (guarded/idempotent — {@see ExpectedSchemaHealth::repair()}) so ops can
     * resolve drift without shell access, then re-checks and reports the outcome.
     */
    public function repairExpectedColumns()
    {
        $result = ExpectedSchemaHealth::repair();

        // Re-check against the live schema so the banner reflects reality on the
        // redirect rather than a stale cached report.
        ExpectedSchemaHealth::flush();
        $stillMissing = ExpectedSchemaHealth::missingCount(true);

        $addedTables = array_keys($result['added']);
        $addedCount  = array_sum(array_map('count', $result['added']));

        if ($addedCount > 0) {
            $detail = implode('; ', array_map(
                fn ($t) => $t . ' (' . implode(', ', $result['added'][$t]) . ')',
                $addedTables
            ));
            $message = "Repaired {$addedCount} column(s): {$detail}.";
            if ($stillMissing > 0) {
                $message .= " {$stillMissing} table(s) still need attention"
                    . (! empty($result['unrepairable'])
                        ? ' (whole table missing — run `php artisan migrate --force`): ' . implode(', ', $result['unrepairable'])
                        : '.');
            }
            $flash = ['success' => $message];
        } elseif (! empty($result['unrepairable'])) {
            $flash = ['error' => 'Could not auto-repair (whole table missing — run `php artisan migrate --force`): '
                . implode(', ', $result['unrepairable']) . '.'];
        } else {
            $flash = ['success' => 'Nothing to repair — all expected columns are already present.'];
        }

        return redirect()->route('admin.dashboard')->with($flash);
    }
}
