<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Single entrypoint that controllers use after `$user->roles()->sync(...)`
 * to record one audit row per role added or removed. Keeping the diff
 * computation here means both `UserAccessController` (web guard) and
 * `UserRoleController` (admin guard) write rows in the same shape, so
 * a single timeline view can display them together.
 *
 * Failures are logged and swallowed — an audit miss must never break
 * the user-facing role update.
 */
class UserRoleAuditLogger
{
    /**
     * Diff `$beforeRoleIds` against `$afterRoleIds` and append one
     * audit row per role that was attached or detached.
     *
     * `$source` should be one of `UserRoleAudit::SOURCE_*`, identifying
     * which surface (self-service vs back-office) made the change.
     *
     * @param array<int> $beforeRoleIds
     * @param array<int> $afterRoleIds
     */
    public function recordDiff(
        User $target,
        array $beforeRoleIds,
        array $afterRoleIds,
        string $source,
        ?string $ip = null,
    ): void {
        $before = array_values(array_unique(array_map('intval', $beforeRoleIds)));
        $after  = array_values(array_unique(array_map('intval', $afterRoleIds)));

        $attached = array_values(array_diff($after, $before));
        $detached = array_values(array_diff($before, $after));

        if (empty($attached) && empty($detached)) {
            return;
        }

        // Single query covers both directions so we can label each row
        // with the role slug/name even if the role is deleted moments
        // later (we snapshot the values at write time).
        $allIds = array_unique(array_merge($attached, $detached));
        $roles  = Role::query()->whereIn('id', $allIds)->get()->keyBy('id');

        $actor = $this->resolveActor();

        try {
            foreach ($attached as $id) {
                $this->writeRow($target, $roles->get($id), $id, UserRoleAudit::ACTION_ATTACHED, $source, $actor, $ip);
            }
            foreach ($detached as $id) {
                $this->writeRow($target, $roles->get($id), $id, UserRoleAudit::ACTION_DETACHED, $source, $actor, $ip);
            }
        } catch (\Throwable $e) {
            Log::error('UserRoleAuditLogger: failed to append audit row', [
                'target_user_id' => $target->id,
                'source'         => $source,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Inspect the currently-authenticated principal across both
     * guards (`web` user vs `admin` Admin) and return a small
     * actor descriptor. Returning null is fine — the audit row
     * still records "System" in that case.
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

    protected function writeRow(
        User $target,
        ?Role $role,
        int $roleId,
        string $action,
        string $source,
        array $actor,
        ?string $ip,
    ): void {
        UserRoleAudit::create(array_merge($actor, [
            'target_user_id' => $target->id,
            'role_id'        => $role?->id ?? $roleId,
            // Slug is mandatory in the schema — fall back to the id
            // if the role record vanished mid-flight.
            'role_slug'      => $role?->slug ?? ('role#' . $roleId),
            'role_name'      => $role?->name,
            'action'         => $action,
            'source'         => $source,
            'ip'             => $ip,
            'created_at'     => now(),
        ]));
    }
}
