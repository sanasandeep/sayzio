<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression coverage: an active admin-guard session must not break public
 * marketing pages.
 *
 * With an admin session active, Laravel's default guard resolves to an
 * Admin model. Controllers that pass the "current user" into
 * PricingResolver::currencyForUser(?User) (or anything else typed against
 * the web User model) crash with a TypeError (500) unless they resolve the
 * visitor explicitly via $request->user('web'). This bit both `/` and
 * `/pricing` in the past — these tests pin the fix on both surfaces.
 */
class PublicPagesAdminGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    public function test_pricing_page_renders_while_authenticated_on_admin_guard(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/pricing')
            ->assertOk();
    }

    public function test_home_page_renders_while_authenticated_on_admin_guard(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/')
            ->assertOk();
    }

    public function test_pricing_page_still_renders_for_guests(): void
    {
        $this->get('/pricing')->assertOk();
    }
}
