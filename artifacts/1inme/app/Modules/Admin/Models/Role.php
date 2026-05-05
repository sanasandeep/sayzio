<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'guard'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function admins()
    {
        return $this->hasMany(Admin::class);
    }

    public function users()
    {
        return $this->belongsToMany(\App\Modules\User\Models\User::class, 'user_roles');
    }

    /**
     * Mirror the role-pivot cascade into the audit ledger so deleting a
     * role doesn't silently drop the 'detached' rows for every user
     * that held it. The `user_roles.role_id` foreign key is declared
     * with `cascadeOnDelete()`, so we have to write the audit entries
     * BEFORE the delete fires (the role row itself still exists at
     * this point, which is what lets `UserRoleAuditLogger` snapshot
     * the slug and name onto each row). Admin-guard roles produce
     * zero rows here because the `user_roles` pivot is web-guard
     * only — admins reference roles directly via `admins.role_id`.
     */
    protected static function booted(): void
    {
        static::deleting(function (Role $role) {
            try {
                $userIds = DB::table('user_roles')
                    ->where('role_id', $role->id)
                    ->pluck('user_id')
                    ->all();
                if (empty($userIds)) {
                    return;
                }
                $logger = app(\App\Modules\User\Services\UserRoleAuditLogger::class);
                $ip = request()?->ip();
                foreach ($userIds as $uid) {
                    $user = \App\Modules\User\Models\User::find($uid);
                    if (!$user) continue;
                    $logger->recordDiff(
                        $user,
                        [(int) $role->id],
                        [],
                        \App\Modules\User\Models\UserRoleAudit::SOURCE_ROLE_DELETED,
                        $ip,
                    );
                }
            } catch (\Throwable $e) {
                // Never block role deletion on an audit failure — but
                // log it so an outage of the audit write path is
                // visible in operational logs instead of disappearing.
                try {
                    \Illuminate\Support\Facades\Log::error(
                        'Role deleting hook: failed to record cascade role-detach audit',
                        ['role_id' => $role->id, 'error' => $e->getMessage()],
                    );
                } catch (\Throwable $ignored) {
                    // Logger itself failed — nothing useful to do here.
                }
            }
        });
    }
}
