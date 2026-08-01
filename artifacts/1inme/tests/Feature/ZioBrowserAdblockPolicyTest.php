<?php

namespace Tests\Feature;

use App\Modules\Admin\Controllers\ZioBrowserAdblockPolicyController;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the Zio Browser admin-mandated ad-block policy (Task #6453):
 *
 *   GET    /admin/zio-adblock-policy   (admin view)
 *   POST   /admin/zio-adblock-policy   (add domains to allow/block)
 *   DELETE /admin/zio-adblock-policy   (remove a domain)
 *   GET    /api/v1/zio-browser/adblock-policy (public versioned API + ETag/304)
 *
 * The policy lives in the `zio_browser_adblock_policy` app setting as
 * {version, allow, block, updated_at, audit[≤50]}. Every mutation bumps
 * the version, which drives the public API's `"v{n}"` ETag.
 */
class ZioBrowserAdblockPolicyTest extends TestCase
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

    public function test_admin_page_requires_authentication(): void
    {
        $this->get('/admin/zio-adblock-policy')->assertRedirect();
    }

    public function test_admin_page_renders(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/zio-adblock-policy')
            ->assertOk()
            ->assertSee('Admin-mandated ad-block policy');
    }

    public function test_adding_domains_normalizes_bumps_version_and_audits(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->post('/admin/zio-adblock-policy', [
                'list'    => 'block',
                'domains' => "HTTPS://WWW.Ads.Example.COM:8080/x, dup.com\ndup.com not_a_domain",
            ])
            ->assertRedirect(route('admin.zio-adblock-policy.index'));

        $policy = ZioBrowserAdblockPolicyController::policy();
        $this->assertSame(1, $policy['version']);
        $this->assertSame(['ads.example.com', 'dup.com'], $policy['block']);
        $this->assertSame([], $policy['allow']);
        $this->assertNotNull($policy['updated_at']);

        $this->assertCount(1, $policy['audit']);
        $this->assertSame('add', $policy['audit'][0]['action']);
        $this->assertSame('block', $policy['audit'][0]['list']);
        $this->assertSame(['ads.example.com', 'dup.com'], $policy['audit'][0]['domains']);
        $this->assertSame($admin->email, $policy['audit'][0]['admin']);
    }

    public function test_adding_to_allow_removes_from_block_keeping_lists_disjoint(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')->post('/admin/zio-adblock-policy', [
            'list' => 'block', 'domains' => 'example.com',
        ]);
        $this->actingAs($admin, 'admin')->post('/admin/zio-adblock-policy', [
            'list' => 'allow', 'domains' => 'example.com',
        ]);

        $policy = ZioBrowserAdblockPolicyController::policy();
        $this->assertSame(['example.com'], $policy['allow']);
        $this->assertSame([], $policy['block']);
        $this->assertSame(2, $policy['version']);
    }

    public function test_removing_a_domain_bumps_version(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->post('/admin/zio-adblock-policy', [
            'list' => 'allow', 'domains' => 'keep.com remove.com',
        ]);

        $this->actingAs($admin, 'admin')
            ->delete('/admin/zio-adblock-policy', ['list' => 'allow', 'domain' => 'remove.com'])
            ->assertRedirect(route('admin.zio-adblock-policy.index'));

        $policy = ZioBrowserAdblockPolicyController::policy();
        $this->assertSame(['keep.com'], $policy['allow']);
        $this->assertSame(2, $policy['version']);
        $this->assertSame('remove', $policy['audit'][0]['action']);
    }

    public function test_audit_trail_is_capped_at_50_entries(): void
    {
        AppSetting::put('zio_browser_adblock_policy', [
            'version'    => 60,
            'allow'      => [],
            'block'      => [],
            'updated_at' => now()->toIso8601String(),
            'audit'      => array_fill(0, 50, ['action' => 'add', 'list' => 'block', 'domains' => ['old.com'], 'admin' => 'x', 'at' => 'y']),
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')->post('/admin/zio-adblock-policy', [
            'list' => 'block', 'domains' => 'new.com',
        ]);

        $policy = ZioBrowserAdblockPolicyController::policy();
        $this->assertCount(50, $policy['audit']);
        $this->assertSame(['new.com'], $policy['audit'][0]['domains']);
        $this->assertSame(61, $policy['version']);
    }

    public function test_public_api_returns_policy_with_version_etag(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')->post('/admin/zio-adblock-policy', [
            'list' => 'block', 'domains' => 'ads.bad.com',
        ]);

        $resp = $this->getJson('/api/v1/zio-browser/adblock-policy');
        $resp->assertOk()
            ->assertHeader('ETag', '"v1"')
            ->assertJson(['data' => ['version' => 1, 'allow' => [], 'block' => ['ads.bad.com']]]);
    }

    public function test_public_api_returns_304_on_matching_etag(): void
    {
        $this->getJson('/api/v1/zio-browser/adblock-policy')
            ->assertOk()
            ->assertHeader('ETag', '"v0"')
            ->assertJson(['data' => ['version' => 0, 'allow' => [], 'block' => []]]);

        $this->getJson('/api/v1/zio-browser/adblock-policy', ['If-None-Match' => '"v0"'])
            ->assertStatus(304);

        // A policy change invalidates the ETag.
        $this->actingAs($this->makeAdmin(), 'admin')->post('/admin/zio-adblock-policy', [
            'list' => 'allow', 'domains' => 'ok.com',
        ]);

        $this->getJson('/api/v1/zio-browser/adblock-policy', ['If-None-Match' => '"v0"'])
            ->assertOk()
            ->assertHeader('ETag', '"v1"');
    }
}
