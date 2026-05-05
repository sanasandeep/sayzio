<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Services\UserRoleAuditCsvExporter;
use App\Modules\User\Services\UserRoleAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Optional `?audit_source=` chip filter on the timeline below.
        // Normalise here so the same value can drive the query AND the
        // view's "active chip" highlighting without re-validating.
        $auditSource = UserRoleAudit::normaliseSourceFilter($request->get('audit_source'));

        // Recent role changes across the user pool. Surfaced to anyone
        // who can see this page (i.e. holders of `user.roles.manage`)
        // so promote/demote actions are no longer invisible.
        $audits = UserRoleAudit::query()
            ->with(['actorUser:id,name,email', 'actorAdmin:id,name,email', 'targetUser:id,name,email'])
            ->bySourceFilter($auditSource)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('user.access.users', [
            'roles'        => $roles,
            'users'        => $users,
            'search'       => $search,
            'audits'       => $audits,
            'auditSource'  => $auditSource,
            'auditFilters' => UserRoleAudit::sourceFilters(),
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

    /**
     * Dedicated, paginated audit page covering the entire
     * `user_role_audits` ledger. The two snapshot panels (50 most
     * recent on User access, 20 latest per user on the admin
     * user-detail page) are intentionally tiny — this page is the
     * place to dig into the full history with filters and CSV export
     * for security reviews.
     */
    public function audit(Request $request)
    {
        $filters = $this->auditFilters($request);

        $audits = UserRoleAudit::query()
            ->with(['actorUser:id,name,email', 'actorAdmin:id,name,email', 'targetUser:id,name,email'])
            ->filtered($filters)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('user.access.audit', [
            'audits'     => $audits,
            'filters'    => $filters,
            'roleSlugs'  => UserRoleAudit::distinctRoleSlugs(),
            'actions'    => [
                UserRoleAudit::ACTION_ATTACHED => 'Granted',
                UserRoleAudit::ACTION_DETACHED => 'Revoked',
            ],
            'sources'    => [
                UserRoleAudit::SOURCE_USER_ACCESS => 'User access page',
                UserRoleAudit::SOURCE_ADMIN       => 'Back-office admin',
            ],
            'exportRoute' => 'user.access.audit.export',
            'backRoute'   => 'user.access.users.index',
        ]);
    }

    /**
     * CSV export of the same filtered audit query the page renders.
     * Streamed/chunked so a multi-thousand-row export doesn't blow
     * the request memory limit.
     */
    public function auditExport(Request $request): StreamedResponse
    {
        $filters = $this->auditFilters($request);

        $query = UserRoleAudit::query()->filtered($filters);

        $filename = 'role-audit-' . now()->format('Ymd-His') . '.csv';
        return UserRoleAudit::streamCsv($query, $filename);
    }

    /**
     * Read the supported filter inputs off the request once, so the
     * on-screen list and the CSV export reflect the exact same view.
     *
     * @return array{actor:string,target:string,role:string,action:string,source:string,from:string,to:string}
     */
    protected function auditFilters(Request $request): array
    {
        return [
            'actor'  => trim((string) $request->get('actor', '')),
            'target' => trim((string) $request->get('target', '')),
            'role'   => trim((string) $request->get('role', '')),
            'action' => (string) $request->get('action', ''),
            'source' => (string) $request->get('source', ''),
            'from'   => trim((string) $request->get('from', '')),
            'to'     => trim((string) $request->get('to', '')),
        ];
    }

    /**
     * One-click CSV download wired to the small "Recent role changes"
     * panel on this page. Covers every audit row across the user pool
     * — not just the latest 50 surfaced inline — so reviewers can pull
     * the full history without first navigating to the dedicated
     * audit page (`audit()`) and clearing all filters.
     *
     * Lives alongside `auditExport()` rather than replacing it: the
     * filtered exporter on the audit page is the right tool when you
     * already know what you're looking for; this one is the right
     * tool when you just want everything. Mirrors the access gate of
     * `index()` (the `user.roles.manage` permission applied at the
     * route layer).
     */
    public function export(UserRoleAuditCsvExporter $exporter): StreamedResponse
    {
        $filename = 'role-change-audit-' . date('Ymd-His') . '.csv';

        return $exporter->streamResponse(UserRoleAudit::query(), $filename);
    }
}
