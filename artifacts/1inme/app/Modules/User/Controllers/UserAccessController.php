<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Services\UserRoleAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserAccessController extends Controller
{
    /**
     * Self-service "user access" page where holders of
     * `user.roles.manage` can promote/demote other users on the user
     * pool. Lists only users that already hold at least one user-pool
     * role plus a search box for adding others, so the page doesn't
     * have to render the entire user table.
     */
    public function index(Request $request)
    {
        $roles = Role::query()
            ->where('guard', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        $search = trim((string) $request->get('q', ''));

        $query = User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name');

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)
                  ->orWhere('users.email', 'like', $like);
            })->limit(50);
        } else {
            // Default view: only users that already have at least one
            // role attached. Avoids dumping the full users table.
            $query->whereHas('roles', fn ($q) => $q->where('guard', 'web'))
                  ->limit(200);
        }

        $users = $query->with(['roles' => fn ($q) => $q->where('guard', 'web')])->get();

        // Recent role changes across the user pool. Surfaced to anyone
        // who can see this page (i.e. holders of `user.roles.manage`)
        // so promote/demote actions are no longer invisible.
        $audits = UserRoleAudit::query()
            ->with(['actorUser:id,name,email', 'actorAdmin:id,name,email', 'targetUser:id,name,email'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('user.access.users', [
            'roles'  => $roles,
            'users'  => $users,
            'search' => $search,
            'audits' => $audits,
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

        $webGuardIds = Role::query()
            ->where('guard', 'web')
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        // Self-lockout protection: if the operator is editing their own
        // account, refuse any change that would strip their last role
        // granting `user.roles.manage`. Without this check, a user
        // could one-click revoke their own access and lose the only
        // way back into this page.
        $actor = Auth::user();
        if ($actor && (int) $actor->id === (int) $user->id) {
            $managerRoleIds = Role::query()
                ->where('guard', 'web')
                ->whereHas('permissions', fn ($q) => $q->where('slug', 'user.roles.manage'))
                ->pluck('id')
                ->all();

            $keepsManager = !empty(array_intersect($managerRoleIds, $webGuardIds));
            if (!$keepsManager) {
                throw ValidationException::withMessages([
                    'role_ids' => 'You can\'t remove your own role-management access. Ask another administrator to do it.',
                ]);
            }
        }

        // Snapshot the previous role set BEFORE sync so we can diff
        // for the audit log. We restrict to web-guard ids on both
        // sides so admin-guard roles (irrelevant here) don't leak in.
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
            UserRoleAudit::SOURCE_USER_ACCESS,
            $request->ip(),
        );

        return redirect()
            ->route('user.access.users.index', ['q' => $request->get('q')])
            ->with('success', 'Access updated for ' . $user->name . '.');
    }
}
