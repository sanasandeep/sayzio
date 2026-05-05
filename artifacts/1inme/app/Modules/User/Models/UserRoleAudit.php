<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only ledger row for a single role attach/detach on a
 * user account. Written by `UserRoleAuditLogger` from both the
 * self-service "User access" page and the back-office admin
 * user-detail page, then surfaced read-only from the same pages
 * so reviewers can see who promoted/demoted whom.
 */
class UserRoleAudit extends Model
{
    public const ACTION_ATTACHED = 'attached';
    public const ACTION_DETACHED = 'detached';

    public const SOURCE_USER_ACCESS = 'user_access';
    public const SOURCE_ADMIN       = 'admin';
    public const SOURCE_BACKFILL    = 'backfill';
    // Auto-generated detach rows written by the model `deleting` hooks
    // when a user account or a role is deleted, so the cascade on the
    // `user_roles` pivot doesn't silently erase the audit trail.
    public const SOURCE_USER_DELETED = 'user_deleted';
    public const SOURCE_ROLE_DELETED = 'role_deleted';

    protected $table = 'user_role_audits';

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id', 'actor_admin_id', 'actor_guard',
        'actor_name', 'actor_email',
        'target_user_id',
        'role_id', 'role_slug', 'role_name',
        'action', 'source', 'ip',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorAdmin()
    {
        return $this->belongsTo(Admin::class, 'actor_admin_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Best-effort label for the actor — prefers the live record's
     * current name/email, falls back to the snapshot, and finally
     * to "System" when neither side is populated (e.g. CLI seeders).
     */
    public function actorLabel(): string
    {
        if ($this->actor_guard === 'web' && $this->actorUser) {
            return $this->actorUser->name ?: ($this->actorUser->email ?: ('User #' . $this->actor_user_id));
        }
        if ($this->actor_guard === 'admin' && $this->actorAdmin) {
            return ($this->actorAdmin->name ?: $this->actorAdmin->email ?: ('Admin #' . $this->actor_admin_id))
                . ' (admin)';
        }
        if ($this->actor_name) {
            return $this->actor_guard === 'admin'
                ? $this->actor_name . ' (admin)'
                : $this->actor_name;
        }
        return 'System';
    }
}
