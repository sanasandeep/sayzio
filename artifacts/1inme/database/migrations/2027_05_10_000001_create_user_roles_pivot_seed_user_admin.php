<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index('role_id');
        });

        $now = now();

        $userPermissions = [
            ['name' => 'Bypass plan limits',                 'slug' => 'user.plan_limits.bypass'],
            ['name' => 'Access any workspace',               'slug' => 'user.workspaces.access_any'],
            ['name' => 'Bypass file size and type limits',   'slug' => 'user.files.access_any'],
            ['name' => 'View all vault entries',             'slug' => 'user.vault.access_any'],
            ['name' => 'View any user\'s invoices',          'slug' => 'user.invoices.view_any'],
            ['name' => 'View analytics for any link',        'slug' => 'user.analytics.view_any'],
            ['name' => 'Manually activate subscriptions',    'slug' => 'user.subscriptions.activate_manually'],
            ['name' => 'Use names from the banned list',     'slug' => 'user.banned_names.bypass'],
            ['name' => 'Manage platform AI Minds',           'slug' => 'user.ai_minds.manage_platform'],
            ['name' => 'Receive operational alerts',         'slug' => 'user.ops_alerts.receive'],
            ['name' => 'Manage subscription plans',          'slug' => 'user.plans.manage'],
            ['name' => 'Review verification requests',       'slug' => 'user.verifications.review'],
            ['name' => 'Assign user-pool roles',             'slug' => 'user.roles.manage'],
            ['name' => 'Platform administration',            'slug' => 'user.platform.admin'],
        ];

        $userPermissionIds = [];
        foreach ($userPermissions as $perm) {
            $existing = DB::table('permissions')->where('slug', $perm['slug'])->value('id');
            if ($existing) {
                $userPermissionIds[] = $existing;
                continue;
            }
            $userPermissionIds[] = DB::table('permissions')->insertGetId([
                'name'       => $perm['name'],
                'slug'       => $perm['slug'],
                'group'      => 'user-app',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $userAdminRoleId = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
        if (!$userAdminRoleId) {
            $userAdminRoleId = DB::table('roles')->insertGetId([
                'name'        => 'User Admin',
                'slug'        => 'user-admin',
                'description' => 'Full administrative access on the user side of the application',
                'guard'       => 'web',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        foreach ($userPermissionIds as $permId) {
            $exists = DB::table('role_permissions')
                ->where('role_id', $userAdminRoleId)
                ->where('permission_id', $permId)
                ->exists();
            if (!$exists) {
                DB::table('role_permissions')->insert([
                    'role_id'       => $userAdminRoleId,
                    'permission_id' => $permId,
                ]);
            }
        }

        if (Schema::hasColumn('users', 'role')) {
            $superAdminUserIds = DB::table('users')
                ->where('role', 'super_admin')
                ->pluck('id');

            foreach ($superAdminUserIds as $uid) {
                $exists = DB::table('user_roles')
                    ->where('user_id', $uid)
                    ->where('role_id', $userAdminRoleId)
                    ->exists();
                if (!$exists) {
                    DB::table('user_roles')->insert([
                        'user_id'    => $uid,
                        'role_id'    => $userAdminRoleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('status');
            }
        });

        $userAdminRoleId = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
        if ($userAdminRoleId) {
            $userIds = DB::table('user_roles')->where('role_id', $userAdminRoleId)->pluck('user_id');
            DB::table('users')->whereIn('id', $userIds)->update(['role' => 'super_admin']);
        }

        Schema::dropIfExists('user_roles');
    }
};
