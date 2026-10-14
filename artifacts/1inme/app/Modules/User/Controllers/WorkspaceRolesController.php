<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use App\Modules\User\Services\WorkspacePermissionCatalog;
use App\Modules\User\Services\WorkspaceRoleDirectory;
use App\Modules\User\Services\WorkspaceRoleMatrix;
use Illuminate\Http\Request;

/**
 * "Roles & Permissions" settings screen for the active workspace.
 *
 * Reachable by the workspace Owner and any member with the Admin role.
 * Workspace-level destructive actions (delete workspace, billing,
 * ownership transfer) stay owner-only and are NOT covered by this matrix
 * — see `RequireWorkspaceOwner` for those.
 */
class WorkspaceRolesController extends Controller
{
    /** Owner or admin only. */
    protected function workspace(Request $request): Workspace
    {
        $ws = app('current_workspace');
        $user = $request->user();
        $isOwner = (int) $ws->owner_user_id === (int) $user->id
            || (method_exists($user, 'hasPermission') && $user->hasPermission('user.workspaces.access_any'));
        $membership = $user->membershipFor($ws);
        $isAdmin = ! $isOwner && $membership && $membership->role === 'admin' && ! $membership->isSuspended();
        abort_unless($isOwner || $isAdmin,
            403, 'Only the workspace owner or an Admin can edit roles.');

        return $ws;
    }

    public function index(Request $request)
    {
        $ws = $this->workspace($request);

        return view('user.team.roles', [
            'workspace' => $ws,
            'roles' => WorkspaceRoleMatrix::roles(),
            'actions' => array_merge(WorkspaceRoleMatrix::actions(), ['export', 'publish', 'run']),
            'features' => WorkspacePermissionCatalog::features(),
            'roleNames' => WorkspaceRoleDirectory::names($ws),
            'selectedRole' => in_array($request->query('role'), WorkspaceRoleMatrix::roles()) ? $request->query('role') : 'admin',
            'matrix' => WorkspaceRoleMatrix::forWorkspace($ws),
            'defaults' => WorkspaceRoleMatrix::defaults(),
            'audits' => WorkspaceRoleMatrix::recentAudits($ws, 10),
        ]);
    }

    public function update(Request $request)
    {
        $ws = $this->workspace($request);

        // Whitelisted shape: matrix[<role>][<action>] = "1"|"0"|on. Anything
        // else is rejected by `WorkspaceRoleMatrix::save()` so a hostile
        // payload can't sneak unknown roles or actions into storage.
        $request->validate(['matrix' => 'nullable|array|required_without:matrix_json', 'matrix_json' => 'nullable|json|max:250000|required_without:matrix', 'selected_role' => 'nullable|string']);
        $posted = $request->filled('matrix_json') ? json_decode($request->input('matrix_json'), true) : $request->input('matrix', []);
        abort_unless(is_array($posted), 422);

        try {
            WorkspaceRoleMatrix::save(
                $ws,
                $posted,
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        WorkspaceActivityRecorder::record($ws, 'role.update', 'role', null, 'Role permissions updated', route('user.team.roles.index'));

        return redirect()->route('user.team.roles.index', ['role' => $request->input('selected_role')])
            ->with('success', 'Role permissions updated.')->with('selected_role', $request->input('selected_role'));
    }

    public function duplicate(Request $request)
    {
        $ws = $this->workspace($request);
        $data = $request->validate(['source' => 'required|string', 'name' => 'required|string|max:60|regex:/^[A-Za-z0-9][A-Za-z0-9 _-]*$/']);
        $role = WorkspaceRoleDirectory::change($ws, $request->user(), 'create', $data);

        return redirect()->route('user.team.roles.index', ['role' => $role])->with('success', 'Role created. Configure its permissions, then assign it to teammates.');
    }

    public function createRole(Request $request)
    {
        $ws = $this->workspace($request);
        $data = $request->validate(['name' => 'required|string|max:60', 'source' => 'nullable|string']);
        $role = WorkspaceRoleDirectory::change($ws, $request->user(), 'create', $data);

        return redirect()->route('user.team.roles.index', ['role' => $role])->with('success', 'Role created.');
    }

    public function renameRole(Request $request)
    {
        $ws = $this->workspace($request);
        $data = $request->validate(['role' => 'required|string', 'name' => 'required|string|max:60']);
        $role = WorkspaceRoleDirectory::change($ws, $request->user(), 'rename', $data);

        return redirect()->route('user.team.roles.index', ['role' => $role])->with('success', 'Role name updated. Member assignments are preserved.');
    }

    public function deleteRole(Request $request)
    {
        $ws = $this->workspace($request);
        $data = $request->validate(['role' => 'required|string', 'reassign_to' => 'required|string']);
        $role = WorkspaceRoleDirectory::change($ws, $request->user(), 'delete', $data);

        return redirect()->route('user.team.roles.index', ['role' => $role])->with('success', 'Role deleted. Members and invitations use the replacement role. Content is preserved.');
    }

    public function reset(Request $request)
    {
        $ws = $this->workspace($request);
        WorkspaceRoleMatrix::reset($ws, $request->user());
        WorkspaceActivityRecorder::record($ws, 'role.update', 'role', null, 'Role permissions reset to defaults', route('user.team.roles.index'));

        return redirect()->route('user.team.roles.index')
            ->with('success', 'Role permissions reset to defaults.');
    }
}
