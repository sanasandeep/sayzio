<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The owner-designated primary super-admin. This account must be a
     * genuine super-admin with full back-office access while remaining
     * permanently protected (never deletable / suspendable).
     */
    private string $email = 'sanasandeep@gmail.com';

    /**
     * Provision `sanasandeep@gmail.com` as a real super-admin.
     *
     * Idempotent and additive — safe to re-run against the shared
     * production DB without wiping data or disturbing the existing
     * `official1inme@gmail.com` account:
     *
     *  - Resolve the `super-admin` role by slug (create it if somehow
     *    absent).
     *  - Ensure an `admins` row exists for this email holding that role
     *    with `status = active`. If a matching admin already exists
     *    (e.g. created manually in prod) we update role + status in place
     *    rather than inserting a duplicate, and we never overwrite its
     *    password. Only a brand-new record gets a strong random password
     *    (the owner sets their own via the standard forgot-password flow).
     *  - Bridge a matching web `User` (the two auth pools are joined by
     *    email — see User::adminAccount()) so dashboard/ownership features
     *    resolve. Existing user accounts are left untouched.
     *  - Re-assert the locked `protected_accounts` "Superadmin" entry so
     *    the never-delete/never-suspend guard stays in force.
     */
    public function up(): void
    {
        $now = now();
        $email = $this->email;

        // 1. Resolve the super-admin role id by slug; create it if absent.
        $roleId = DB::table('roles')
            ->where('slug', 'super-admin')
            ->where('guard', 'admin')
            ->value('id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name'        => 'Super Admin',
                'slug'        => 'super-admin',
                'description' => 'Full access to all admin features',
                'guard'       => 'admin',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // 2. Ensure the admin record exists with the super-admin role +
        //    active status. Match case-insensitively so we never create a
        //    duplicate of a manually-added prod account.
        $existing = DB::table('admins')
            ->whereRaw('lower(email) = ?', [strtolower($email)])
            ->first();

        if ($existing) {
            // Update role + status in place; never touch the password.
            DB::table('admins')
                ->where('id', $existing->id)
                ->update([
                    'role_id'    => $roleId,
                    'status'     => 'active',
                    'updated_at' => $now,
                ]);
        } else {
            // Brand-new account: strong random password, owner resets via
            // the standard admin forgot-password flow. No real password in
            // the codebase.
            DB::table('admins')->insert([
                'name'       => 'Sana Sandeep',
                'email'      => $email,
                'password'   => Hash::make(Str::random(40)),
                'role_id'    => $roleId,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Bridge a matching web User so dashboard/ownership features
        //    resolve consistently. Leave an existing account untouched.
        $userExists = DB::table('users')
            ->whereRaw('lower(email) = ?', [strtolower($email)])
            ->exists();

        if (! $userExists) {
            DB::table('users')->insert([
                'name'       => 'Sana Sandeep',
                'email'      => $email,
                'password'   => Hash::make(Str::random(40)),
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 4. Re-assert the locked protected-accounts "Superadmin" seed so
        //    the never-delete / never-suspend guard stays in force. Keyed
        //    by email; idempotent.
        DB::table('protected_accounts')->updateOrInsert(
            ['email' => $email],
            [
                'locked'     => true,
                'label'      => 'Superadmin',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    /**
     * Intentionally a no-op. This migration only ever grants access and
     * re-asserts protection; reverting it would risk demoting or removing
     * the owner's primary super-admin account and is never desirable.
     */
    public function down(): void
    {
        // No-op by design — see class docblock.
    }
};
