<?php

namespace App\Modules\User\Models;

use App\Modules\User\Services\WorkspacePermissions;
use Illuminate\Database\Eloquent\Model;

class WorkspaceMember extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'role', 'permissions', 'last_active_at', 'suspended_at'];

    protected $casts = [
        'permissions'    => 'array',
        'last_active_at' => 'datetime',
        'suspended_at'   => 'datetime',
    ];

    /** True if the owner has temporarily disabled this seat. */
    public function isSuspended(): bool
    {
        return !is_null($this->suspended_at);
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Permissions are now derived purely from the member's role. The
     * legacy `permissions` JSON column is no longer consulted but kept
     * around for historical inspection / future custom-overrides.
     */
    public function effectivePermissions(): array
    {
        $matrix = WorkspacePermissions::effectiveRoleActions($this->workspace);
        return $matrix[$this->role ?: 'viewer'] ?? $matrix['viewer'];
    }

    /**
     * Gate check for a workspace action. Accepts either the bare action
     * (`'edit'`, `'delete'`, …) or the legacy `'feature.action'` form
     * (`'links.edit'`) — the feature prefix is ignored because role
     * permissions apply uniformly across every resource in the workspace.
     */
    public function can(string $permission): bool
    {
        // A suspended seat retains its row (and history) but is denied
        // every workspace action until the owner reactivates it.
        if ($this->isSuspended()) return false;
        return WorkspacePermissions::roleCan($this->role ?: 'viewer', $permission, $this->workspace);
    }
}
