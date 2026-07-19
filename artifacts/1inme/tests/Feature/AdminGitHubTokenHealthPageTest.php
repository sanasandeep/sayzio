<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Services\Integrations\GitHubTokenHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminGitHubTokenHealthPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        return Admin::create([
            'name'     => 'GH Admin',
            'email'    => 'gh-admin-' . uniqid() . '@example.com',
            'password' => 'secret-password',
            'status'   => 'active',
            'role_id'  => $role->id,
        ]);
    }

    public function test_page_shows_never_verified_when_no_probe_recorded(): void
    {
        AppSetting::put(GitHubTokenHealth::STATE_KEY, []);

        $resp = $this->actingAs($this->admin(), 'admin')->get(route('admin.integrations.github.edit'));
        $resp->assertOk();
        $resp->assertSee('Never verified yet');
        $resp->assertSee('Verify token');
    }

    public function test_page_renders_persisted_last_probe_with_expiry(): void
    {
        AppSetting::put(GitHubTokenHealth::STATE_KEY, [
            'last_probe' => [
                'status'     => 'ok',
                'detail'     => 'GitHub token authenticates against acme/repo (expires 2026-10-13).',
                'expires_at' => '2026-10-13T06:22:33+00:00',
                'checked_at' => now()->subHours(3)->toIso8601String(),
                'source'     => 'scheduled',
            ],
        ]);

        $resp = $this->actingAs($this->admin(), 'admin')->get(route('admin.integrations.github.edit'));
        $resp->assertOk();
        $resp->assertSee('Last checked 3 hours ago');
        $resp->assertSee('scheduled check');
        $resp->assertSee('GitHub token authenticates against acme/repo', false);
        $resp->assertSee('Token expires Oct 13, 2026');
    }

    public function test_verify_button_persists_probe_and_flashes_result(): void
    {
        AppSetting::put(GitHubTokenHealth::STATE_KEY, []);
        config(['services.github.repo' => 'acme/repo', 'services.github.token' => 'ghp_test']);
        Http::fake([
            'api.github.com/*' => Http::response(['id' => 1], 200, [
                'github-authentication-token-expiration' => '2026-10-13 06:22:33 UTC',
            ]),
        ]);

        $resp = $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.integrations.github.test'));

        $resp->assertRedirect(route('admin.integrations.github.edit'));
        $resp->assertSessionHas('success');

        $probe = GitHubTokenHealth::lastProbe();
        $this->assertNotNull($probe);
        $this->assertSame('ok', $probe['status']);
        $this->assertSame('manual', $probe['source']);
        $this->assertSame('2026-10-13T06:22:33+00:00', $probe['expires_at']);
    }

    public function test_scheduled_check_persists_probe(): void
    {
        AppSetting::put(GitHubTokenHealth::STATE_KEY, []);
        config(['services.github.repo' => 'acme/repo', 'services.github.token' => 'ghp_test']);
        Http::fake(['api.github.com/*' => Http::response(['id' => 1], 401)]);

        GitHubTokenHealth::check();

        $probe = GitHubTokenHealth::lastProbe();
        $this->assertNotNull($probe);
        $this->assertSame('rejected', $probe['status']);
        $this->assertSame('scheduled', $probe['source']);
    }
}
