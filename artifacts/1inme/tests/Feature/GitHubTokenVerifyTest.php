<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The admin "Verify token" button (POST admin/integrations/github/test)
 * makes a live GitHub API call per click. It must be throttled per admin
 * (6/min) so a stuck or spammed button cannot burn the GitHub rate limit,
 * flashing a friendly "wait" message instead of hitting GitHub.
 */
class GitHubTokenVerifyTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.github.repo'  => 'example/repo',
            'services.github.token' => 'ghp_test_token',
        ]);
    }

    public function test_verify_probes_github_and_flashes_success(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['id' => 1], 200),
        ]);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->from(route('admin.integrations.github.edit'))
            ->post(route('admin.integrations.github.test'));

        $resp->assertRedirect(route('admin.integrations.github.edit'));
        $resp->assertSessionHas('success');
        Http::assertSentCount(1);
    }

    public function test_rejected_token_flashes_error(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401),
        ]);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.integrations.github.test'));

        $resp->assertSessionHas('error');
    }

    public function test_rapid_clicks_are_throttled_without_hitting_github(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['id' => 1], 200),
        ]);

        $admin = $this->makeAdmin();

        // Burn the 6/min allowance.
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($admin, 'admin')
                ->post(route('admin.integrations.github.test'))
                ->assertSessionHas('success');
        }
        Http::assertSentCount(6);

        // The 7th rapid click is refused with a friendly flash and no API call.
        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.integrations.github.test'));

        $resp->assertSessionHas('error');
        $this->assertStringContainsString('wait', strtolower(session('error')));
        Http::assertSentCount(6);
    }

    public function test_throttle_is_per_admin(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['id' => 1], 200),
        ]);

        $first = $this->makeAdmin();
        RateLimiter::clear('github-token-test:' . $first->id);
        for ($i = 0; $i < 6; $i++) {
            RateLimiter::hit('github-token-test:' . $first->id, 60);
        }

        // A different admin is unaffected by the first admin's spam.
        $other = $this->makeAdmin();
        $this->actingAs($other, 'admin')
            ->post(route('admin.integrations.github.test'))
            ->assertSessionHas('success');
    }

    /**
     * Regression: AdminAuth middleware never calls Auth::shouldUse('admin'),
     * so in production $request->user() resolves the DEFAULT (web) guard —
     * often null or a different user. The limiter must key on the admin
     * guard identity, not whatever Request::user() happens to return.
     */
    public function test_throttle_keys_on_admin_guard_not_default_guard(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['id' => 1], 200),
        ]);

        $admin = $this->makeAdmin();
        $webUser = \App\Modules\User\Models\User::factory()->create();

        // Burn the limiter for the ADMIN's id only.
        for ($i = 0; $i < 6; $i++) {
            RateLimiter::hit('github-token-test:' . $admin->id, 60);
        }
        RateLimiter::clear('github-token-test:' . $webUser->id);

        // Log in the admin first, then a web user LAST so the default guard
        // (what Request::user() reads) resolves the web user — mimicking
        // production where AdminAuth doesn't switch the default guard.
        $this->be($admin, 'admin');
        $this->be($webUser, 'web');

        $resp = $this->post(route('admin.integrations.github.test'));

        // Correct keying (admin guard) ⇒ throttled. Buggy keying
        // (Request::user() → web user) would slip through as success.
        $resp->assertSessionHas('error');
        $this->assertStringContainsString('wait', strtolower(session('error')));
        Http::assertSentCount(0);
    }
}
