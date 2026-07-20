<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AdminActionAudit;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the admin "set user password" surface — web
 * (POST /admin/users/{user}/set-password, gated by `users.edit` via
 * CheckPermission) and mobile/API
 * (POST /api/v1/admin/users/{user}/set-password, permission checked
 * in-controller against the caller's bridged back-office Admin).
 *
 * Both paths must respect {@see ProtectedAccount::isProtected()} so a
 * protected account's password can never be replaced, and the happy
 * path must hash the new password, invalidate the old plaintext at the
 * login form, and write a `user.password_set` audit row.
 */
class AdminSetUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'old-secret-123';
    private const NEW_PASSWORD = 'brand-new-secret-9';

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function superAdminRole(): Role
    {
        return Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
    }

    /**
     * A staff Admin holding exactly the given permission slugs (possibly
     * none). `status => 'active'` is required — without it AdminAuth
     * treats the account as deactivated and every request 302s to login.
     *
     * @param array<int, string> $permSlugs
     */
    private function makeStaff(array $permSlugs): Admin
    {
        sort($permSlugs);
        $role = Role::firstOrCreate(
            ['slug' => 'staff-' . (implode('-', $permSlugs) ?: 'none') . '-' . Str::lower(Str::random(4))],
            ['name' => 'Staff', 'guard' => 'admin']
        );
        foreach ($permSlugs as $permSlug) {
            $perm = Permission::firstOrCreate(
                ['slug' => $permSlug],
                ['name' => $permSlug, 'group' => explode('.', $permSlug)[0] ?? 'misc']
            );
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        return Admin::create([
            'name'     => 'Staff ' . Str::random(4),
            'email'    => 'staff' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    /** A web User bridged (by email) to an active super-admin Admin, for API calls. */
    private function makeApiOperator(): User
    {
        $email = 'op' . Str::random(8) . '@example.com';
        Admin::create([
            'name'     => 'Operator',
            'email'    => $email,
            'password' => Hash::make('secret'),
            'role_id'  => $this->superAdminRole()->id,
            'status'   => 'active',
        ]);

        return User::factory()->create(['email' => $email])->fresh();
    }

    private function makeTarget(bool $protected = false): User
    {
        $user = User::factory()->create([
            'password' => Hash::make(self::OLD_PASSWORD),
        ])->fresh();

        if ($protected) {
            ProtectedAccount::create([
                'email'  => ProtectedAccount::normalizeEmail($user->email),
                'locked' => false,
                'label'  => 'Test',
            ]);
        }

        return $user;
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function webSetPassword(User $target, string $password)
    {
        return $this->from(route('admin.users.show', $target))
            ->post("/admin/users/{$target->id}/set-password", [
                'password'              => $password,
                'password_confirmation' => $password,
            ]);
    }

    // ---------------------------------------------------------------
    // Permission gate
    // ---------------------------------------------------------------

    public function test_web_admin_without_users_edit_gets_403(): void
    {
        $staff  = $this->makeStaff(['users.view']); // can look, not edit
        $target = $this->makeTarget();

        $this->be($staff, 'admin');
        $this->webSetPassword($target, self::NEW_PASSWORD)->assertForbidden();

        // Password untouched.
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $target->fresh()->password));
    }

    public function test_api_caller_without_users_edit_gets_403_json(): void
    {
        // Bridged admin exists but holds no permissions at all.
        $email = 'nopriv' . Str::random(8) . '@example.com';
        $staff = $this->makeStaff([]);
        $staff->forceFill(['email' => $email])->save();
        $operator = User::factory()->create(['email' => $email])->fresh();

        $target = $this->makeTarget();

        $resp = $this->withToken($this->token($operator))
            ->postJson("/api/v1/admin/users/{$target->id}/set-password", [
                'password' => self::NEW_PASSWORD,
            ]);

        $resp->assertStatus(403);
        $this->assertNotEmpty($resp->json('error.message'));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $target->fresh()->password));
    }

    // ---------------------------------------------------------------
    // Protected-account guard
    // ---------------------------------------------------------------

    public function test_web_protected_account_is_blocked_with_error_flash(): void
    {
        $staff  = $this->makeStaff(['users.edit']);
        $target = $this->makeTarget(protected: true);

        $this->be($staff, 'admin');
        $resp = $this->webSetPassword($target, self::NEW_PASSWORD);

        $resp->assertStatus(302);
        $resp->assertRedirect(route('admin.users.show', $target));
        $resp->assertSessionHas('error');

        // Password untouched, no audit row minted.
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $target->fresh()->password));
        $this->assertNull(
            AdminActionAudit::where('action', AdminActionLogger::USER_PASSWORD_SET)->first(),
            'A blocked set-password must not write a user.password_set audit.'
        );
    }

    public function test_api_protected_account_is_blocked_with_403_json(): void
    {
        $operator = $this->makeApiOperator();
        $target   = $this->makeTarget(protected: true);

        $resp = $this->withToken($this->token($operator))
            ->postJson("/api/v1/admin/users/{$target->id}/set-password", [
                'password' => self::NEW_PASSWORD,
            ]);

        $resp->assertStatus(403);
        $resp->assertJsonPath('error.code', 'protected_account');
        $this->assertNotEmpty($resp->json('error.message'));

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $target->fresh()->password));
        $this->assertNull(
            AdminActionAudit::where('action', AdminActionLogger::USER_PASSWORD_SET)->first()
        );
    }

    public function test_protection_matches_email_case_insensitively(): void
    {
        $staff  = $this->makeStaff(['users.edit']);
        $target = User::factory()->create([
            'email'    => 'CasePw@Example.COM',
            'password' => Hash::make(self::OLD_PASSWORD),
        ])->fresh();
        ProtectedAccount::create([
            'email'  => 'casepw@example.com',
            'locked' => false,
            'label'  => 'Test',
        ]);

        $this->be($staff, 'admin');
        $this->webSetPassword($target, self::NEW_PASSWORD)
            ->assertStatus(302)
            ->assertSessionHas('error');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $target->fresh()->password));
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    public function test_web_happy_path_hashes_password_invalidates_old_login_and_audits(): void
    {
        // Password login is disabled by a seeded setting; the login-form
        // assertions below need it on (otherwise BOTH attempts bounce and
        // the "old password fails" check would pass vacuously).
        \App\Modules\Admin\Models\AppSetting::put(
            \App\Modules\Common\Support\AuthMethods::SETTING_EMAIL_PASSWORD_ENABLED,
            true
        );

        $staff  = $this->makeStaff(['users.edit']);
        $target = $this->makeTarget();

        $this->be($staff, 'admin');
        $resp = $this->webSetPassword($target, self::NEW_PASSWORD);

        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        // Stored hashed — never plaintext — and the new credential verifies.
        $fresh = $target->fresh();
        $this->assertNotSame(self::NEW_PASSWORD, $fresh->password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $fresh->password));

        // Audit trail: a user.password_set row referencing the target.
        $audit = AdminActionAudit::where('action', AdminActionLogger::USER_PASSWORD_SET)
            ->latest('id')->first();
        $this->assertNotNull($audit, 'Expected a user.password_set audit row.');
        $this->assertSame($target->id, $audit->target_user_id);
        $this->assertSame($staff->id, $audit->admin_id);

        // The old plaintext no longer signs in at the login form...
        // (be($staff,'admin') made 'admin' the DEFAULT guard, so the login
        // controller's Auth::login() would land on the wrong guard — reset.)
        $this->app['auth']->shouldUse('web');
        auth('web')->logout();
        $this->flushHeaders();
        $this->post('/user/login', [
            'email'    => $target->email,
            'password' => self::OLD_PASSWORD,
        ]);
        $this->assertGuest('web');

        // ...while the new one does.
        $login = $this->post('/user/login', [
            'email'    => $target->email,
            'password' => self::NEW_PASSWORD,
        ]);
        $login->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($target->fresh(), 'web');
    }

    public function test_api_happy_path_sets_password_and_audits(): void
    {
        $operator = $this->makeApiOperator();
        $target   = $this->makeTarget();

        $this->withToken($this->token($operator))
            ->postJson("/api/v1/admin/users/{$target->id}/set-password", [
                'password' => self::NEW_PASSWORD,
            ])
            ->assertOk();

        $fresh = $target->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $fresh->password));

        $audit = AdminActionAudit::where('action', AdminActionLogger::USER_PASSWORD_SET)
            ->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($target->id, $audit->target_user_id);
    }
}
