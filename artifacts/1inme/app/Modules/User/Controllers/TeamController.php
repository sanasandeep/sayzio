<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\WorkspaceInviteMailable;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceInvite;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\SensitiveActionLogger;
use App\Modules\User\Services\TwoFactorPolicy;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use App\Modules\User\Services\WorkspaceContentReassigner;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspacePermissions;
use App\Services\AI\AiResourceShareService;
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
    public function __construct(
        protected WorkspaceContentReassigner $reassigner,
        protected AiResourceShareService $shares,
    ) {}

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
        if (method_exists($user, 'hasPermission') && $user->hasPermission('user.workspaces.access_any')) return true;
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
                   || (method_exists($request->user(), 'hasPermission') && $request->user()->hasPermission('user.workspaces.access_any'));

        // 2FA compliance: per-member status + policy snapshot. Shown in
        // a side panel on the team page so owners can spot stragglers.
        $policy = app(TwoFactorPolicy::class);
        $compliance = $this->buildComplianceList($ws, $members, $policy);

        $sharedAi = $this->shares->resourcesSharedWithWorkspace((int) $ws->id);
        $badgeSharedAi = $this->shares->resourcesSharedWithUserBadges($request->user());

        return view('user.team.index', [
            'sharedAiMinds'       => $sharedAi['minds'],
            'sharedAiPersonas'    => $sharedAi['personas'],
            'badgeSharedAiMinds'    => $badgeSharedAi['minds'],
            'badgeSharedAiPersonas' => $badgeSharedAi['personas'],
            'workspace'           => $ws,
            'rows'                => $rows,
            'pendingInvites'      => $pendingInvites,
            'maxSeats'            => $maxSeats,
            'usedSeats'           => $usedSeats,
            'pendingCount'        => $pendingCount,
            'planLabel'           => $planLabel,
            'reassignOptions'     => $reassignOptions,
            'roleDescriptions'    => WorkspacePermissions::roleDescriptions(),
            'effectiveMatrix'     => WorkspacePermissions::effectiveRoleActions($ws),
            'canEditRoles'        => $this->isOwnerOrAdmin($request, $ws),
            'approvalCfg'         => $approvalCfg,
            'isOwner'             => $isOwner,
            'twoFactorRequired'   => $policy->workspaceRequires2FA($ws),
            'twoFactorDeadline'   => $policy->workspaceGraceDeadline($ws),
            'twoFactorCompliance' => $compliance,
            'ownerHas2FA'         => $ws->owner ? $policy->userHasEnrolledTotp($ws->owner) : false,
            'isWorkspaceOwner'    => $isOwner,
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

    /**
     * Compose the per-row compliance snapshot rendered on the team page.
     * Each entry: ['name', 'email', 'role', 'enrolled' (bool), 'is_owner' (bool)].
     */
    protected function buildComplianceList($ws, $members, TwoFactorPolicy $policy): array
    {
        $rows = [];
        if ($ws->owner) {
            $rows[] = [
                'name'     => $ws->owner->name,
                'email'    => $ws->owner->email,
                'role'     => 'Owner',
                'enrolled' => $policy->userHasEnrolledTotp($ws->owner),
                'is_owner' => true,
            ];
        }
        foreach ($members as $m) {
            if (!$m->user) continue;
            $rows[] = [
                'name'     => $m->user->name,
                'email'    => $m->user->email,
                'role'     => ucfirst($m->role ?: 'viewer'),
                'enrolled' => $policy->userHasEnrolledTotp($m->user),
                'is_owner' => false,
            ];
        }
        return $rows;
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
                \App\Modules\Common\Services\Emailer::send('workspace.member_removed', $email, [
                    'workspace_name' => $ws->name,
                    'app_name'       => config('app.name'),
                ], ['user' => $userId, 'related' => $ws]);
            }
        } catch (\Throwable $e) { /* best effort */ }

        $msg = 'Seat removed.';
        if ($owned > 0) {
            $msg = "Seat removed and {$owned} item" . ($owned === 1 ? '' : 's') . ' reassigned.';
        }
        return back()->with('success', $msg);
    }

    /**
     * Member-initiated "leave workspace". Unlike removeMember (owner/admin
     * driven), this lets any non-owner seat drop their own membership
     * without waiting for an owner. Runs OUTSIDE the owner/admin gate: it
     * resolves the active workspace directly and only checks that the caller
     * is a non-owner member. Their content is reassigned to the owner so
     * nothing is orphaned, they're moved back to their personal workspace,
     * and the owner gets an in-app notification.
     */
    public function leave(Request $request)
    {
        $ws   = app('current_workspace');
        $user = $request->user();

        // The owner can't abandon their own workspace — they must transfer
        // ownership or delete it. (There's no seat row for the owner anyway.)
        if ((int) $ws->owner_user_id === (int) $user->id) {
            return back()->with('error',
                "You own this workspace, so you can't leave it. Transfer ownership or delete the workspace instead.");
        }

        $member = $user->membershipFor($ws);
        if (!$member) {
            return back()->with('error', "You're not a member of this workspace.");
        }

        $userId = (int) $user->id;
        $role   = $member->role;

        // Reassign anything they authored to the owner so the workspace
        // isn't left with authorless links/posts/forms.
        $owned = $this->reassigner->totalForMember($ws, $userId);
        if ($owned > 0) {
            $this->reassigner->reassign($ws, $userId, (int) $ws->owner_user_id);
        }

        $member->delete();

        WorkspaceActivityRecorder::record(
            $ws, 'member.leave', 'member', $userId,
            $user->email ?: ('user#' . $userId),
            route('user.team.index'),
            ['user_id' => $userId, 'role' => $role, 'reassigned' => $owned],
            $user,
        );

        // Let the owner know, in-app, that someone left.
        UserNotification::create([
            'user_id'    => (int) $ws->owner_user_id,
            'type'       => 'workspace_member_left',
            'data'       => [
                'user_id'        => $userId,
                'user_name'      => $user->name,
                'user_email'     => $user->email,
                'workspace_id'   => $ws->id,
                'workspace_name' => $ws->name,
                'role'           => $role,
                'reassigned'     => $owned,
                'message'        => "{$user->name} left {$ws->name}."
                                    . ($owned > 0
                                        ? " Their {$owned} item" . ($owned === 1 ? '' : 's') . ' moved to you.'
                                        : ''),
            ],
            'created_at' => now(),
            'emailed_at' => null,
        ]);

        // Send them back to their personal workspace.
        $personal = $user->ensureDefaultWorkspace();
        app(WorkspaceContext::class)->set($personal);

        return redirect()->route('user.dashboard')
            ->with('success', "You've left {$ws->name}.");
    }

    protected function sendInviteEmail(WorkspaceInvite $invite): void
    {
        try {
            \App\Modules\Common\Services\Emailer::sendMailable('workspace.invite', $invite->email, new WorkspaceInviteMailable($invite), [
                'workspace_name' => optional($invite->workspace)->name,
            ], ['related' => $invite, 'user' => $invite->inviter_user_id]);
        } catch (\Throwable $e) { /* best effort — surface in invites list */ }
    }
}
