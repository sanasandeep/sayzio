<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Split the coarse `users.edit` bundle (and the staff.create /
     * staff.delete admin-access gate) into dedicated per-action admin-guard
     * permissions so staff roles can be granted granular powers.
     *
     * Additive + idempotent — safe to re-run against the shared production
     * database:
     *  - Permissions are matched by slug and never duplicated.
     *  - Roles are resolved by slug; missing ones are skipped (they're only
     *    ever created by the fresh-install seeder, never invented here).
     *  - Role↔permission links use insertOrIgnore against the unique
     *    (role_id, permission_id) pair, so re-runs are no-ops.
     */
    public function up(): void
    {
        $now = now();

        // group => [[name, slug], ...]
        $permissions = [
            'users' => [
                ['Provision New Accounts', 'users.create'],
                ['Suspend / Reactivate Accounts', 'users.suspend'],
                ['Assign User Roles', 'users.assign_roles'],
            ],
            'billing' => [
                ['Grant / Deduct Credits', 'users.credits'],
                ['Assign / Comp Plans', 'users.assign_plan'],
                ['Bulk Credit Grants', 'users.bulk_credits'],
                ['Bulk Plan Assignment', 'users.bulk_plan'],
            ],
            'staff' => [
                ['Grant Staff Admin Access', 'users.grant_admin'],
                ['Revoke Staff Admin Access', 'users.revoke_admin'],
            ],
        ];

        $idBySlug = [];
        foreach ($permissions as $group => $perms) {
            foreach ($perms as [$name, $slug]) {
                $id = DB::table('permissions')->where('slug', $slug)->value('id');
                if (! $id) {
                    $id = DB::table('permissions')->insertGetId([
                        'name'       => $name,
                        'slug'       => $slug,
                        'group'      => $group,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $idBySlug[$slug] = $id;
            }
        }

        $roleId = fn (string $slug) => DB::table('roles')
            ->where('slug', $slug)
            ->where('guard', 'admin')
            ->value('id');

        $allSlugs = array_keys($idBySlug);

        // Support keeps its existing narrower scope: everything the old
        // `users.edit` bundle gave it, but NOT the admin-access grant/revoke
        // (it never held staff.create / staff.delete).
        $supportSlugs = array_values(array_diff($allSlugs, [
            'users.grant_admin',
            'users.revoke_admin',
        ]));

        $attach = function (?int $rid, array $slugs) use ($idBySlug) {
            if (! $rid) {
                return;
            }
            foreach ($slugs as $slug) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id'       => $rid,
                    'permission_id' => $idBySlug[$slug],
                ]);
            }
        };

        // Staff + Super Admin get the full new set; Support gets the subset.
        $attach($roleId('staff'), $allSlugs);
        $attach($roleId('super-admin'), $allSlugs);
        $attach($roleId('support'), $supportSlugs);
    }

    /**
     * No-op: additive permission seed. Reverting risks removing permissions
     * an operator may have since assigned to custom roles.
     */
    public function down(): void
    {
        // Intentionally empty — see class docblock.
    }
};
