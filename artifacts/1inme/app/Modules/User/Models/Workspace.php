<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $fillable = ['owner_user_id', 'name', 'slug', 'is_personal', 'inbox_inbound_token', 'settings'];

    protected $casts = [
        'is_personal' => 'boolean',
        'settings'    => 'array',
    ];

    /** Display label: "Personal" for the user's auto-created workspace, "Team" otherwise. */
    public function kindLabel(): string
    {
        return $this->is_personal ? 'Personal' : 'Team';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members()
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function invites()
    {
        return $this->hasMany(WorkspaceInvite::class);
    }

    /** Total seats currently used: owner + active members. */
    public function seatCount(): int
    {
        return 1 + $this->members()->count();
    }

    /** Pending (un-revoked, un-accepted, un-expired) invites. */
    public function pendingInvites()
    {
        return $this->invites()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Read the post-approval workflow config out of the workspace's
     * generic `settings` JSON column. Shape:
     *   settings.post_approval = ['enabled' => bool, 'approver_roles' => string[]]
     *
     * Defaults to disabled with the Admin role as the only approver — that
     * way turning the toggle on doesn't immediately leave the workspace
     * with zero approvers.
     */
    public function postApprovalConfig(): array
    {
        $cfg = (array) (($this->settings ?? [])['post_approval'] ?? []);
        return [
            'enabled'        => (bool) ($cfg['enabled'] ?? false),
            'approver_roles' => array_values(array_filter(
                (array) ($cfg['approver_roles'] ?? ['admin']),
                fn ($r) => is_string($r) && $r !== ''
            )),
        ];
    }

    /** True if this workspace requires reviewer approval before publishing posts. */
    public function postApprovalEnabled(): bool
    {
        // Personal workspaces never gate posts on approval — there's no team.
        if ($this->is_personal) return false;
        return $this->postApprovalConfig()['enabled'];
    }

    /** Roles whose members can approve / reject pending posts. */
    public function postApproverRoles(): array
    {
        return $this->postApprovalConfig()['approver_roles'];
    }

    /**
     * Persist the approval config back to the JSON settings column. The
     * caller is responsible for permission-checking (owner-only).
     */
    public function setPostApprovalConfig(bool $enabled, array $approverRoles): void
    {
        $allowed = ['admin', 'editor', 'replier', 'analyst', 'viewer'];
        $approverRoles = array_values(array_unique(array_filter(
            $approverRoles,
            fn ($r) => in_array($r, $allowed, true)
        )));
        // Always keep at least one approver role on the list when the
        // toggle is on — otherwise nothing would ever leave the queue.
        if ($enabled && empty($approverRoles)) {
            $approverRoles = ['admin'];
        }
        $settings = (array) ($this->settings ?? []);
        $settings['post_approval'] = [
            'enabled'        => $enabled,
            'approver_roles' => $approverRoles,
        ];
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Can the given user approve / reject pending posts in this workspace?
     * Workspace owners and super-admins always can; otherwise the user must
     * be an active member whose role appears in the approver roles list.
     */
    public function userCanApprovePosts(?User $user): bool
    {
        if (!$user) return false;
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return true;
        if ((int) $this->owner_user_id === (int) $user->id) return true;
        if (!$this->postApprovalEnabled()) return false;
        $m = $user->membershipFor($this);
        if (!$m) return false;
        return in_array($m->role, $this->postApproverRoles(), true);
    }
}
