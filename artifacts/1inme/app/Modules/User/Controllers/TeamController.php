<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\WorkspaceInviteMailable;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceInvite;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\SensitiveActionLogger;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use App\Modules\User\Services\WorkspaceContentReassigner;
use App\Modules\User\Services\WorkspacePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Seat management for the active workspace: members, pending invites,
 * suspend/reactivate, and remove with content reassignment. Owner and
 * Admin members can act here; workspace-level destructive actions stay
 * gated by the `workspace.owner` middleware.
 */
class TeamController extends Controller
{
    public function __construct(protected WorkspaceContentReassigner $reassigner) {}

    /** Resolve the active workspace and ensure the caller is owner or admin. */
    protected function workspace(Request $request): Workspace
    {
        $ws = app('current_workspace');
        abort_unless($this->isOwnerOrAdmin($request, $ws),
                     403, 'Only the workspace owner or an Admin can manage the team.');
        return $ws;
    }

    /** True if the active user is the workspace owner OR an Admin member. */
    protected function isOwnerOrAdmin(Request $request, Workspace $ws): bool
    {
        $user = $request->user();
        if (!$user) return false;
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return true;
        if ((int) $ws->owner_user_id === (int) $user->id) return true;
        $m = $user->membershipFor($ws);
        return $m && $m->role === 'admin' && !$m->isSuspended();
    }

    public function index(Request $request)
    {
        $ws = $this->workspace($request);
        $members = $ws->members()->with('user:id,name,email,avatar,last_login_at')->get();
        $pendingInvites = $ws->pendingInvites()->orderByDesc('created_at')->get();

        // Seat limits follow the owner's plan (an Admin may be on a smaller plan).
        $owner = $ws->owner ?: $request->user();
        $maxSeats = (int) $owner->getPlanFeature('max_seats_per_workspace', 1);
        $usedSeats = $ws->seatCount();
        $pendingCount = $pendingInvites->count();
        $planLabel = $owner->plan->name ?? ($owner->plan->slug ?? 'Free');

        $rows = [];
        foreach ($members as $m) {
            $u = $m->user;
            $owned = $this->reassigner->totalForMember($ws, (int) $m->user_id);
            $rows[] = [
                'id'              => $m->id,
                'user_id'         => (int) $m->user_id,
                'name'            => $u->name ?? '—',
                'email'           => $u->email ?? '—',
                'role'            => $m->role,
                'status'          => $m->isSuspended() ? 'suspended' : 'active',
                'last_login_at'   => optional($u?->last_login_at)->toIso8601String(),
                'last_active_at'  => optional($m->last_active_at)->toIso8601String(),
                'last_seen_human' => $this->formatLastSeen($m, $u),
                'owned_count'     => $owned,
                'is_suspended'    => $m->isSuspended(),
            ];
        }

        // Reassignment dropdown: owner + non-suspended members.
        $reassignOptions = [[
            'user_id' => (int) $ws->owner_user_id,
            'label'   => ($ws->owner->name ?? 'Owner') . ' (owner)',
        ]];
        foreach ($members as $m) {
            if ($m->isSuspended()) continue;
            $reassignOptions[] = [
                'user_id' => (int) $m->user_id,
                'label'   => ($m->user->name ?? $m->user->email ?? "Member #{$m->id}"),
            ];
        }

        $approvalCfg = $ws->postApprovalConfig();
        $isOwner = (int) $ws->owner_user_id === (int) $request->user()->id
                   || (method_exists($request->user(), 'isSuperAdmin') && $request->user()->isSuperAdmin());

        return view('user.team.index', [
            'workspace'         => $ws,
            'rows'              => $rows,
            'pendingInvites'    => $pendingInvites,
            'maxSeats'          => $maxSeats,
            'usedSeats'         => $usedSeats,
            'pendingCount'      => $pendingCount,
            'planLabel'         => $planLabel,
            'reassignOptions'   => $reassignOptions,
            'roleDescriptions'  => WorkspacePermissions::roleDescriptions(),
            'effectiveMatrix'   => WorkspacePermissions::effectiveRoleActions($ws),
            'canEditRoles'      => $this->isOwnerOrAdmin($request, $ws),
            'approvalCfg'       => $approvalCfg,
            'isOwner'           => $isOwner,
        ]);
    }

    /** Pick the most informative "last seen" timestamp for the row. */
    protected function formatLastSeen(WorkspaceMember $m, ?User $u): ?string
    {
        $candidates = array_filter([$m->last_active_at, $u?->last_login_at]);
        if (empty($candidates)) return null;
        usort($candidates, fn ($a, $b) => $b <=> $a);
        return $candidates[0]->diffForHumans();
    }

