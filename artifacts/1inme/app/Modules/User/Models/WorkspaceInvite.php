<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkspaceInvite extends Model
{
    protected $fillable = [
        'workspace_id', 'inviter_user_id', 'email', 'role', 'permissions',
        'token', 'expires_at', 'accepted_at', 'revoked_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at'  => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    public function isPending(): bool
    {
        if ($this->accepted_at || $this->revoked_at) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }
}
