<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
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
            'workspace'      => $ws,
            'members'        => $members,
            'pendingInvites' => $pendingInvites,
            'maxSeats'       => $maxSeats,
            'usedSeats'      => $usedSeats,
            'presets'        => array_keys(WorkspacePermissions::presets()),
            'matrix'         => WorkspacePermissions::matrix(),
        ]);
    }

    public function invite(Request $request)
    {
        $ws = $this->workspace($request);
        $owner = $request->user();

        $data = $request->validate([
            'email'         => 'required|email|max:255',
            'role'          => 'required|in:admin,editor,replier,analyst,viewer,custom',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'boolean',
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

        // Resolve permissions: preset baseline OR explicit checkbox matrix.
        $permissions = $this->resolvePermissions($data['role'], $data['permissions'] ?? null);

        $invite = WorkspaceInvite::create([
            'workspace_id'    => $ws->id,
            'inviter_user_id' => $owner->id,
            'email'           => strtolower(trim($data['email'])),
            'role'            => $data['role'],
            'permissions'     => $permissions,
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
            'role'          => 'required|in:admin,editor,replier,analyst,viewer,custom',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'boolean',
        ]);

        $member->update([
            'role'        => $data['role'],
            'permissions' => $this->resolvePermissions($data['role'], $data['permissions'] ?? null),
        ]);
        return back()->with('success', 'Member permissions updated.');
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

    /**
     * Resolve a permissions blob from a role + optional matrix.
     * - Non-custom roles always use the preset (matrix is ignored).
     * - 'custom' role requires the matrix and stores it verbatim (only true keys).
     */
    protected function resolvePermissions(string $role, ?array $matrix): array
    {
        if ($role !== 'custom') {
            return WorkspacePermissions::preset($role);
        }
        $clean = [];
        foreach (($matrix ?? []) as $k => $v) {
            if ($v) $clean[$k] = true;
        }
        return $clean;
    }

    protected function sendInviteEmail(WorkspaceInvite $invite): void
    {
        $url = route('user.workspaces.invite.show', ['token' => $invite->token]);
        $wsName = optional($invite->workspace)->name ?? 'a workspace';
        try {
            Mail::raw(
                "You've been invited to join the '{$wsName}' workspace on " . config('app.name') . ".\n\n"
                . "Accept your invite here: {$url}\n\n"
                . "This link expires on " . optional($invite->expires_at)->toDayDateTimeString() . '.',
                function ($m) use ($invite, $wsName) {
                    $m->to($invite->email)->subject("You've been invited to {$wsName}");
                }
            );
        } catch (\Throwable $e) { /* best effort — surface in invites list */ }
    }
}
