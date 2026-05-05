<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Models\UserRoleAuditExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a `UserRoleAudit` query as a downloadable CSV. Lives in its
 * own class so the self-service "User access" page and the back-office
 * user/roles pages emit identical column sets and ordering — that way
 * a reviewer can diff exports from the two surfaces without column
 * drift, and the schema lives in one place if it ever needs to grow.
 *
 * Each call also writes one row to the `user_role_audit_exports`
 * ledger via `UserRoleAuditExportLogger`, so super-admins can audit
 * the auditors — i.e. see who pulled the role-change history, when,
 * from where, and how many rows came along for the ride.
 */
class UserRoleAuditCsvExporter
{
    public function __construct(
        protected ?UserRoleAuditExportLogger $exportLogger = null,
    ) {
    }

    /**
     * Header row written first. Order matches the writeRow() emission
     * below — keep them in sync.
     */
    public const COLUMNS = [
        'timestamp',
        'actor_label',
        'actor_guard',
        'action',
        'role_slug',
        'role_name',
        'target_user_id',
        'target_user_label',
        'source',
        'ip',
    ];

    /**
     * Build a streamed CSV download response from a `UserRoleAudit`
     * query. The query is consumed in chunks so very long histories
     * don't materialise the whole result set in memory.
     *
     * Caller is responsible for any access checks before invoking
     * this — the exporter does no permission gating itself.
     *
     * `$context` describes the export for the audit ledger:
     *   - 'scope':          one of `UserRoleAuditExport::SCOPE_*`
     *   - 'target_user_id': set when the export is scoped to a
     *                       single user; null for full-pool pulls
     *   - 'request':        the originating HTTP request, used to
     *                       capture the client IP. Optional — when
     *                       omitted the row is logged without an IP.
     *
     * @param array{
     *   scope?: ?string,
     *   target_user_id?: ?int,
     *   request?: ?Request,
     * } $context
     */
    public function streamResponse(
        Builder $query,
        string $filename,
        array $context = [],
    ): StreamedResponse {
        // Snapshot the row count BEFORE streaming so the audit row
        // captures it even if the response is aborted mid-flight.
        // Cloned so the orderBy/with we apply below stays scoped to
        // the streamed read and doesn't leak into the count query.
        $rowCount = (int) (clone $query)->count();

        $this->logExport($context, $rowCount);

        $query = (clone $query)
            ->with([
                'actorUser:id,name,email',
                'actorAdmin:id,name,email',
                'targetUser:id,name,email',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return new StreamedResponse(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::COLUMNS);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    $this->writeRow($out, $row);
                }
            });

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }

    /**
     * Hand the export off to `UserRoleAuditExportLogger`. Callers
     * may omit the context entirely (e.g. tests) — we then default
     * to a full-pool, no-target row so we still leave a trace.
     *
     * @param array{
     *   scope?: ?string,
     *   target_user_id?: ?int,
     *   request?: ?Request,
     * } $context
     */
    protected function logExport(array $context, int $rowCount): void
    {
        $logger = $this->exportLogger ?? app(UserRoleAuditExportLogger::class);

        $scope = $context['scope'] ?? UserRoleAuditExport::SCOPE_FULL_POOL;
        $targetUserId = $context['target_user_id'] ?? null;
        $request = $context['request'] ?? null;

        $logger->record(
            scope: $scope,
            targetUserId: $targetUserId !== null ? (int) $targetUserId : null,
            rowCount: $rowCount,
            ip: $request instanceof Request ? $request->ip() : null,
        );
    }

    /**
     * @param resource $out
     */
    protected function writeRow($out, UserRoleAudit $row): void
    {
        fputcsv($out, [
            optional($row->created_at)->toIso8601String() ?? '',
            $row->actorLabel(),
            $row->actor_guard ?? '',
            $row->action ?? '',
            $row->role_slug ?? '',
            $row->role_name ?? '',
            (string) $row->target_user_id,
            optional($row->targetUser)->name
                ?: (optional($row->targetUser)->email
                    ?: ('User #' . $row->target_user_id)),
            $row->source ?? '',
            $row->ip ?? '',
        ]);
    }
}
