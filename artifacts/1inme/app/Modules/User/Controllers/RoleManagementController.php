<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Companion to UserAccessController. UserAccessController handles
 * "which roles does this *user* hold"; this controller handles
 * "which permissions does this *role* hold". Both pages are gated by
 * `user.roles.manage` so an operator with that permission can fully
 * self-serve role definitions instead of editing the seed migration.
 *
 * Scope:
 *  - Only `web`-guard roles (the user pool). Admin-guard roles belong
 *    to the back-office and are managed separately.
 *  - Permission checklist is restricted to the `user-app` group so
 *    operators can't accidentally attach a back-office permission to
 *    a user-pool role.
 */
class RoleManagementController extends Controller
{
    /**
     * Slug of the permission that gates this entire controller.
     * Pulled out as a constant so the self-lockout checks below stay
     * in sync with the route middleware in routes/modules/user.php.
     */
    private const MANAGER_PERMISSION = 'user.roles.manage';

    /** Permission group this UI is allowed to attach to a role. */
    private const PERMISSION_GROUP = 'user-app';

    public function index()
    {
        $roles = Role::query()
            ->where('guard', 'web')
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'guard']);

        return view('user.access.roles.index', [
            'roles' => $roles,
        ]);
    }

    public function create()
    {
        $role = new Role(['guard' => 'web']);
        return view('user.access.roles.edit', [
            'role'        => $role,
            'permissions' => $this->groupedPermissions(),
            'assigned'    => [],
            'isNew'       => true,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRolePayload($request);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'] ?? null,
                'guard'       => 'web',
            ]);
            $role->permissions()->sync($data['permission_ids']);
            return $role;
        });

        return redirect()
            ->route('user.access.roles.edit', $role)
            ->with('success', 'Role "' . $role->name . '" created.');
    }

    public function edit(Role $role)
    {
        $this->ensureWebGuard($role);

        $assigned = $role->permissions()->pluck('permissions.id')->all();

        return view('user.access.roles.edit', [
            'role'        => $role,
            'permissions' => $this->groupedPermissions(),
            'assigned'    => $assigned,
            'isNew'       => false,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureWebGuard($role);
        $data = $this->validateRolePayload($request, $role);

        // Self-lockout: if the actor currently holds `user.roles.manage`
        // *only* via this role, refuse a save that drops it from the
        // checklist. Without this check, the operator could one-click
        // strip their own access to this very page.
        $this->preventSelfLockout($role, $data['permission_ids']);

        DB::transaction(function () use ($role, $data) {
            $role->update([
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'] ?? null,
            ]);
            $role->permissions()->sync($data['permission_ids']);
        });

        // Permission cache is request-scoped on the User model, so we
        // can only clear it for users currently loaded in memory. The
        // attached users will pick up the change on their next request,
        // which is exactly when their cache is rebuilt anyway. Still,
        // we touch every user's `updated_at` so any external cache that
        // keys on it (or future warm caches) gets invalidated.
        $this->bumpAttachedUsers($role);

        return redirect()
            ->route('user.access.roles.edit', $role)
            ->with('success', 'Role "' . $role->name . '" updated.');
    }

    public function destroy(Role $role)
    {
        $this->ensureWebGuard($role);

        // Refuse to delete a role still in use. Operators must first
        // detach users (via the User access page) so the deletion is a
        // deliberate two-step action, not a silent mass-revocation.
        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return redirect()
                ->route('user.access.roles.index')
                ->withErrors(['role' => 'Cannot delete "' . $role->name . '" while ' . $userCount . ' user(s) still hold it. Detach them on the User access page first.']);
        }

        // Self-lockout: simulate "this role no longer grants
        // user.roles.manage" and confirm the actor still has it via
        // some other role.
        $this->preventSelfLockout($role, []);

        $role->delete();

        return redirect()
            ->route('user.access.roles.index')
            ->with('success', 'Role deleted.');
    }

    /* ------------------------------------------------------------------ */
    /* helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, array{0:int,1:string,2:string,3:?string}>
     *         Returns permissions grouped by `group`, but for now there
     *         is only `user-app`. Kept as a grouped structure so the
     *         view doesn't need changing if more groups appear.
     */
    private function groupedPermissions(): array
    {
        return Permission::query()
            ->where('group', self::PERMISSION_GROUP)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'group'])
            ->groupBy('group')
            ->map(fn ($items) => $items->values())
            ->all();
    }

    /**
     * Validates incoming role payload. Used by store + update.
     *
     * @return array{name:string, slug:string, description:?string, permission_ids:array<int,int>}
     */
    private function validateRolePayload(Request $request, ?Role $existing = null): array
    {
        $rawSlug = (string) $request->input('slug', '');
        $autoSlug = $rawSlug !== ''
            ? Str::slug($rawSlug)
            : Str::slug((string) $request->input('name', ''));
        $request->merge(['slug' => $autoSlug]);

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:80'],
            'slug'             => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('roles', 'slug')
                    ->where(fn ($q) => $q->where('guard', 'web'))
                    ->ignore($existing?->id),
            ],
            'description'      => ['nullable', 'string', 'max:255'],
            'permission_ids'   => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ], [
            'slug.regex' => 'Slug may only contain lowercase letters, numbers and dashes.',
        ]);

        // Restrict to the user-app permission group — block any attempt
        // to attach a back-office permission to a user-pool role.
        $allowedIds = Permission::query()
            ->where('group', self::PERMISSION_GROUP)
            ->whereIn('id', collect($validated['permission_ids'] ?? [])->map('intval')->all())
            ->pluck('id')
            ->all();

        return [
            'name'           => $validated['name'],
            'slug'           => $validated['slug'],
            'description'    => $validated['description'] ?? null,
            'permission_ids' => array_values(array_unique($allowedIds)),
        ];
    }

    private function ensureWebGuard(Role $role): void
    {
        if ($role->guard !== 'web') {
            abort(404);
        }
    }

    /**
     * Refuse a change that would leave the *current operator* without
     * the `user.roles.manage` permission. Used by both update (with the
     * proposed permission set) and destroy (with an empty set, to
     * simulate the role being gone).
     *
     * @param array<int,int> $proposedPermissionIds
     */
    private function preventSelfLockout(Role $role, array $proposedPermissionIds): void
    {
        $actor = Auth::user();
        if (!$actor) return;

        $managerPermissionId = Permission::query()
            ->where('slug', self::MANAGER_PERMISSION)
            ->value('id');
        if (!$managerPermissionId) return;

        $actorRoleIds = $actor->roles()->where('guard', 'web')->pluck('roles.id')->all();
        if (!in_array($role->id, $actorRoleIds, true)) {
            // Actor isn't even attached to this role; their access is
            // unaffected by what permissions it holds.
            return;
        }

        // Does any *other* role the actor holds still grant manage?
        $otherRoleIds = array_values(array_diff($actorRoleIds, [$role->id]));
        $stillHasItElsewhere = !empty($otherRoleIds) && DB::table('role_permissions')
            ->whereIn('role_id', $otherRoleIds)
            ->where('permission_id', $managerPermissionId)
            ->exists();

        $thisRoleStillGrantsIt = in_array((int) $managerPermissionId, array_map('intval', $proposedPermissionIds), true);

        if (!$stillHasItElsewhere && !$thisRoleStillGrantsIt) {
            throw ValidationException::withMessages([
                'permission_ids' => 'You can\'t remove "' . self::MANAGER_PERMISSION . '" from this role — it\'s the only role granting you access to manage roles. Ask another administrator to do it.',
            ]);
        }
    }

    /**
     * Touch every attached user's `updated_at` so any external cache
     * keyed on it gets invalidated, and explicitly flush the in-memory
     * permission cache for any models still hydrated in memory.
     */
    private function bumpAttachedUsers(Role $role): void
    {
        $userIds = $role->users()->pluck('users.id')->all();
        if (empty($userIds)) return;

        DB::table('users')->whereIn('id', $userIds)->update(['updated_at' => now()]);

        // Flush the actor's own cache eagerly so the new permission set
        // is reflected on the very next request handled by this PHP
        // process (e.g. the redirect target after this update).
        $actor = Auth::user();
        if ($actor && in_array((int) $actor->id, array_map('intval', $userIds), true)) {
            $actor->flushPermissionCache();
        }
    }
}
