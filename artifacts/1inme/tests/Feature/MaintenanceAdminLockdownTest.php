<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The admin-only maintenance lockdown (`maintenance_admin_only_enabled`) takes
 * every surface offline for everyone EXCEPT users holding a platform admin role
 * — across the back-office admin guard, the session web guard, and
 * token-authenticated API callers. Turning it off restores access for all.
 */
class MaintenanceAdminLockdownTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'U ' . Str::random(4),
            'email'    => 'u-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    /** A web-guard user holding a platform (guard=web) admin role. */
    private function makeWebAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'user-admin', 'guard' => 'web'],
            ['name' => 'User Admin', 'description' => 'Platform admin (web)'],
        );
        $user = $this->makeUser();
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->flushPermissionCache();
        return $user->fresh();
    }

    private function lockdownOn(): void
    {
        AppSetting::put('maintenance_admin_only_enabled', true);
    }

    public function test_guest_is_blocked_on_marketing_when_lockdown_on(): void
    {
        $this->lockdownOn();
        $this->get('/')->assertStatus(503);
    }

    public function test_regular_web_user_is_blocked_when_lockdown_on(): void
    {
        $this->lockdownOn();
        $this->actingAs($this->makeUser())->get('/user')->assertStatus(503);
    }

    public function test_guest_api_gets_503_envelope_when_lockdown_on(): void
    {
        $this->lockdownOn();
        $this->getJson('/api/v1/feed')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'maintenance_mode')
            ->assertJsonPath('error.area', 'all');
    }

    public function test_admin_guard_user_bypasses_lockdown(): void
    {
        $this->lockdownOn();

        $role  = Role::firstOrCreate(['slug' => 'super-admin', 'guard' => 'admin'], ['name' => 'Super Admin']);
        $admin = Admin::create([
            'name'     => 'Admin',
            'email'    => 'admin-' . Str::random(6) . '@example.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);

        // Admin panel stays reachable and the marketing surface is not gated
        // for an authenticated admin.
        $this->actingAs($admin, 'admin')->get('/')->assertStatus(200);
    }

    public function test_web_admin_role_user_bypasses_lockdown(): void
    {
        $this->lockdownOn();
        $this->actingAs($this->makeWebAdmin())->get('/user')->assertDontSee('Scheduled maintenance');
    }

    public function test_token_authenticated_admin_role_user_bypasses_lockdown_on_api(): void
    {
        $this->lockdownOn();
        $admin = $this->makeWebAdmin();

        $this->withToken($admin->createToken('test')->plainTextToken);
        // Not a 503: the admin-role API caller is let through to the real
        // endpoint instead of the maintenance envelope.
        $this->getJson('/api/v1/feed')->assertStatus(200);
    }

    public function test_token_authenticated_regular_user_is_blocked_on_api(): void
    {
        $this->lockdownOn();
        $user = $this->makeUser();

        $this->withToken($user->createToken('test')->plainTextToken);
        $this->getJson('/api/v1/feed')
            ->assertStatus(503)
            ->assertJsonPath('error.area', 'all');
    }

    public function test_disabling_lockdown_restores_access_for_everyone(): void
    {
        AppSetting::put('maintenance_admin_only_enabled', false);
        $this->get('/')->assertStatus(200);
        $this->actingAs($this->makeUser())->get('/user')->assertStatus(200);
    }
}
