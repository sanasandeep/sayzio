<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Services\UserRoleAuditLogger;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function edit(User $user)
    {
        $roles = Role::query()
            ->where('guard', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        $assigned = $user->roles()->pluck('roles.id')->all();

        // Per-user role-change history. Surfaced to anyone with
        // `users.edit` (the existing route guard) so back-office
        // operators can see who promoted/demoted this user before.
        $audits = UserRoleAudit::query()
            ->with(['actorUser:id,name,email', 'actorAdmin:id,name,email'])
            ->where('target_user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.users.roles', [
            'user'     => $user,
            'roles'    => $roles,
            'assigned' => $assigned,
            'audits'   => $audits,
        ]);
    }

    public function update(Request $request, User $user, UserRoleAuditLogger $auditLogger)
    {
        $validated = $request->validate([
            'role_ids'   => 'array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $ids = collect($validated['role_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->all();

        // Restrict to web-guard roles only — the admin guard is for the
        // back-office Admin model and must not leak onto user accounts.
        $webGuardIds = Role::query()
            ->where('guard', 'web')
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        // Snapshot the previous role set BEFORE sync so we can diff
        // for the audit ledger. Web-guard scoped on both sides so an
        // unrelated admin-guard role attached to the same user pivot
        // (defensive) doesn't show up as a phantom detach.
        $previousRoleIds = $user->roles()
            ->where('guard', 'web')
            ->pluck('roles.id')
            ->all();

        $user->roles()->sync($webGuardIds);
        $user->flushPermissionCache();

        $auditLogger->recordDiff(
            $user,
            $previousRoleIds,
            $webGuardIds,
            UserRoleAudit::SOURCE_ADMIN,
            $request->ip(),
        );

        return redirect()
            ->route('admin.users.roles.edit', $user)
            ->with('success', 'Roles updated for ' . $user->name . '.');
    }
}
