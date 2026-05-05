<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspacePermissions;
use Closure;
use Illuminate\Http\Request;

/**
 * Gate a route by a workspace permission, e.g.
 *   Route::middleware('workspace.can:posts.create')->...
 *
 * Owner of the active workspace (and super-admins) bypass; everyone else
 * must have the listed permission(s) on their membership.
 *
 * If multiple permissions are listed, ANY one of them grants access.
 * Returns 403 with a structured JSON payload on AJAX/JSON requests so the
 * UI can show a friendly explanation instead of a raw error page. For
 * normal browser requests, renders a branded "no access" page that shows
 * the user's current role, the workspace name, and a button to ask the
 * workspace owner for access.
 */
class RequireWorkspacePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        /** @var Workspace|null $ws */
        $ws = app()->bound('current_workspace') ? app('current_workspace') : null;
        if (!$ws) {
            // No workspace context — usually a misconfigured route. Deny.
            return $this->deny($request, 'no_workspace', null, null);
        }

        if ($user->hasPermission('user.workspaces.access_any') || (int) $ws->owner_user_id === $user->id) {
            return $next($request);
        }

        $membership = $user->membershipFor($ws);
        if (!$membership) {
            return $this->deny($request, 'not_a_member', $ws, null);
        }

        foreach ($permissions as $perm) {
            if ($membership->can($perm)) {
                return $next($request);
            }
        }
        return $this->deny($request, 'missing_permission', $ws, $membership->role, $permissions);
    }

    protected function deny(Request $request, string $reason, ?Workspace $workspace, ?string $role, array $permissions = [])
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'error'       => 'forbidden',
                'reason'      => $reason,
                'permissions' => $permissions,
            ], 403);
        }

        $permissionLabels = array_map(
            fn ($p) => WorkspacePermissions::permissionLabel($p),
            $permissions
        );

        $grantorRoles = [];
        foreach ($permissions as $perm) {
            $lowest = WorkspacePermissions::lowestRoleFor($perm);
            if ($lowest) {
                $grantorRoles[] = self::roleLabel($lowest);
            }
        }
        $grantorRoles = array_values(array_unique($grantorRoles));

        // Only show the owner contact block when a teammate is missing a
        // permission they could plausibly be granted. For "not a member" /
        // "no workspace" denials we omit it to avoid leaking owner email
        // to people who aren't part of the workspace.
        $owner = ($workspace && $reason === 'missing_permission') ? $workspace->owner : null;

        return response()->view('user.errors.no-workspace-permission', [
            'reason'           => $reason,
            'workspace'        => $workspace,
            'role'             => $role,
            'roleLabel'        => $role ? self::roleLabel($role) : null,
            'permissions'      => $permissions,
            'permissionLabels' => $permissionLabels,
            'grantorRoles'     => $grantorRoles,
            'owner'            => $owner,
        ], 403);
    }

    protected static function roleLabel(string $role): string
    {
        $known = [
            'admin'   => 'Admin',
            'editor'  => 'Editor',
            'replier' => 'Replier',
            'analyst' => 'Analyst',
            'viewer'  => 'Viewer',
            'custom'  => 'Custom role',
        ];
        return $known[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
}
