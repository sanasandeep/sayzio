<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $fillable = ['owner_user_id', 'name', 'slug', 'is_personal'];

    protected $casts = [
        'is_personal' => 'boolean',
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
}
