<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\AppLaunchSignup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Guards the public "Notify me when the app launches" endpoint
 * (POST /app-launch/notify) and its admin surface. The endpoint was only
 * smoke-tested by hand, so this pins the abuse-hardening behaviour a future
 * refactor could silently break: the honeypot must never create a row, the
 * duplicate path must not leak which emails are on the list (and must not
 * write a second row), invalid emails must 422, and the per-IP rate limiter
 * must kick in. Also lightly covers the admin list / CSV export / delete.
 */
class AppLaunchNotifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Both the route's throttle middleware and the controller's own
        // per-IP limiter live in the cache, which persists across tests in
        // the same process. Clear it so each test starts from a clean slate
        // and one test's requests can't trip another's limiter.
        $this->app['cache']->flush();
        RateLimiter::clear('app-launch-notify:127.0.0.1');
    }

    private function notifyUrl(): string
    {
        return '/app-launch/notify';
    }

    public function test_valid_signup_creates_a_row(): void
    {
        $resp = $this->postJson($this->notifyUrl(), [
            'email' => 'Fan@Example.com',
            'store' => 'play',
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);

        // Email is stored lowercased/trimmed; context is captured.
        $this->assertDatabaseCount('app_launch_signups', 1);
        $row = AppLaunchSignup::first();
        $this->assertSame('fan@example.com', $row->email);
        $this->assertSame('play', $row->store);
        $this->assertNull($row->notified_at);
    }

    public function test_duplicate_returns_success_without_a_second_row(): void
    {
        AppLaunchSignup::create(['email' => 'dupe@example.com']);

        $resp = $this->postJson($this->notifyUrl(), [
            'email' => 'DUPE@example.com', // different case — still a duplicate
        ]);

        // Response is indistinguishable from a fresh signup (no leak) …
        $resp->assertOk();
        $resp->assertJson(['ok' => true]);
        // … and no second row is written.
        $this->assertDatabaseCount('app_launch_signups', 1);
    }

    public function test_invalid_email_returns_422(): void
    {
        $resp = $this->postJson($this->notifyUrl(), [
            'email' => 'not-an-email',
        ]);

        $resp->assertStatus(422);
        $resp->assertJson(['ok' => false]);
        $this->assertDatabaseCount('app_launch_signups', 0);
    }

    public function test_honeypot_returns_success_but_creates_no_row(): void
    {
        $resp = $this->postJson($this->notifyUrl(), [
            'email'   => 'bot@example.com',
            'website' => 'http://spam.example', // honeypot filled by a bot
        ]);

        // Bots must learn nothing: friendly success, but nothing persisted.
        $resp->assertOk();
        $resp->assertJson(['ok' => true]);
        $this->assertDatabaseCount('app_launch_signups', 0);
    }

    public function test_rate_limiting_kicks_in_after_repeated_attempts(): void
    {
        // The controller allows 5 attempts per IP before answering 429.
        // Re-post the SAME email so attempts 2-5 are harmless duplicates
        // (no extra rows) while still incrementing the limiter.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->notifyUrl(), ['email' => 'repeat@example.com'])
                ->assertOk();
        }

        // The 6th attempt is refused by the rate limiter.
        $resp = $this->postJson($this->notifyUrl(), ['email' => 'repeat@example.com']);
        $resp->assertStatus(429);
        $resp->assertJson(['ok' => false]);

        // Only the first attempt ever created a row.
        $this->assertDatabaseCount('app_launch_signups', 1);
    }

    // ---- Admin surface (light coverage) -----------------------------------

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

    public function test_admin_index_lists_signups(): void
    {
        AppLaunchSignup::create(['email' => 'listed@example.com', 'store' => 'app']);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/app-launch');

        $resp->assertOk();
        $resp->assertSee('listed@example.com', false);
    }

    public function test_admin_export_streams_csv(): void
    {
        AppLaunchSignup::create(['email' => 'export@example.com', 'store' => 'play']);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/app-launch/export');

        $resp->assertOk();
        $this->assertStringContainsString('text/csv', (string) $resp->headers->get('content-type'));
        $csv = $resp->streamedContent();
        $this->assertStringContainsString('email,store,signed_up_at,notified_at', $csv);
        $this->assertStringContainsString('export@example.com', $csv);
    }

    public function test_admin_destroy_deletes_a_signup(): void
    {
        $row = AppLaunchSignup::create(['email' => 'remove@example.com']);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->delete('/admin/app-launch/' . $row->id);

        $resp->assertRedirect();
        $this->assertDatabaseMissing('app_launch_signups', ['id' => $row->id]);
    }
}
