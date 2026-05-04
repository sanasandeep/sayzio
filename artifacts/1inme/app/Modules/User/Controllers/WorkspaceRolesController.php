<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceActivityRecorder;
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
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin());
        $isAdmin = !$isOwner && optional($user->membershipFor($ws))->role === 'admin';
        abort_unless($isOwner || $isAdmin,
                     403, 'Only the workspace owner or an Admin can edit roles.');
        return $ws;
    }

    public function index(Request $request)
    {
        $ws = $this->workspace($request);
        return view('user.team.roles', [
            'workspace' => $ws,
            'roles'     => WorkspaceRoleMatrix::roles(),
            'actions'   => WorkspaceRoleMatrix::actions(),
            'matrix'    => WorkspaceRoleMatrix::forWorkspace($ws),
            'defaults'  => WorkspaceRoleMatrix::defaults(),
            'audits'    => WorkspaceRoleMatrix::recentAudits($ws, 10),
        ]);
    }

    public function update(Request $request)
    {
        $ws = $this->workspace($request);

        // Whitelisted shape: matrix[<role>][<action>] = "1"|"0"|on. Anything
        // else is rejected by `WorkspaceRoleMatrix::save()` so a hostile
        // payload can't sneak unknown roles or actions into storage.
        $request->validate(['matrix' => 'nullable|array']);

        try {
            WorkspaceRoleMatrix::save(
                $ws,
                (array) $request->input('matrix', []),
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        WorkspaceActivityRecorder::record($ws, 'role.update', 'role', null, 'Role permissions updated', route('user.team.roles.index'));

        return redirect()->route('user.team.roles.index')
            ->with('success', 'Role permissions updated.');
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
