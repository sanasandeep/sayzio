<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the `badge_requests.review` admin-guard permission that gates the
 * badge-request review queue (Task #2910) and grant it to the default
 * Staff, Super Admin and Support roles.
 *
 * Additive + idempotent — matched by slug, role links use insertOrIgnore,
 * missing roles are skipped. Safe to re-run against the shared production
 * database. Mirrors 2027_07_27_000001_seed_granular_admin_permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $slug  = 'badge_requests.review';
        $name  = 'Review Badge Requests';
        $group = 'users';

        $permId = DB::table('permissions')->where('slug', $slug)->value('id');
        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name'       => $name,
                'slug'       => $slug,
                'group'      => $group,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleId = fn (string $rslug) => DB::table('roles')
            ->where('slug', $rslug)
            ->where('guard', 'admin')
            ->value('id');

        foreach (['staff', 'super-admin', 'support'] as $rslug) {
            $rid = $roleId($rslug);
            if ($rid) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id'       => $rid,
                    'permission_id' => $permId,
                ]);
            }
        }
    }

    /**
     * No-op: additive permission seed. Reverting risks removing a
     * permission an operator may have assigned to custom roles.
     */
    public function down(): void
    {
        // Intentionally empty — see class docblock.
    }
};
