<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\SchemaRepairAudit;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Support\ExpectedSchemaHealth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Bearer-token parity for the admin one-click schema-column repair so a
 * super admin can spot and fix edited-after-applied column drift from the
 * 1INME Mobile app while troubleshooting on the go.
 *
 * This is the mobile counterpart of the web dashboard banner +
 * {@see \App\Modules\Admin\Controllers\DashboardController::repairExpectedColumns()}.
 * Both surfaces share the SAME engine
 * ({@see \App\Modules\Common\Support\ExpectedSchemaHealth}) so a column that
 * the web "Fix now" button would re-create is re-created identically here:
 *
 *   GET  /api/v1/admin/schema-health         (read-only drift report)
 *   POST /api/v1/admin/schema-health/repair  (add + backfill missing columns)
 *
 * `repair` is destructive-adjacent (it ALTERs the live schema), so — exactly
 * like the web path — it writes a {@see SchemaRepairAudit} row recording WHO
 * ran it, WHEN, and the schema-level outcome (added columns per table +
 * whole-missing tables it could not recreate), then re-checks the live schema
 * so the response reflects reality rather than a stale cached report. Whole-
 * missing tables can't be re-created in place and are surfaced under
 * `unrepairable` (they still need `php artisan migrate --force`), while column
 * drift is surfaced under `added`.
 *
 * Every endpoint is gated behind the same `settings.manage` permission the web
 * routes use, so only platform admins reach them; a regular sanctum token is
 * rejected with 403.
 */
class SchemaHealthController extends Controller
{
    use ApiResponses;

    /**
     * Read-only drift report: the expected tables/columns the live DB is
     * missing despite their migration being recorded as ran. Mirrors the
     * dashboard banner so the mobile screen can show "in sync" or list the
     * drift before offering the repair action.
     */
    public function status(Request $request)
    {
        if (! $request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view schema health.');
        }

        return $this->ok($this->reportPayload(ExpectedSchemaHealth::compute()));
    }

    /**
     * Read-only, paginated timeline of past one-click schema repair runs —
     * who ran each repair, when, and which columns/tables it touched. Mirrors
     * the web admin repair-audit page so a reviewer can audit this
     * destructive-adjacent action from the 1INME Mobile app. Only schema
     * metadata is returned (added columns per table + whole-missing tables it
     * could not recreate), never row data.
     */
    public function audits(Request $request)
    {
        if (! $request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view schema repair audits.');
        }

        $perPage = (int) $request->integer('per_page', 30);
        $perPage = max(1, min($perPage, 100));

        $audits = SchemaRepairAudit::query()
            ->with(['actorAdmin:id,name,email', 'actorUser:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->ok([
            'audits' => array_map(fn (SchemaRepairAudit $a) => $this->auditPayload($a), $audits->items()),
            'meta'   => [
                'current_page' => $audits->currentPage(),
                'last_page'    => $audits->lastPage(),
                'per_page'     => $audits->perPage(),
                'total'        => $audits->total(),
            ],
        ]);
    }

    /**
     * Shape a single {@see SchemaRepairAudit} row for the mobile list — the
     * resolved actor label/email, the timestamp, the per-table added columns
     * and the whole-missing tables it could not repair, plus the convenience
     * counts the screen renders as badges.
     */
    private function auditPayload(SchemaRepairAudit $audit): array
    {
        return [
            'id'                  => $audit->id,
            'actor_label'         => $audit->actorLabel(),
            'actor_email'         => $audit->actor_email,
            'actor_guard'         => $audit->actor_guard,
            'added'               => (array) ($audit->added ?? []),
            'unrepairable'        => array_values((array) ($audit->unrepairable ?? [])),
            'added_columns_count' => (int) $audit->added_columns_count,
            'added_tables_count'  => (int) $audit->added_tables_count,
            'unrepairable_count'  => (int) $audit->unrepairable_count,
            'changed_schema'      => $audit->changedSchema(),
            'ip'                  => $audit->ip,
            'created_at'          => optional($audit->created_at)->toIso8601String(),
        ];
    }

    /**
     * Add + backfill any missing expected columns in place (idempotent /
     * guarded — {@see ExpectedSchemaHealth::repair()}), record the audit row,
     * then re-check the live schema and report the outcome. Whole-missing
     * tables are returned under `unrepairable`; repaired column drift under
     * `added`.
     */
    public function repair(Request $request)
    {
        if (! $request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to repair the schema.');
        }

        $result = ExpectedSchemaHealth::repair();

        // Record WHO ran the repair, WHEN, and the schema-level outcome first —
        // this destructive-adjacent ops action must leave an audit trail even
        // if a later step throws. Best-effort: a logging miss must not break
        // the repair.
        $this->recordRepairAudit($result, $request);

        // Re-check against the live schema so the response reflects reality
        // rather than a stale cached report.
        ExpectedSchemaHealth::flush();
        $stillMissing = ExpectedSchemaHealth::missingCount(true);

        $added        = $result['added'] ?? [];
        $unrepairable = array_values(array_unique($result['unrepairable'] ?? []));

        return $this->ok([
            'added'               => $added,
            'unrepairable'        => $unrepairable,
            'added_tables_count'  => count($added),
            'added_columns_count' => array_sum(array_map('count', $added)),
            'unrepairable_count'  => count($unrepairable),
            'still_missing'       => $stillMissing,
            'healthy'             => $stillMissing === 0,
        ]);
    }

    /**
     * Shape a {@see ExpectedSchemaHealth::compute()} report for the API: keep
     * the documented `available`/`scanned`/`missing` fields and add the
     * convenience `missing_count`/`healthy` flags the mobile screen renders.
     *
     * @param array{available:bool, scanned:int, missing:array<int,array{table:string,table_missing:bool,columns:array<int,string>}>, error?:string} $report
     */
    private function reportPayload(array $report): array
    {
        $missing = $report['missing'] ?? [];

        $payload = [
            'available'     => (bool) ($report['available'] ?? false),
            'scanned'       => (int) ($report['scanned'] ?? 0),
            'missing'       => array_values($missing),
            'missing_count' => count($missing),
            'healthy'       => ($report['available'] ?? false) && count($missing) === 0,
        ];

        if (isset($report['error'])) {
            $payload['error'] = $report['error'];
        }

        return $payload;
    }

    /**
     * Append one audit row for a repair run. The mobile caller is a web-guard
     * Sanctum user, so the actor is recorded under the `web` guard. Schema-
     * level metadata only — added columns per table and the names of whole-
     * missing tables that could not be repaired; never row data. Best-effort:
     * a logging failure is swallowed (and logged) so it can never break the
     * user-facing repair.
     *
     * @param array{added:array<string,array<int,string>>, unrepairable:array<int,string>} $result
     */
    protected function recordRepairAudit(array $result, Request $request): void
    {
        try {
            $added        = $result['added'] ?? [];
            $unrepairable = array_values(array_unique($result['unrepairable'] ?? []));
            $user         = $request->user();

            SchemaRepairAudit::create([
                'actor_admin_id'      => null,
                'actor_user_id'       => $user?->id,
                'actor_guard'         => 'web',
                'actor_name'          => $user?->name,
                'actor_email'         => $user?->email,
                'added'               => $added,
                'unrepairable'        => $unrepairable,
                'added_columns_count' => array_sum(array_map('count', $added)),
                'added_tables_count'  => count($added),
                'unrepairable_count'  => count($unrepairable),
                'ip'                  => $request->ip(),
                'created_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SchemaHealthController: failed to record schema repair audit', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
