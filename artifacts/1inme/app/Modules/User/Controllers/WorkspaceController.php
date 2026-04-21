<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    public function __construct(protected WorkspaceContext $ctx) {}

    /** Switch the active workspace. Verifies access first. */
    public function switch(Request $request, Workspace $workspace)
    {
        $user = $request->user();
        abort_unless($user->belongsToWorkspace($workspace), 403, 'You do not have access to that workspace.');
        $this->ctx->set($workspace);
        return back()->with('success', 'Switched to ' . $workspace->name . '.');
    }

    /** Create a new workspace (owner only — limited by plan max_workspaces). */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $max = (int) $user->getPlanFeature('max_workspaces', 1);
        $owned = $user->ownedWorkspaces()->count();
        if ($max !== -1 && $owned >= $max) {
            return back()->with('error', "Your plan allows at most {$max} workspace(s). Upgrade to add more.");
        }

        // New workspaces created from the switcher are team workspaces.
        // The user's personal workspace is auto-created at registration and
        // is the only `is_personal=true` row they own.
        $ws = $user->ownedWorkspaces()->create([
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']) . '-' . Str::random(4),
            'is_personal' => false,
        ]);

        $this->ctx->set($ws);
        return redirect()->route('user.dashboard')->with('success', "Workspace '{$ws->name}' created.");
    }

    /** Owner-only: rename a workspace they own. */
    public function update(Request $request, Workspace $workspace)
    {
        $user = $request->user();
        abort_unless((int) $workspace->owner_user_id === $user->id, 403);
        $data = $request->validate(['name' => 'required|string|max:120']);
        $workspace->update(['name' => $data['name']]);
        return back()->with('success', 'Workspace renamed.');
    }

    /**
     * Owner-only: delete a workspace they own. Owner cannot delete their
     * last workspace — they must always have at least one.
     */
    public function destroy(Request $request, Workspace $workspace)
    {
        $user = $request->user();
        abort_unless((int) $workspace->owner_user_id === $user->id, 403);
        if ($workspace->is_personal) {
            return back()->with('error', 'Your personal workspace cannot be deleted.');
        }
        $owned = $user->ownedWorkspaces()->count();
        if ($owned <= 1) {
            return back()->with('error', 'You cannot delete your only workspace.');
        }
        $workspace->members()->delete();
        $workspace->invites()->delete();
        $workspace->delete();
        $this->ctx->clear();
        return redirect()->route('user.dashboard')->with('success', 'Workspace deleted.');
    }

    /**
     * Member-initiated "ask an admin for access" — fired from the branded
     * 403 page when a member hits a route their role can't see. Drops an
     * in-app notification on the workspace owner and bounces back with a
     * confirmation flash. Throttled at the route level to prevent spam.
     */
    public function requestAccess(Request $request)
    {
        $data = $request->validate([
            'workspace_id' => 'required|integer',
            'path'         => 'nullable|string|max:255',
            'permissions'  => 'nullable|array',
            'permissions.*'=> 'string|max:64',
        ]);

        $user = $request->user();
        $ws   = Workspace::find($data['workspace_id']);

        if (!$ws || !$user->membershipFor($ws)) {
            return redirect()->route('user.dashboard')
                ->with('access_request_error', "We couldn't find that workspace.");
        }

        // Don't pester the owner more than once an hour for the same path.
        $cacheKey = "access_request:{$user->id}:{$ws->id}:" . md5((string) ($data['path'] ?? ''));
        if (\Cache::has($cacheKey)) {
            return back()->with('access_request_sent', true);
        }
        \Cache::put($cacheKey, 1, now()->addHour());

        UserNotification::create([
            'user_id'    => (int) $ws->owner_user_id,
            'type'       => 'workspace_access_request',
            'data'       => [
                'requester_id'   => $user->id,
                'requester_name' => $user->name,
                'requester_email'=> $user->email,
                'workspace_id'   => $ws->id,
                'workspace_name' => $ws->name,
                'path'           => $data['path'] ?? null,
                'permissions'    => $data['permissions'] ?? [],
                'message'        => "{$user->name} is asking for access in {$ws->name}.",
            ],
            'created_at' => now(),
            'emailed_at' => null,
        ]);

        return back()->with('access_request_sent', true);
    }
}