    public function invite(Request $request)
    {
        $ws = $this->workspace($request);
        $owner = $ws->owner ?: $request->user();

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
            'inviter_user_id' => $request->user()->id,
            'email'           => strtolower(trim($data['email'])),
            'role'            => $data['role'],
            // permissions blob no longer drives gating — role does. Stored
            // as the role's action map for historical inspection.
            'permissions'     => WorkspacePermissions::roleActions()[$data['role']] ?? [],
            'token'           => WorkspaceInvite::newToken(),
            'expires_at'      => now()->addDays(14),
        ]);

        $this->sendInviteEmail($invite);
        WorkspaceActivityRecorder::record($ws, 'member.invite', 'invite', $invite->id, $invite->email, route('user.team.index'), ['role' => $invite->role]);
        return back()->with('success', "Invite sent to {$invite->email}.");
    }

    public function resend(Request $request, WorkspaceInvite $invite)
    {
        $ws = $this->workspace($request);
        abort_unless($invite->workspace_id === $ws->id, 404);
        abort_unless($invite->isPending(), 422, 'Invite is no longer pending.');
        $invite->update(['expires_at' => now()->addDays(14)]);
        $this->sendInviteEmail($invite);
        WorkspaceActivityRecorder::record($ws, 'member.invite', 'invite', $invite->id, $invite->email, route('user.team.index'), ['resend' => true, 'role' => $invite->role]);
        return back()->with('success', "Invite resent to {$invite->email}.");
    }

    public function revoke(Request $request, WorkspaceInvite $invite)
    {
        $ws = $this->workspace($request);
        abort_unless($invite->workspace_id === $ws->id, 404);
        $invite->update(['revoked_at' => now()]);
        WorkspaceActivityRecorder::record($ws, 'member.invite.revoke', 'invite', $invite->id, $invite->email, route('user.team.index'));
        return back()->with('success', "Invite to {$invite->email} revoked.");
    }

    public function updateMember(Request $request, WorkspaceMember $member)
    {
        $ws = $this->workspace($request);
        abort_unless($member->workspace_id === $ws->id, 404);

        $data = $request->validate([
            'role' => 'required|in:admin,editor,replier,analyst,viewer',
        ]);

        $previousRole = $member->role;
        $member->update([
            'role'        => $data['role'],
            'permissions' => WorkspacePermissions::roleActions()[$data['role']] ?? [],
        ]);
        WorkspaceActivityRecorder::record(
            $ws, 'member.update', 'member', $member->id,
            optional($member->user)->email ?: ('user#' . $member->user_id),
            route('user.team.index'),
            ['from_role' => $previousRole, 'to_role' => $data['role']],
        );
        return back()->with('success', 'Member role updated.');
    }

    /** Freeze a seat: keep the row but deny all workspace permissions. */
    public function suspend(Request $request, WorkspaceMember $member)
    {
        $ws = $this->workspace($request);
        abort_unless($member->workspace_id === $ws->id, 404);
        if ($member->isSuspended()) {
            return back()->with('error', 'That seat is already suspended.');
        }
        $member->update(['suspended_at' => now()]);
        return back()->with('success', 'Seat suspended — that teammate no longer has access.');
    }

    public function reactivate(Request $request, WorkspaceMember $member)
    {
        $ws = $this->workspace($request);
        abort_unless($member->workspace_id === $ws->id, 404);
        if (!$member->isSuspended()) {
            return back()->with('error', 'That seat is already active.');
        }
        $member->update(['suspended_at' => null]);
        return back()->with('success', 'Seat reactivated.');
    }

    public function removeMember(Request $request, WorkspaceMember $member)
    {
        $ws = $this->workspace($request);
        abort_unless($member->workspace_id === $ws->id, 404);

        $data = $request->validate([
            'reassign_to' => 'nullable|integer',
        ]);

        $email    = optional($member->user)->email;
        $name     = optional($member->user)->name;
        $memberId = $member->id;
        $userId   = (int) $member->user_id;
        $role     = $member->role;
        $owned    = $this->reassigner->totalForMember($ws, $userId);

        if ($owned > 0) {
            $reassignTo = (int) ($data['reassign_to'] ?? 0);
            if ($reassignTo <= 0) {
                return back()->with('error',
                    'Pick someone to take over their content before removing this seat.');
            }
            $valid = (int) $ws->owner_user_id === $reassignTo
                || $ws->members()
                    ->where('user_id', $reassignTo)
                    ->whereNull('suspended_at')
                    ->exists();
            if (!$valid || $reassignTo === $userId) {
                return back()->with('error',
                    'Choose a different active teammate to receive their content.');
            }
            $this->reassigner->reassign($ws, $userId, $reassignTo);
        }

        $member->delete();
        WorkspaceActivityRecorder::record(
            $ws, 'member.remove', 'member', $memberId,
            $email ?: ('user#' . $userId),
            route('user.team.index'),
            ['user_id' => $userId, 'role' => $role],
        );

        // Sensitive action — append to the workspace audit ledger and
        // (subject to owner prefs) email the workspace owner.
        app(SensitiveActionLogger::class)->record(
            $ws,
            SensitiveActionLogger::ACTION_MEMBER_REMOVED,
            'workspace_member',
            $userId,
            $name ?: $email ?: ('User #'.$userId),
            ['email' => $email, 'role' => $role],
        );

        try {
            if ($email) {
                Mail::raw("You've been removed from the '{$ws->name}' workspace on " . config('app.name') . '.',
                    fn ($m) => $m->to($email)->subject("Removed from workspace: {$ws->name}"));
            }
        } catch (\Throwable $e) { /* best effort */ }

        $msg = 'Seat removed.';
        if ($owned > 0) {
            $msg = "Seat removed and {$owned} item" . ($owned === 1 ? '' : 's') . ' reassigned.';
        }
        return back()->with('success', $msg);
    }

    protected function sendInviteEmail(WorkspaceInvite $invite): void
    {
        try {
            Mail::to($invite->email)->send(new WorkspaceInviteMailable($invite));
        } catch (\Throwable $e) { /* best effort — surface in invites list */ }
    }
}
