<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Api\Support\SessionTokenIssuer;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Services\UserRoleAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Bearer-token parity for the back-office admin tooling that powers the
 * seamless admin <-> user dashboard switch, the role / admin-access
 * assignment panel and user impersonation on the web app.
 *
 * On the web the operator is signed into the `admin` guard; on mobile the
 * Sanctum token authenticates a `web` User. The two auth pools are bridged
 * by email — {@see User::adminAccount()} — so the operator's authority for
 * every action here comes from that linked back-office Admin record and its
 * admin-guard role permissions, exactly mirroring the web flow. A mobile
 * user with an active linked admin account can therefore reach the admin
 * surfaces with the same token (no re-login), which is what "switching"
 * means in a token world.
 */
class AdminAccessController extends Controller
{
    use ApiResponses;

    /**
     * Admin context for the signed-in user: whether they have an active
     * back-office admin account and, if so, which admin actions their role
     * unlocks. Drives the mobile "Switch to admin dashboard" entry and the
     * per-action gating (role management, admin grant/revoke, impersonation).
     */
    public function context(Request $request)
    {
        $admin = $this->activeAdmin($request);

        if (! $admin) {
            return $this->ok([
                'has_admin_access' => false,
                'admin'            => null,
                'can'              => $this->capabilities(null),
            ]);
        }

        return $this->ok([
            'has_admin_access' => true,
            'admin'            => [
                'id'   => $admin->id,
                'name' => $admin->name,
                'role' => $admin->role ? [
                    'name'           => $admin->role->name,
                    'slug'           => $admin->role->slug,
                    'is_super_admin' => $admin->role->slug === 'super-admin',
                ] : null,
            ],
            'can' => $this->capabilities($admin),
        ]);
    }

