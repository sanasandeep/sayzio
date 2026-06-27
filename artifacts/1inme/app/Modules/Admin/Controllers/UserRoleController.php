<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Models\UserRoleAuditExport;
use App\Modules\User\Services\UserRoleAuditCsvExporter;
use App\Modules\User\Services\UserRoleAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserRoleController extends Controller
{
    public function edit(Request $request, User $user)
    {
        // Web-guard roles assignable to this user, each eager-loaded with
        // its permissions so the screen can spell out exactly what feature
        // access each role grants (Part 1: "show each role's availability").
        $roles = Role::query()
            ->where('guard', 'web')
            ->with(['permissions' => fn ($q) => $q->orderBy('group')->orderBy('name')])
            ->orderBy('name')
            ->get();

        $assigned = $user->roles()->pluck('roles.id')->all();

        // Admin-access panel data. The admin pool is a separate table
        // (admin guard) linked to a user by email; promoting a user to
        // admin creates/repoints that record so the same person can use
        // the back-office and the seamless dashboard switch.
        $operator        = Auth::guard('admin')->user();
        $adminAccount    = $user->adminAccount();
        $adminRoles      = Role::query()
            ->where('guard', 'admin')
            ->with(['permissions' => fn ($q) => $q->orderBy('group')->orderBy('name')])
            ->orderBy('name')
            ->get();
        $canGrantAdmin   = $operator && $operator->hasPermission('users.grant_admin');
        $canRevokeAdmin  = $operator && $operator->hasPermission('users.revoke_admin');
        $canAssignRoles  = $operator && $operator->hasPermission('users.assign_roles');

        // Per-user role-change history. Surfaced to anyone with
        // `users.edit` (the existing route guard) so back-office
        // operators can see who promoted/demoted this user before.
        //
        // The panel exposes the same simple filter controls as the
        // self-service "User access" page (date range, actor, role,
        // action, source) so reviewers can narrow a per-user history
        // before exporting. The "target" filter is implicit here —
        // the query is already scoped to `$user`.
        $auditFilters = $this->panelFilters($request);

        $audits = UserRoleAudit::query()
            ->with(['actorUser:id,name,email', 'actorAdmin:id,name,email'])
            ->where('target_user_id', $user->id)
            ->bySourceFilter(UserRoleAudit::normaliseSourceFilter($auditFilters['audit_source']))
            ->betweenDates(
                UserRoleAudit::normaliseRangePreset($auditFilters['audit_range']),
                $auditFilters['audit_from'],
                $auditFilters['audit_to'],
            )
            ->filtered([
                'actor'  => $auditFilters['actor'],
                'role'   => $auditFilters['role'],
                'action' => $auditFilters['action'],
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.users.roles', [
            'user'           => $user,
            'roles'          => $roles,
            'assigned'       => $assigned,
            'adminAccount'   => $adminAccount,
            'adminRoles'     => $adminRoles,
            'canGrantAdmin'  => $canGrantAdmin,
            'canRevokeAdmin' => $canRevokeAdmin,
            'canAssignRoles' => $canAssignRoles,
            'audits'         => $audits,
            'auditFilters'   => $auditFilters,
            'auditRoleSlugs' => UserRoleAudit::distinctRoleSlugs(),
            'auditActions'   => UserRoleAudit::actionLabels(),
            'auditSources'   => UserRoleAudit::sourceFilters(),
            'auditRanges'    => UserRoleAudit::rangeFilters(),
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

    /**
     * Promote a user to admin (or change their back-office role).
     *
     * The admin pool is a separate table (admin guard). We create — or
     * repoint — an Admin record matching this user's name/email so the
     * same person can sign into the back-office and use the seamless
     * dashboard switch. The chosen role must be an admin-guard role.
     */
    public function grantAdminAccess(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $role = Role::query()
            ->where('guard', 'admin')
            ->find((int) $validated['role_id']);

        if (! $role) {
            return back()->with('error', 'That is not a valid admin role.');
        }

        $admin = $user->adminAccount();

        if ($admin) {
            $admin->update([
                'role_id' => $role->id,
                'status'  => 'active',
            ]);
            $message = $user->name . ' is now a ' . $role->name . ' admin.';
        } else {
            Admin::create([
                'name'     => $user->name,
                'email'    => $user->email,
                // Random password — this account signs in via the
                // dashboard switch / OTP, never with a known password.
                'password' => Hash::make(Str::random(40)),
                'role_id'  => $role->id,
                'status'   => 'active',
            ]);
            $message = $user->name . ' was promoted to admin (' . $role->name . ').';
        }

        $user->flushAdminAccountCache();

        // When the grant was launched from the inline "Promote existing
        // user" control on the Staff page, keep the operator there rather
        // than bouncing them to this user's role page. Only a known-safe
        // internal target is honoured.
        if ($request->input('redirect_to') === 'staff') {
            return redirect()
                ->route('admin.staff.index')
                ->with('success', $message);
        }

        return redirect()
            ->route('admin.users.roles.edit', $user)
            ->with('success', $message);
    }

    /**
     * Revoke a user's back-office admin access by deleting the matching
     * Admin record. The user account itself is untouched.
     */
    public function revokeAdminAccess(User $user)
    {
        $admin = $user->adminAccount();

        if (! $admin) {
            return back()->with('error', 'This user does not have admin access.');
        }

        // Guard against an operator removing their own admin access while
        // signed in — that would lock them out mid-request.
        $operator = Auth::guard('admin')->user();
        if ($operator && (int) $operator->id === (int) $admin->id) {
            return back()->with('error', 'You cannot revoke your own admin access.');
        }

        $admin->delete();
        $user->flushAdminAccountCache();

        return redirect()
            ->route('admin.users.roles.edit', $user)
            ->with('success', 'Admin access revoked for ' . $user->name . '.');
    }

    /**
     * Stream every role-change audit row for a single user as a CSV
     * download. Scoped strictly to `$user` so the export matches the
     * panel on `admin.users.{show,roles.edit}` and never leaks rows
     * for accounts the operator might not be looking at.
     *
     * Honours the same filter inputs the panel exposes (date range,
     * actor, role, action, source) so the download mirrors exactly
     * what the reviewer is looking at on screen.
     *
     * Gated by the `users.edit` permission at the route layer, the
     * same check the timeline panel itself uses.
     */
    public function export(Request $request, User $user, UserRoleAuditCsvExporter $exporter): StreamedResponse
    {
        $filters = $this->panelFilters($request);

        $filename = 'role-change-audit-user-' . $user->id . '-' . date('Ymd-His') . '.csv';

        $query = UserRoleAudit::query()
            ->where('target_user_id', $user->id)
            ->bySourceFilter(UserRoleAudit::normaliseSourceFilter($filters['audit_source']))
            ->betweenDates(
                UserRoleAudit::normaliseRangePreset($filters['audit_range']),
                $filters['audit_from'],
                $filters['audit_to'],
            )
            ->filtered([
                'actor'  => $filters['actor'],
                'role'   => $filters['role'],
                'action' => $filters['action'],
            ]);

        return $exporter->streamResponse($query, $filename, [
            'scope'          => UserRoleAuditExport::SCOPE_SINGLE_USER,
            'target_user_id' => $user->id,
            'request'        => $request,
        ]);
    }

    /**
     * Filter inputs surfaced by the role-change panel on the
     * back-office user pages. Mirrors the shape the user-access
     * panel uses so the same view partials and CSV exporter can be
     * reused without controller-level branching.
     *
     * @return array{actor:string,role:string,action:string,source:string,from:string,to:string}
     */
    protected function panelFilters(Request $request): array
    {
        return [
            'actor'        => trim((string) $request->get('actor', '')),
            'role'         => trim((string) $request->get('role', '')),
            'action'       => (string) $request->get('action', ''),
            'audit_source' => (string) ($request->get('audit_source', '') ?? ''),
            'audit_range'  => (string) ($request->get('audit_range', '') ?? ''),
            'audit_from'   => trim((string) $request->get('audit_from', '')),
            'audit_to'     => trim((string) $request->get('audit_to', '')),
        ];
    }
}
