<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\UserRoleAudit;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a `UserRoleAudit` query as a downloadable CSV. Lives in its
 * own class so the self-service "User access" page and the back-office
 * user/roles pages emit identical column sets and ordering — that way
 * a reviewer can diff exports from the two surfaces without column
 * drift, and the schema lives in one place if it ever needs to grow.
 */
class UserRoleAuditCsvExporter
{
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
     */
    public function streamResponse(Builder $query, string $filename): StreamedResponse
    {
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
