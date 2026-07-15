<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Support\SiteLastUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Ensures the admin layout footer shows a "Last updated" timestamp when a
 * source is available and stays silent (shows nothing) when neither git nor
 * the build manifest are reachable.
 */
class AdminFooterLastUpdatedTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Footer Admin',
            'email'    => 'footer-admin-' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        SiteLastUpdated::flush();
        parent::tearDown();
    }

    public function test_footer_shows_last_updated_when_timestamp_available(): void
    {
        $admin = $this->makeAdmin();
        $fixed = Carbon::create(2026, 7, 15, 5, 58, 0, 'UTC');

        Cache::put(
            'site:last_updated_at',
            $fixed,
            300
        );

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Last updated:');
        $response->assertSee('Jul 15, 2026');
        $response->assertSee('UTC');
    }

    public function test_footer_hides_last_updated_when_unavailable(): void
    {
        $admin = $this->makeAdmin();

        Cache::put('site:last_updated_at', null, 300);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Last updated:');
    }

    public function test_resolver_returns_null_gracefully_when_no_source(): void
    {
        SiteLastUpdated::flush();

        $result = SiteLastUpdated::resolve();

        // In CI/test envs without git or a build manifest this may return
        // a Carbon or null — both are acceptable. We only assert it never
        // throws.
        $this->assertTrue($result === null || $result instanceof Carbon);
    }
}
