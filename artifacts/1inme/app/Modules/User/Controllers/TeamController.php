<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\WorkspaceInviteMailable;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceInvite;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspacePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Team-settings page (members + pending invites + invite/edit/remove flows)
 * for the *active* workspace. All actions here are owner-only.
 */
class TeamController extends Controller
{
    /** Resolve the active workspace and ensure the caller owns it. */
    protected function workspace(Request $request): Workspace
    {
        $ws = app('current_workspace');
        abort_unless((int) $ws->owner_user_id === $request->user()->id,
                     403, 'Only the workspace owner can manage the team.');
        return $ws;
    }

    public function index(Request $request)
    {
        $ws = $this->workspace($request);
        $members = $ws->members()->with('user:id,name,email,avatar')->get();
        $pendingInvites = $ws->pendingInvites()->orderByDesc('created_at')->get();

        $owner = $request->user();
        $maxSeats = (int) $owner->getPlanFeature('max_seats_per_workspace', 1);
        $usedSeats = $ws->seatCount();

        return view('user.team.index', [
            'workspace'         => $ws,
            'members'           => $members,
            'pendingInvites'    => $pendingInvites,
            'maxSeats'          => $maxSeats,
            'usedSeats'         => $usedSeats,
            'roleDescriptions'  => WorkspacePermissions::roleDescriptions(),
        ]);
    }

    public function invite(Request $request)
    {
        $ws = $this->workspace($request);
        $owner = $request->user();

        $data = $request->validate([
            'email' => 'required|email|max:255',
            'role'  => 'required|in:admin,editor,replier,analyst,viewer',
        ]);

        $maxSeats = (int) $owner->getPlanFeature('max_seats_per_workspace', 1);
        if ($maxSeats !== -1 && $ws->seatCount() + $ws->pendingInvites()->count() >= $maxSeats) {
            return back()->with('error', "Seat limit reached ({$maxSeats}). Upgrade your plan or remove a member.");
        }

        // Don't double-invite an existing member.
        $alreadyMember = $ws->members()->whereHas('user', fn ($q) => $q->where('email', $data['email']))->exists()
            || User::where('email', $data['email'])->where('id', $ws->owner_user_id)->exists();
        if ($alreadyMember) {
            return back()->with('error', 'That user is already a member of this workspace.');
        }

        $invite = WorkspaceInvite::create([
            'workspace_id'    => $ws->id,
            'inviter_user_id' => $owner->id,
            'email'           => strtolower(trim($data['email'])),
            'role'            => $data['role'],
            // permissions blob no longer drives gating — role does. Stored
            // as the role's action map for historical inspection.
            'permissions'     => WorkspacePermissions::roleActions()[$data['role']] ?? [],
            'token'           => WorkspaceInvite::newToken(),
            'expires_at'      => now()->addDays(14),
        ]);

        $this->sendInviteEmail($invite);
        return back()->with('success', "Invite sent to {$invite->email}.");
    }

    public function resend(Request $request, WorkspaceInvite $invite)
    {
        $ws = $this->workspace($request);
        abort_unless($invite->workspace_id === $ws->id, 404);
        abort_unless($invite->isPending(), 422, 'Invite is no longer pending.');
        $invite->update(['expires_at' => now()->addDays(14)]);
        $this->sendInviteEmail($invite);
        return back()->with('success', "Invite resent to {$invite->email}.");
    }

    public function revoke(Request $request, WorkspaceInvite $invite)
    {
        $ws = $this->workspace($request);
        abort_unless($invite->workspace_id === $ws->id, 404);
        $invite->update(['revoked_at' => now()]);
        return back()->with('success', "Invite to {$invite->email} revoked.");
    }

    public function updateMember(Request $request, WorkspaceMember $member)
    {
        $ws = $this->workspace($request);
        abort_unless($member->workspace_id === $ws->id, 404);

        $data = $request->validate([
            'role' => 'required|in:admin,editor,replier,analyst,viewer',
        ]);

        $member->update([
            'role'        => $data['role'],
            'permissions' => WorkspacePermissions::roleActions()[$data['role']] ?? [],
        ]);
        return back()->with('success', 'Member role updated.');
    }

    public function removeMember(Request $request, WorkspaceMember $member)
    {
        $ws = $this->workspace($request);
        abort_unless($member->workspace_id === $ws->id, 404);
        $email = optional($member->user)->email;
        $member->delete();

        try {
            if ($email) {
                Mail::raw("You've been removed from the '{$ws->name}' workspace on " . config('app.name') . '.',
                    fn ($m) => $m->to($email)->subject("Removed from workspace: {$ws->name}"));
            }
        } catch (\Throwable $e) { /* best effort */ }

        return back()->with('success', 'Member removed.');
    }

    protected function sendInviteEmail(WorkspaceInvite $invite): void
    {
        try {
            Mail::to($invite->email)->send(new WorkspaceInviteMailable($invite));
        } catch (\Throwable $e) { /* best effort — surface in invites list */ }
    }
}
