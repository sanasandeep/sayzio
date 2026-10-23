<?php

use App\Modules\Admin\Support\StaffPermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            foreach (StaffPermissionCatalog::definitions() as $slug => $definition) {
                $existing = DB::table('permissions')->where('slug', $slug)->value('id');
                if ($existing) continue; // Do not re-grant capabilities an operator subsequently removed.
                $id = DB::table('permissions')->insertGetId([
                    'slug' => $slug, 'name' => $definition['name'], 'group' => $definition['group'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if (! $definition['legacy']) continue;
                $roleIds = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                    ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
                    ->where('permissions.slug', $definition['legacy'])->where('roles.guard', 'admin')
                    ->pluck('roles.id');
                foreach ($roleIds as $roleId) DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        // Permission grants may have been edited; preserve them on rollback.
    }
};
