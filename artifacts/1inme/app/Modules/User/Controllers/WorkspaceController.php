<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
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

        $ws = $user->ownedWorkspaces()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . Str::random(4),
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
}
