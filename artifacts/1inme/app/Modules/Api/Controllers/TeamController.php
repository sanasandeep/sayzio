<?php

namespace App\Modules\Api\Controllers;

use App\Mail\WorkspaceInviteMailable;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceInvite;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspacePermissions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

/**
 * Mobile API for the Team / staff settings page. Reads members + pending
 * invites for the active workspace and lets owners / admins invite, revoke
 * invites, and remove members. Role editing matches the web rules
 * (admin / editor / replier / analyst / viewer).
 */
class TeamController extends Controller
{
    use ApiResponses;

    public function __construct(protected WorkspaceContext $ctx) {}

    /** Resolve the active workspace, or a workspace explicitly requested by id. */
    protected function workspace(Request $request): ?Workspace
    {
        $explicit = $request->integer('workspace_id') ?: null;
        if ($explicit) {
            $ws = Workspace::find($explicit);
            if (!$ws || !$request->user() || !$request->user()->belongsToWorkspace($ws)) return null;
            return $ws;
        }
        return $this->ctx->resolve($request->user());
    }

    protected function isOwnerOrAdmin(?User $user, ?Workspace $ws): bool
    {
        if (!$user || !$ws) return false;
        if (method_exists($user, 'hasPermission') && $user->hasPermission('user.workspaces.access_any')) return true;
        if ((int) $ws->owner_user_id === (int) $user->id) return true;
        $m = $user->membershipFor($ws);
        return $m && $m->role === 'admin';
    }

    public function index(Request $request)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');

        $members = $ws->members()->with('user:id,name,email,avatar')->get()
            ->map(fn (WorkspaceMember $m) => [
                'id'      => $m->id,
                'user_id' => $m->user_id,
                'role'    => $m->role,
                'name'    => $m->user?->name,
                'email'   => $m->user?->email,
                'avatar'  => \App\Support\PublicStorageUrl::resolve($m->user?->avatar),
                'created_at' => optional($m->created_at)->toIso8601String(),
            ])->all();

        $invites = $ws->pendingInvites()->orderByDesc('created_at')->get()
            ->map(fn (WorkspaceInvite $i) => [
                'id'         => $i->id,
                'email'      => $i->email,
                'role'       => $i->role,
                'expires_at' => optional($i->expires_at)->toIso8601String(),
                'created_at' => optional($i->created_at)->toIso8601String(),
            ])->all();

        $owner = $ws->owner ?: $request->user();
        $maxSeats = (int) $owner->getPlanFeature('max_seats_per_workspace', 1);

        return $this->ok([
            'workspace' => [
                'id'   => $ws->id,
                'name' => $ws->name,
                'is_personal' => (bool) ($ws->is_personal ?? false),
            ],
            'members'        => $members,
            'pending_invites'=> $invites,
            'used_seats'     => $ws->seatCount(),
            'max_seats'      => $maxSeats,
            'can_manage'     => $this->isOwnerOrAdmin($request->user(), $ws),
        ]);
    }

    public function invite(Request $request)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');
        if (!$this->isOwnerOrAdmin($request->user(), $ws)) {
            return $this->forbidden('Only the workspace owner or an Admin can invite teammates.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role'  => ['required', 'in:admin,editor,replier,analyst,viewer'],
        ]);

        $owner = $ws->owner ?: $request->user();
        $maxSeats = (int) $owner->getPlanFeature('max_seats_per_workspace', 1);
        $usedSeats = $ws->seatCount() + $ws->pendingInvites()->count();
        if ($maxSeats !== -1 && $usedSeats >= $maxSeats) {
            return $this->planGate("Seat limit reached ({$maxSeats}). Upgrade your plan or remove a member.", 'max_seats_per_workspace', $owner, 422, 'seat_limit', $usedSeats);
        }

        $alreadyMember = $ws->members()->whereHas('user', fn ($q) => $q->where('email', $data['email']))->exists()
            || User::where('email', $data['email'])->where('id', $ws->owner_user_id)->exists();
        if ($alreadyMember) {
            return $this->fail('That user is already a member of this workspace.', 422, 'already_member');
        }

        $invite = WorkspaceInvite::create([
            'workspace_id'    => $ws->id,
            'inviter_user_id' => $request->user()->id,
            'email'           => strtolower(trim($data['email'])),
            'role'            => $data['role'],
            'permissions'     => WorkspacePermissions::roleActions()[$data['role']] ?? [],
            'token'           => WorkspaceInvite::newToken(),
            'expires_at'      => now()->addDays(14),
        ]);

        try {
            \App\Modules\Common\Services\Emailer::sendMailable('workspace.invite', $invite->email, new WorkspaceInviteMailable($invite), [
                'workspace_name' => optional($invite->workspace)->name,
            ], ['related' => $invite, 'user' => $invite->inviter_user_id]);
        } catch (\Throwable $e) { /* best effort */ }

        return $this->created(['invite' => [
            'id'         => $invite->id,
            'email'      => $invite->email,
            'role'       => $invite->role,
            'expires_at' => optional($invite->expires_at)->toIso8601String(),
        ]]);
    }

    /**
     * Owner/Admin: change a member's role. Mirrors the web
     * {@see \App\Modules\User\Controllers\TeamController::updateMember()} —
     * same role set and the same `permissions` action-map persistence — so the
     * native team surface can promote/demote teammates without bouncing to the
     * web. Returns the re-serialized member so the caller can refresh the row.
     */
    public function updateMember(Request $request, int $member)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');
        if (!$this->isOwnerOrAdmin($request->user(), $ws)) {
            return $this->forbidden('Only the workspace owner or an Admin can change roles.');
        }

        $row = WorkspaceMember::where('workspace_id', $ws->id)
            ->with('user:id,name,email,avatar')
            ->find($member);
        if (!$row) return $this->notFound('Member not found');

        $data = $request->validate([
            'role' => ['required', 'in:admin,editor,replier,analyst,viewer'],
        ]);

        $row->update([
            'role'        => $data['role'],
            'permissions' => WorkspacePermissions::roleActions()[$data['role']] ?? [],
        ]);

        return $this->ok(['member' => [
            'id'         => $row->id,
            'user_id'    => $row->user_id,
            'role'       => $row->role,
            'name'       => $row->user?->name,
            'email'      => $row->user?->email,
            'avatar'     => \App\Support\PublicStorageUrl::resolve($row->user?->avatar),
            'created_at' => optional($row->created_at)->toIso8601String(),
        ]]);
    }

    public function revokeInvite(Request $request, int $invite)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');
        if (!$this->isOwnerOrAdmin($request->user(), $ws)) return $this->forbidden();

        $row = WorkspaceInvite::where('workspace_id', $ws->id)->find($invite);
        if (!$row) return $this->notFound('Invite not found');
        $row->update(['revoked_at' => now()]);
        return $this->noContent();
    }

    public function removeMember(Request $request, int $member)
    {
        $ws = $this->workspace($request);
        if (!$ws) return $this->forbidden('No workspace');
        if (!$this->isOwnerOrAdmin($request->user(), $ws)) return $this->forbidden();

        $row = WorkspaceMember::where('workspace_id', $ws->id)->find($member);
        if (!$row) return $this->notFound('Member not found');
        $row->delete();
        return $this->noContent();
    }
}