    /**
     * Searchable, paginated user list for the assignment screen, mirroring
     * the back-office users index. Gated behind `users.view`.
     */
    public function users(Request $request)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('users.view')) {
            return $this->forbidden('You are not allowed to view users.');
        }

        $search  = trim((string) $request->get('search', ''));
        $perPage = 20;

        $query = User::query()->with('plan');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('handle', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query->latest()->paginate($perPage)->withQueryString();

        // Resolve linked admin accounts in one query rather than per-row so
        // the list can flag who already has back-office access.
        $emails = collect($paginator->items())
            ->map(fn (User $u) => strtolower(trim((string) $u->email)))
            ->filter()
            ->values()
            ->all();

        $adminsByEmail = [];
        if (! empty($emails)) {
            $adminsByEmail = Admin::query()
                ->whereIn('email', $emails)
                ->get()
                ->keyBy(fn (Admin $a) => strtolower(trim((string) $a->email)));
        }

        // Lowercased set of protected emails on this page so the mobile UI
        // can flag protected accounts and hide their delete/suspend controls
        // (defense in depth — the server also refuses regardless of the UI).
        $protectedEmails = [];
        if (! empty($emails)) {
            $protectedEmails = ProtectedAccount::query()
                ->whereIn('email', $emails)
                ->pluck('email')
                ->map(fn ($e) => strtolower(trim((string) $e)))
                ->flip()
                ->all();
        }

        // Id-keyed protected entries (email-less accounts) on this page.
        $protectedUserIds = ProtectedAccount::query()
            ->whereIn('user_id', collect($paginator->items())->pluck('id')->all())
            ->pluck('user_id')
            ->flip()
            ->all();

        $rows = collect($paginator->items())->map(function (User $u) use ($adminsByEmail, $protectedEmails, $protectedUserIds) {
            $key = strtolower(trim((string) $u->email));
            $linked = $adminsByEmail[$key] ?? null;
            return [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'handle'       => $u->handle,
                'avatar'       => $u->avatar,
                'status'       => $u->status,
                'plan'         => $u->plan?->name,
                'is_admin'     => $linked !== null,
                'admin_status' => $linked?->status,
                'is_protected' => isset($protectedEmails[$key]) || isset($protectedUserIds[$u->id]),
            ];
        })->all();

        return $this->ok([
            'users'    => $rows,
            'page'     => $paginator->currentPage(),
            'has_more' => $paginator->hasMorePages(),
            'total'    => $paginator->total(),
        ]);
    }

    /**
     * Role + admin-access panel data for one user, mirroring
     * {@see \App\Modules\Admin\Controllers\UserRoleController::edit}. Spells
     * out the feature permissions each assignable role grants so the mobile
     * screen can show availability exactly like the web panel. Gated behind
     * `users.edit`.
     */
    public function userRoles(Request $request, int $userId)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('users.edit')) {
            return $this->forbidden('You are not allowed to manage roles.');
        }

        $user = User::find($userId);
        if (! $user) {
            return $this->notFound('User not found.');
        }

        $roles = Role::query()
            ->where('guard', 'web')
            ->with(['permissions' => fn ($q) => $q->orderBy('group')->orderBy('name')])
            ->orderBy('name')
            ->get();

        $assigned = $user->roles()->pluck('roles.id')->map(fn ($v) => (int) $v)->all();

        $adminAccount = $user->adminAccount();
        $adminRoles   = Role::query()
            ->where('guard', 'admin')
            ->with(['permissions' => fn ($q) => $q->orderBy('group')->orderBy('name')])
            ->orderBy('name')
            ->get();

        return $this->ok([
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'is_protected' => ProtectedAccount::isProtected($user),
            ],
            'roles' => $roles->map(fn (Role $r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'slug'        => $r->slug,
                'description' => $r->description,
                'assigned'    => in_array((int) $r->id, $assigned, true),
                'permissions' => $r->permissions->map(fn ($p) => [
                    'name' => $p->name ?: $p->slug,
                    'slug' => $p->slug,
                ])->all(),
            ])->all(),
            'admin_account' => $adminAccount ? [
                'id'     => $adminAccount->id,
                'status' => $adminAccount->status,
                'role'   => $adminAccount->role ? [
                    'id'   => $adminAccount->role->id,
                    'name' => $adminAccount->role->name,
                    'slug' => $adminAccount->role->slug,
                ] : null,
            ] : null,
            'admin_roles' => $adminRoles->map(fn (Role $r) => [
                'id'                => $r->id,
                'name'              => $r->name,
                'slug'              => $r->slug,
                'is_super_admin'    => $r->slug === 'super-admin',
                'permissions_count' => $r->permissions->count(),
                'permissions'       => $r->permissions->map(fn ($p) => [
                    'name' => $p->name ?: $p->slug,
                    'slug' => $p->slug,
                ])->all(),
            ])->all(),
            'can_grant_admin'  => $admin->hasPermission('staff.create'),
            'can_revoke_admin' => $admin->hasPermission('staff.delete'),
        ]);
    }

    /**
     * Sync a user's web-guard roles, mirroring the web update() including the
     * audit-ledger diff. Gated behind `users.edit`.
     */
    public function updateRoles(Request $request, int $userId, UserRoleAuditLogger $auditLogger)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('users.edit')) {
            return $this->forbidden('You are not allowed to manage roles.');
        }

        $user = User::find($userId);
        if (! $user) {
            return $this->notFound('User not found.');
        }

        $validated = $request->validate([
            'role_ids'   => 'array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $ids = collect($validated['role_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->all();

        // Web-guard roles only — never let an admin-guard role leak onto a
        // user account through this endpoint.
        $webGuardIds = Role::query()
            ->where('guard', 'web')
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

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

        return $this->userRoles($request, $user->id);
    }

    /**
     * Promote a user to admin (or change their back-office role), mirroring
     * the web grantAdminAccess(). The chosen role must be admin-guard. Gated
     * behind `staff.create`.
     */
    public function grantAdminAccess(Request $request, int $userId)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('staff.create')) {
            return $this->forbidden('You are not allowed to grant admin access.');
        }

        $user = User::find($userId);
        if (! $user) {
            return $this->notFound('User not found.');
        }

        $validated = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $role = Role::query()
            ->where('guard', 'admin')
            ->find((int) $validated['role_id']);

        if (! $role) {
            return $this->fail('That is not a valid admin role.', 422, 'invalid_admin_role');
        }

        // Back-office accounts are keyed by email. A mobile/WhatsApp-only
        // sign-up has no users.email yet — the user must add + verify an
        // email (Linked identifiers) before they can be promoted.
        if (trim((string) $user->email) === '') {
            return $this->fail(
                'This user has no email address on file. Ask them to add and verify an email in Account Settings first, then grant admin access.',
                422,
                'user_email_required'
            );
        }

        $linked = $user->adminAccount();

        if ($linked) {
            $linked->update(['role_id' => $role->id, 'status' => 'active']);
        } else {
            Admin::create([
                'name'     => $user->name,
                'email'    => $user->email,
                // Random password — this account signs in via the dashboard
                // switch / OTP, never with a known password.
                'password' => Hash::make(Str::random(40)),
                'role_id'  => $role->id,
                'status'   => 'active',
            ]);
        }

        $user->flushAdminAccountCache();

        return $this->userRoles($request, $user->id);
    }

    /**
     * Revoke a user's back-office admin access, mirroring the web
     * revokeAdminAccess(). Gated behind `staff.delete`.
     */
    public function revokeAdminAccess(Request $request, int $userId, AdminActionLogger $audit)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('staff.delete')) {
            return $this->forbidden('You are not allowed to revoke admin access.');
        }

        $user = User::find($userId);
        if (! $user) {
            return $this->notFound('User not found.');
        }

        $linked = $user->adminAccount();
        if (! $linked) {
            return $this->fail('This user does not have admin access.', 422, 'no_admin_access');
        }

        // An operator must never revoke their own admin access mid-session.
        if ((int) $linked->id === (int) $admin->id) {
            return $this->fail('You cannot revoke your own admin access.', 422, 'self_revoke');
        }

        // Deleting the back-office admin record for a protected account is a
        // delete in disguise — refuse it server-side regardless of which
        // surface initiated it (mirrors the web StaffController guard).
        if (ProtectedAccount::isProtected($user) || ProtectedAccount::isProtected($linked)) {
            $audit->log(AdminActionLogger::DELETE_BLOCKED, $user, [
                'email'  => $user->email,
                'reason' => 'Account is protected and its admin access cannot be revoked.',
            ], $admin);
            return $this->fail(
                'This account is protected and its admin access cannot be revoked.',
                422,
                'account_protected'
            );
        }

        $linked->delete();
        $user->flushAdminAccountCache();

        return $this->userRoles($request, $user->id);
    }

    /**
     * Impersonate a user, mirroring the web impersonation flow. On mobile
     * impersonation means issuing a fresh bearer token for the target user
     * that the app swaps in (and swaps back out on "stop"), so the operator
     * sees the target's dashboard without re-login. Gated behind
     * `users.impersonate`.
     */
    public function impersonate(Request $request, int $userId)
    {
        $admin = $this->activeAdmin($request);
        if (! $admin || ! $admin->hasPermission('users.impersonate')) {
            return $this->forbidden('You are not allowed to impersonate users.');
        }

        $target = User::find($userId);
        if (! $target) {
            return $this->notFound('User not found.');
        }

        if (($target->status ?? 'active') !== 'active') {
            return $this->fail('That account is not active.', 422, 'target_inactive');
        }

        // Mint a normal mobile-kind token (not metered as an api_key) for the
        // target. We deliberately do NOT fire a login alert — impersonation is
        // an operator action, not the target signing in.
        $issued = SessionTokenIssuer::issue($target, $request, 'Impersonation session', 'mobile', 'mobile');

        return $this->ok([
            'token' => $issued->plainTextToken,
            'user'  => UserResource::toArray($target, self: true),
        ]);
    }

    /**
     * The signed-in user's active back-office Admin record, or null.
     */
    protected function activeAdmin(Request $request): ?Admin
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return null;
        }

        $admin = $user->adminAccount();
        return ($admin && $admin->status === 'active') ? $admin : null;
    }

    /**
     * Per-action capability map for an operator admin (or all-false when
     * there is no admin context).
     *
     * @return array<string,bool>
     */
    protected function capabilities(?Admin $admin): array
    {
        return [
            'view_users'       => (bool) $admin?->hasPermission('users.view'),
            'manage_roles'     => (bool) $admin?->hasPermission('users.edit'),
            'grant_admin'      => (bool) $admin?->hasPermission('staff.create'),
            'revoke_admin'     => (bool) $admin?->hasPermission('staff.delete'),
            'impersonate'      => (bool) $admin?->hasPermission('users.impersonate'),
            // Protected-accounts list: staff with users.view may read it,
            // only a super-admin may add/remove entries (mirrors the web).
            'view_protected'   => (bool) $admin?->hasPermission('users.view'),
            'manage_protected' => (bool) ($admin && $admin->isSuperAdmin()),
            // Analytics-storage panel + mail/platform settings parity.
            'manage_settings'  => (bool) $admin?->hasPermission('settings.manage'),
        ];
    }
}
