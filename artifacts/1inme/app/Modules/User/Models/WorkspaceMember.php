<?php

namespace App\Modules\User\Models;

use App\Modules\User\Services\WorkspacePermissions;
use Illuminate\Database\Eloquent\Model;

class WorkspaceMember extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'role', 'permissions'];

    protected $casts = ['permissions' => 'array'];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Effective permissions blob — preset baseline if column is null. */
    public function effectivePermissions(): array
    {
        if (is_array($this->permissions) && !empty($this->permissions)) return $this->permissions;
        return WorkspacePermissions::preset($this->role ?: 'viewer');
    }

    public function can(string $permission): bool
    {
        return (bool) ($this->effectivePermissions()[$permission] ?? false);
    }
}
