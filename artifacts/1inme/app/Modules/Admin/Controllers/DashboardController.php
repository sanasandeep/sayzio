<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\BgTemplateHealth;
use App\Modules\Common\Support\ContactRecipientHealth;
use App\Modules\Common\Support\ExpectedSchemaHealth;
use App\Modules\Common\Support\SchemaHealth;
use App\Modules\Common\Support\StatsStorageHealth;
use App\Modules\Common\Support\TemplateGalleryHealth;
use App\Modules\Common\Support\WorkspaceColumnHealth;
use App\Modules\Admin\Support\ScheduledJobHealthAlerts;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\SchemaRepairAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        // Proactive unbounded-analytics-growth warning: a high-volume stats
        // table is over the alert threshold AND nothing will prune it. Cached.
        $statsStorage = StatsStorageHealth::cached();

        // Proactive lost-leads warning: no admin contact recipient is configured,
        // so quick-contact / contact-form leads land in the inbox but nobody is
        // notified by email. Cached.
        $contactRecipientHealth = ContactRecipientHealth::cached();

        // Proactive empty-onboarding-gallery warning: zero active page templates,
        // so the onboarding wizard silently degrades to its "No templates
        // available yet" escape and new users land on a bare setup screen with
        // nobody being told. Cached.
        $templateGalleryHealth = TemplateGalleryHealth::cached();

        // Proactive empty/thin background-template-library warning: the
        // biolink editor's Appearance → Page background → Template picker
        // silently shows "No templates available yet" when bg_templates has
        // no active rows. Mirrors the bg-templates:check-library watchdog on
        // a persistent dashboard surface. Cached.
        $bgTemplateHealth = BgTemplateHealth::cached();

        // Proactive failing-scheduled-job warning: jobs with an open failure
        // episode (last run failed, no success since) surfaced at a glance,
        // mirroring the SchemaHealth banner. Cheap: one cached AppSetting read.
        $failureEpisodes = ScheduledJobHealthAlerts::openEpisodes();

        return view('admin.dashboard.index', compact('stats', 'schemaHealth', 'workspaceColumnHealth', 'expectedSchemaHealth', 'statsStorage', 'contactRecipientHealth', 'templateGalleryHealth', 'bgTemplateHealth', 'failureEpisodes'));
    }

    /**
     * One-click auto-repair for edited-after-applied column drift surfaced by the
     * dashboard banner. Adds + backfills the missing expected columns in place
     * (guarded/idempotent — {@see ExpectedSchemaHealth::repair()}) so ops can
     * resolve drift without shell access, then re-checks and reports the outcome.
     */
    public function repairExpectedColumns(Request $request)
    {
        $result = ExpectedSchemaHealth::repair();

        // Record WHO ran the repair, WHEN, and the schema-level outcome before
        // anything else — this destructive-adjacent ops action must leave an
        // audit trail even if a later step throws. Only schema metadata is
        // logged (added columns per table + unrepairable table names), never
        // row data, and it lives in its own table so it survives the cache
        // flush below. Best-effort: a logging miss must not break the repair.
        $this->recordRepairAudit($result, $request);

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

    /**
     * Append one audit row for a repair run so ops can see who altered the
     * live schema and when. Schema-level metadata only — added columns per
     * table and the names of whole-missing tables that could not be repaired.
     * Best-effort: a logging failure is swallowed (and logged) so it can
     * never break the user-facing repair.
     *
     * @param array{added:array<string,array<int,string>>, unrepairable:array<int,string>} $result
     */
    protected function recordRepairAudit(array $result, Request $request): void
    {
        try {
            $added        = $result['added'] ?? [];
            $unrepairable = array_values(array_unique($result['unrepairable'] ?? []));

            SchemaRepairAudit::create(array_merge($this->resolveActor(), [
                'added'               => $added,
                'unrepairable'        => $unrepairable,
                'added_columns_count' => array_sum(array_map('count', $added)),
                'added_tables_count'  => count($added),
                'unrepairable_count'  => count($unrepairable),
                'ip'                  => $request->ip(),
                'created_at'          => now(),
            ]));
        } catch (\Throwable $e) {
            Log::error('DashboardController: failed to record schema repair audit', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tiny JSON endpoint the admin footer polls so the "Last updated"
     * timestamp refreshes in place without a full page reload. Returns the
     * same cached value the footer renders on page load.
     */
    public function lastUpdated()
    {
        $ts = \App\Support\SiteLastUpdated::get();

        return response()->json([
            'available' => $ts !== null,
            'iso'       => $ts?->toIso8601String(),
            'formatted' => $ts?->format('M j, Y H:i') . ($ts ? ' UTC' : null),
            'relative'  => $ts?->diffForHumans(),
        ]);
    }

    /**
     * Read-only timeline of past one-click schema repair runs — who ran each
     * repair, when, and which columns/tables it touched.
     */
    public function repairAudits()
    {
        $audits = SchemaRepairAudit::query()
            ->with(['actorAdmin:id,name,email', 'actorUser:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.dashboard.repair-audits', compact('audits'));
    }

    /**
     * Inspect the authenticated principal across both guards and return a
     * small actor descriptor. The repair action runs behind the admin guard,
     * so this is normally an Admin; null fields are fine — the audit row
     * records "System" in that case.
     *
     * @return array{actor_admin_id:?int, actor_user_id:?int, actor_guard:?string, actor_name:?string, actor_email:?string}
     */
    protected function resolveActor(): array
    {
        $admin = Auth::guard('admin')->user();
        if ($admin instanceof Admin) {
            return [
                'actor_admin_id' => (int) $admin->id,
                'actor_user_id'  => null,
                'actor_guard'    => 'admin',
                'actor_name'     => $admin->name,
                'actor_email'    => $admin->email,
            ];
        }

        $user = Auth::guard('web')->user();
        if ($user instanceof User) {
            return [
                'actor_admin_id' => null,
                'actor_user_id'  => (int) $user->id,
                'actor_guard'    => 'web',
                'actor_name'     => $user->name,
                'actor_email'    => $user->email,
            ];
        }

        return [
            'actor_admin_id' => null,
            'actor_user_id'  => null,
            'actor_guard'    => null,
            'actor_name'     => null,
            'actor_email'    => null,
        ];
    }
}
