<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'name'  => 'required|string|max:120',
            'icon'  => ['nullable', 'string', Rule::in(array_keys(Workspace::ICON_CHOICES))],
            'color' => ['nullable', 'string', Rule::in(Workspace::COLOR_CHOICES)],
        ]);

        $max = (int) $user->getPlanFeature('max_workspaces', 1);
        $owned = $user->ownedWorkspaces()->count();
        if ($max !== -1 && $owned >= $max) {
            return back()->with('error', "Your plan allows at most {$max} workspace(s). Upgrade to add more.");
        }

        // Persist the chosen symbol + colour into the generic settings JSON
        // (no dedicated columns). Only stored when the user actually picked
        // one; otherwise the workspace falls back to the automatic icon.
        $settings = [];
        if (!empty($data['icon']) || !empty($data['color'])) {
            $settings['appearance'] = array_filter([
                'icon'  => $data['icon'] ?? null,
                'color' => $data['color'] ?? null,
            ]);
        }

        // New workspaces created from the switcher are team workspaces.
        // The user's personal workspace is auto-created at registration and
        // is the only `is_personal=true` row they own.
        $ws = $user->ownedWorkspaces()->create([
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']) . '-' . Str::random(4),
            'is_personal' => false,
            'settings'    => $settings ?: null,
        ]);

        $this->ctx->set($ws);
        return redirect()->route('user.dashboard')->with('success', "Workspace '{$ws->name}' created.");
    }

    /** Owner-only: show the workspace settings page. */
    public function settings(Request $request, Workspace $workspace)
    {
        $user = $request->user();
        abort_unless((int) $workspace->owner_user_id === $user->id, 403);
        $ownedCount = $user->ownedWorkspaces()->count();
        return view('user.workspaces.settings', [
            'workspace'  => $workspace,
            'ownedCount' => $ownedCount,
        ]);
    }

    /** Owner-only: rename a workspace and/or update its appearance. */
    public function update(Request $request, Workspace $workspace)
    {
        $user = $request->user();
        abort_unless((int) $workspace->owner_user_id === $user->id, 403);
        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'icon'  => ['nullable', 'string', Rule::in(array_keys(Workspace::ICON_CHOICES))],
            'color' => ['nullable', 'string', Rule::in(Workspace::COLOR_CHOICES)],
        ]);
        $previousName = $workspace->name;

        $settings = $workspace->settings ?? [];
        $appearance = $settings['appearance'] ?? [];
        if (!empty($data['icon'])) {
            $appearance['icon'] = $data['icon'];
        }
        if (!empty($data['color'])) {
            $appearance['color'] = $data['color'];
        }
        $settings['appearance'] = $appearance;

        $workspace->update([
            'name'     => $data['name'],
            'settings' => $settings,
        ]);
        WorkspaceActivityRecorder::record($workspace, 'workspace.update', 'workspace', $workspace->id, $workspace->name, route('user.team.index'), [
            'from_name' => $previousName, 'to_name' => $data['name'],
        ]);
        return back()->with('success', 'Workspace settings saved.');
    }

    /**
     * Owner-only: turn the post-approval workflow on/off and pick which
     * member roles can approve. Personal workspaces don't expose this
     * (no team), so we reject the request to keep the JSON shape clean.
     */
    public function updatePostApproval(Request $request, Workspace $workspace)
    {
        $user = $request->user();
        abort_unless((int) $workspace->owner_user_id === $user->id, 403);

        if ($workspace->is_personal) {
            return back()->with('error', "Approval workflow only applies to team workspaces.");
        }

        $data = $request->validate([
            'enabled'          => 'nullable|boolean',
            'approver_roles'   => 'nullable|array',
            'approver_roles.*' => 'string|in:admin,editor,replier,analyst,viewer',
        ]);

        $enabled       = (bool) ($data['enabled'] ?? false);
        $approverRoles = $data['approver_roles'] ?? ['admin'];

        $workspace->setPostApprovalConfig($enabled, $approverRoles);

        return back()->with('success', $enabled
            ? 'Approval workflow on. New posts will go to the review queue.'
            : 'Approval workflow off. Posts publish immediately again.');
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
            'note'         => 'nullable|string|max:280',
        ]);

        $note = trim((string) ($data['note'] ?? ''));
        $note = $note === '' ? null : $note;

        $user = $request->user();
        $ws   = Workspace::find($data['workspace_id']);

        if (!$ws || !$user->membershipFor($ws)) {
            return redirect()->route('user.dashboard')
                ->with('access_request_error', "We couldn't find that workspace.");
        }

        // Don't pester the owner more than once an hour for the same
        // (user, workspace, permission-set). Keying on the requested
        // permissions (not the URL path) means a frustrated teammate
        // can't bypass the cooldown by reopening the no-permission page
        // in a fresh tab/session and resubmitting — every duplicate
        // request for the same gated capability is silently swallowed
        // while still showing the friendly confirmation flash.
        $perms = array_values(array_unique(array_map('strval', $data['permissions'] ?? [])));
        sort($perms);
        $permKey = $perms ? md5(implode('|', $perms)) : 'none';
        $cacheKey = "access_request:{$user->id}:{$ws->id}:{$permKey}";
        // Cache::add is atomic (write-if-absent) — closes the race where
        // two near-simultaneous submissions could both pass a has()
        // check before either wrote the cooldown marker.
        $expiresAt = now()->addHour();
        if (! \Cache::add($cacheKey, $expiresAt->getTimestamp(), $expiresAt)) {
            // Already pinged within the cooldown window — tell the
            // teammate how long until they can try again so they
            // stop mashing the button.
            $storedExpiry = (int) \Cache::get($cacheKey, 0);
            $secondsLeft  = max(0, $storedExpiry - now()->getTimestamp());
            $minutesLeft  = (int) ceil($secondsLeft / 60);
            if ($minutesLeft < 1) {
                $minutesLeft = 1;
            }
            $unit = $minutesLeft === 1 ? 'minute' : 'minutes';
            return back()->with(
                'access_request_pending',
                "You already pinged the owner — you can ask again in {$minutesLeft} {$unit}."
            );
        }

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
                'note'           => $note,
                'message'        => "{$user->name} is asking for access in {$ws->name}."
                                    . ($note ? " Note: {$note}" : ''),
            ],
            'created_at' => now(),
            'emailed_at' => null,
        ]);

        return back()->with('access_request_sent', true);
    }
}
