<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Models\Admin;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAuditExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Single entrypoint that `UserRoleAuditCsvExporter` uses to append
 * one row to the `user_role_audit_exports` ledger per CSV download
 * of the role-change audit. Mirrors the actor-resolution shape of
 * `UserRoleAuditLogger` so both ledgers attribute writes the same
 * way (split web/admin guard, snapshotted name/email).
 *
 * Failures are logged and swallowed — a missed audit-of-the-audit
 * row must never break the export the operator actually asked for.
 */
class UserRoleAuditExportLogger
{
    /**
     * Append one ledger row describing a single CSV export.
     *
     * `$scope` should be one of `UserRoleAuditExport::SCOPE_*`.
     * `$targetUserId` is required for `SCOPE_SINGLE_USER` and
     * ignored otherwise.
     */
    public function record(
        string $scope,
        ?int $targetUserId,
        int $rowCount,
        ?string $ip,
    ): ?UserRoleAuditExport {
        try {
            $actor = $this->resolveActor();

            return UserRoleAuditExport::create(array_merge($actor, [
                'scope'          => $scope,
                'target_user_id' => $scope === UserRoleAuditExport::SCOPE_SINGLE_USER
                    ? $targetUserId
                    : null,
                'row_count'      => max(0, $rowCount),
                'ip'             => $ip,
                'created_at'     => now(),
            ]));
        } catch (\Throwable $e) {
            Log::error('UserRoleAuditExportLogger: failed to append export row', [
                'scope'          => $scope,
                'target_user_id' => $targetUserId,
                'row_count'      => $rowCount,
                'error'          => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Inspect the currently-authenticated principal across both
     * guards (`web` user vs `admin` Admin) and return a small
     * actor descriptor. Returning all-null is fine — the ledger
     * row then records "System" via `actorLabel()`.
     *
     * @return array{
     *   actor_user_id: ?int,
     *   actor_admin_id: ?int,
     *   actor_guard: ?string,
     *   actor_name: ?string,
     *   actor_email: ?string,
     * }
     */
    protected function resolveActor(): array
    {
        $admin = Auth::guard('admin')->user();
        if ($admin instanceof Admin) {
            return [
                'actor_user_id'  => null,
                'actor_admin_id' => (int) $admin->id,
                'actor_guard'    => 'admin',
                'actor_name'     => $admin->name,
                'actor_email'    => $admin->email,
            ];
        }

        $user = Auth::guard('web')->user();
        if ($user instanceof User) {
            return [
                'actor_user_id'  => (int) $user->id,
                'actor_admin_id' => null,
                'actor_guard'    => 'web',
                'actor_name'     => $user->name,
                'actor_email'    => $user->email,
            ];
        }

        return [
            'actor_user_id'  => null,
            'actor_admin_id' => null,
            'actor_guard'    => null,
            'actor_name'     => null,
            'actor_email'    => null,
        ];
    }
}
