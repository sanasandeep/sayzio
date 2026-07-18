<?php

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotently provisions the two fixed demo accounts so they exist in
 * production with the correct passwords regardless of whether `db:seed`
 * has ever been run.
 *
 * Production deploys run migrations but not `db:seed`, which means the
 * one-click demo buttons that previously existed on the login pages would
 * create throwaway accounts on demand. Those buttons have been removed; the
 * owner now signs in through the normal email + password form instead.
 *
 * Accounts provisioned:
 *
 *   sana@sayzio.app  /  DiaryLabs@1906
 *     Full showcase account — user-admin web role + super-admin Admin bridge.
 *     If the account already exists (e.g. from a previous seeder run), only
 *     the password and status are updated so the existing showcase content is
 *     never wiped.
 *
 *   demo@sayzio.app  /  ReadOnlyDemo@2026
 *     Read-only demo account (is_readonly_demo = true, no Admin record).
 *     Same idempotent update behaviour.
 *
 * Strictly additive — never drops tables, never truncates rows, never
 * touches any account other than these two emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $this->provisionShowcaseAccount();
        $this->provisionReadonlyDemoAccount();
    }

    private function provisionShowcaseAccount(): void
    {
        $plan = Plan::where('slug', 'unlimited')->first()
            ?? Plan::defaultPlan();

        $user = User::updateOrCreate(
            ['email' => 'sana@sayzio.app'],
            [
                'name'              => 'Sana Rahman',
                'password'          => Hash::make('DiaryLabs@1906'),
                'status'            => 'active',
                'email_verified_at' => now(),
                'is_demo'           => true,
                'is_readonly_demo'  => false,
                'onboarded_at'      => now(),
            ] + ($plan ? ['plan_id' => $plan->id] : [])
        );

        // Grant the user-admin web role if the roles table exists.
        if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
            $userAdminRoleId = \Illuminate\Support\Facades\DB::table('roles')
                ->where('slug', 'user-admin')->where('guard', 'web')
                ->value('id');
            if ($userAdminRoleId) {
                $user->roles()->syncWithoutDetaching([$userAdminRoleId]);
                $user->flushPermissionCache();
            }
        }

        // Ensure a super-admin Admin bridge record exists.
        if (!Schema::hasTable('admins')) {
            return;
        }

        $superAdminRoleId = Role::where('slug', 'super-admin')
            ->where('guard', 'admin')
            ->value('id');

        if (!$superAdminRoleId) {
            return;
        }

        Admin::updateOrCreate(
            ['email' => 'sana@sayzio.app'],
            [
                'name'     => 'Sana Rahman',
                'password' => Hash::make('DiaryLabs@1906'),
                'role_id'  => $superAdminRoleId,
                'status'   => 'active',
            ]
        );
    }

    private function provisionReadonlyDemoAccount(): void
    {
        $plan = Plan::defaultPlan();

        User::updateOrCreate(
            ['email' => 'demo@sayzio.app'],
            [
                'name'              => 'Sayzio Demo',
                'password'          => Hash::make('ReadOnlyDemo@2026'),
                'status'            => 'active',
                'email_verified_at' => now(),
                'is_demo'           => true,
                'is_readonly_demo'  => true,
                'onboarded_at'      => now(),
            ] + ($plan ? ['plan_id' => $plan->id] : [])
        );

        // Ensure no stale Admin record exists for the readonly demo account
        // (it must never have admin or super-admin access).
        if (Schema::hasTable('admins')) {
            Admin::where('email', 'demo@sayzio.app')->delete();
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: removing accounts in a rollback could
        // destroy real showcase content that was built on top of them.
    }
};
