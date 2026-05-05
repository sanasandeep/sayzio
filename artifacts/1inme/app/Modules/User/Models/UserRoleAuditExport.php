<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only ledger row for a single CSV download of the
 * role-change audit trail. Written by `UserRoleAuditCsvExporter`
 * just before the response is streamed to the client, so even a
 * truncated download leaves a "this user pulled the audit"
 * footprint in the table.
 *
 * Surfaced read-only on the back-office "Role audit downloads"
 * panel for super-admins so unusual download activity (very
 * large pulls, bursts from a single actor, etc.) can be spotted.
 */
class UserRoleAuditExport extends Model
{
    public const SCOPE_FULL_POOL   = 'full_pool';
    public const SCOPE_SINGLE_USER = 'single_user';

    protected $table = 'user_role_audit_exports';

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id', 'actor_admin_id', 'actor_guard',
        'actor_name', 'actor_email',
        'scope', 'target_user_id',
        'row_count', 'ip',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'row_count'  => 'integer',
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
     * current name/email, falls back to the snapshotted columns,
     * and finally to "System" when neither side is populated.
     * Mirrors `UserRoleAudit::actorLabel()` so the two ledgers
     * read consistently in the admin UI.
     */
    public function actorLabel(): string
    {
        if ($this->actor_guard === 'web' && $this->actorUser) {
            return $this->actorUser->name
                ?: ($this->actorUser->email ?: ('User #' . $this->actor_user_id));
        }
        if ($this->actor_guard === 'admin' && $this->actorAdmin) {
            return ($this->actorAdmin->name
                ?: $this->actorAdmin->email
                ?: ('Admin #' . $this->actor_admin_id)) . ' (admin)';
        }
        if ($this->actor_name) {
            return $this->actor_guard === 'admin'
                ? $this->actor_name . ' (admin)'
                : $this->actor_name;
        }
        return 'System';
    }

    /**
     * Human-readable scope label for the admin panel.
     */
    public function scopeLabel(): string
    {
        return match ($this->scope) {
            self::SCOPE_FULL_POOL   => 'Full user pool',
            self::SCOPE_SINGLE_USER => 'Single user',
            default                 => $this->scope ?: '—',
        };
    }
}
