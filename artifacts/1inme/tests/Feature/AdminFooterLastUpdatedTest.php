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
        SiteLastUpdated::$gitOutputResolver = null;
        SiteLastUpdated::$manifestPathOverride = null;
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

        // Stub BOTH sources away so even a cache miss cannot resolve a value
        // (git stubbed out, manifest pointed at a nonexistent file), and seed
        // the NONE sentinel that get() caches for an unavailable result.
        SiteLastUpdated::$gitOutputResolver = fn () => null;
        SiteLastUpdated::$manifestPathOverride = '/nonexistent/manifest.json';
        SiteLastUpdated::flush();
        Cache::put('site:last_updated_at', SiteLastUpdated::NONE, 300);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Last updated:');
    }

    /**
     * Production-like fallback: git metadata is unavailable, so the timestamp
     * must come from the Vite build manifest's mtime. Requires a real
     * `npm run build` to have produced public/build/manifest.json (the CI
     * validation step builds first); skipped when the manifest is absent.
     */
    public function test_footer_falls_back_to_manifest_mtime_when_git_unavailable(): void
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            $this->markTestSkipped('Vite build manifest missing — run `npm run build` first.');
        }

        // Stub git as unavailable to force the manifest fallback branch.
        SiteLastUpdated::$gitOutputResolver = fn () => null;
        SiteLastUpdated::flush();

        $resolved = SiteLastUpdated::resolve();

        $this->assertInstanceOf(Carbon::class, $resolved);
        $this->assertSame(filemtime($manifest), $resolved->getTimestamp());

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Last updated:');
        $response->assertSee($resolved->format('M j, Y H:i'));
        $response->assertSee('UTC');
    }

    public function test_meta_endpoint_returns_cached_timestamp_as_json(): void
    {
        $admin = $this->makeAdmin();
        $fixed = Carbon::create(2026, 7, 15, 5, 58, 0, 'UTC');

        Cache::put('site:last_updated_at', $fixed, 300);

        $response = $this->actingAs($admin, 'admin')->getJson(route('admin.meta.last-updated'));

        $response->assertOk();
        $response->assertJson([
            'available' => true,
            'iso'       => $fixed->toIso8601String(),
            'formatted' => 'Jul 15, 2026 05:58 UTC',
        ]);
        $this->assertIsString($response->json('relative'));
    }

    public function test_meta_endpoint_reports_unavailable_when_no_source(): void
    {
        $admin = $this->makeAdmin();

        Cache::put('site:last_updated_at', SiteLastUpdated::NONE, 300);

        $response = $this->actingAs($admin, 'admin')->getJson(route('admin.meta.last-updated'));

        $response->assertOk();
        $response->assertJson([
            'available' => false,
            'iso'       => null,
            'relative'  => null,
        ]);
    }

    public function test_meta_endpoint_requires_admin_auth(): void
    {
        $response = $this->get(route('admin.meta.last-updated'));

        $response->assertRedirect(route('admin.login'));
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
