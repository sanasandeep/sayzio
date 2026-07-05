<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Task #3566 — a single comment on a {@see DeliveryProject}.
 *
 * author_role is 'client' (buyer/client note from the portal or public share
 * page) or 'team' (a reply from a workspace member). author_user_id is only
 * populated for team replies; portal/public authors are anonymous and carry a
 * captured name/email instead.
 */
class DeliveryProjectComment extends Model
{
    use BelongsToWorkspace;

    public const ROLE_CLIENT = 'client';
    public const ROLE_TEAM   = 'team';

    protected $fillable = [
        'project_id', 'workspace_id',
        'author_role', 'author_user_id', 'author_name', 'author_email',
        'body',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DeliveryProject::class, 'project_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function isTeam(): bool
    {
        return $this->author_role === self::ROLE_TEAM;
    }

    /** Best-effort display name for the comment author. */
    public function displayName(): string
    {
        if ($this->author_role === self::ROLE_TEAM) {
            return $this->author?->name ?: ($this->author_name ?: 'Team');
        }
        return $this->author_name ?: 'Client';
    }
}
