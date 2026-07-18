<?php

namespace Tests\Feature;

use App\Console\Commands\CheckZioBrowserReleaseFreshness;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\ZioBrowserRelease;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the download-links staleness watchdog
 * (`zio-browser:check-freshness`): ZioBrowserRelease::refresh() stamps the
 * failure streak into app_settings, the check alerts ops admins once the
 * streak exceeds the 24h threshold (one-shot within the cooldown), and a
 * successful refresh closes the episode with an all-clear.
 *
 * Note: the command emails via Emailer (Mail::raw under the hood), which
 * MailFake records as a no-op, so these tests assert on the reliable in-app
 * notification signal (and keep Mail::fake() only to stop real delivery).
 */
class CheckZioBrowserReleaseFreshnessTest extends TestCase
{
    use RefreshDatabase;

    /** A user holding the ops-alerts permission (the alert audience). */
    private function makeOpsAdmin(): User
    {
        $role = Role::create([
            'name'  => 'Ops ' . Str::random(4),
            'slug'  => 'ops-' . Str::random(4),
            'guard' => 'web',
        ]);
        $perm = Permission::firstOrCreate(
            ['slug' => 'user.ops_alerts.receive'],
            ['name' => 'Receive operational alerts', 'group' => 'user-app'],
        );
        $role->permissions()->attach($perm->id);

        $user = User::create([
            'name'              => 'Ops Olivia',
            'email'             => 'olivia' . Str::random(6) . '@ops.test',
            'password'          => bcrypt('secret'),
            'status'            => 'active',
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->id);
        $user->flushPermissionCache();

        return $user;
    }

    private function putHealth(array $state): void
    {
        AppSetting::put(ZioBrowserRelease::HEALTH_KEY, $state);
    }

    private function health(): array
    {
        $state = AppSetting::get(ZioBrowserRelease::HEALTH_KEY, []);

        return is_array($state) ? $state : [];
    }

    public function test_failed_refresh_opens_failure_streak_and_success_clears_it(): void
    {
        // First call fails (500), second succeeds — one fake with a sequence,
        // because a second Http::fake() would not override the first stub.
        Http::fake([
            'api.github.com/*' => Http::sequence()
                ->push('nope', 500)
                ->push([[
                    'draft'      => false,
                    'prerelease' => false,
                    'tag_name'   => 'zio-browser-v9.9.9',
                    'assets'     => [
                        ['name' => 'SayZio.Browser-9.9.9-arm64.dmg', 'browser_download_url' => 'https://x/a.dmg'],
                        ['name' => 'SayZio.Browser-9.9.9.dmg', 'browser_download_url' => 'https://x/b.dmg'],
                        ['name' => 'SayZio.Browser.Setup.9.9.9.exe', 'browser_download_url' => 'https://x/c.exe'],
                    ],
                ]], 200),
        ]);

        $this->assertFalse(ZioBrowserRelease::refresh());

        $state = $this->health();
        $this->assertNotEmpty($state['failing_since'] ?? null);
        $this->assertNotEmpty($state['last_failure_at'] ?? null);
        $this->assertNotEmpty($state['last_error'] ?? null);

        $this->assertTrue(ZioBrowserRelease::refresh());

        $state = $this->health();
        $this->assertArrayNotHasKey('failing_since', $state);
        $this->assertNotEmpty($state['last_success_at'] ?? null);
    }

    public function test_no_alert_below_staleness_threshold(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();

        $this->putHealth(['failing_since' => now()->subHours(6)->toIso8601String()]);

        $this->artisan('zio-browser:check-freshness')->assertExitCode(0);

        $this->assertSame(0, UserNotification::where('user_id', $admin->id)->count());
        $this->assertEmpty($this->health()['alerting'] ?? null);
    }

    public function test_alerts_once_past_threshold_and_not_again_within_cooldown(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();

        $this->putHealth([
            'failing_since'   => now()->subHours(30)->toIso8601String(),
            'last_success_at' => now()->subDays(2)->toIso8601String(),
            'last_error'      => 'GitHub release fetch failed or returned no usable zio-browser release',
        ]);

        $this->artisan('zio-browser:check-freshness')->assertExitCode(0);

        $alerts = UserNotification::where('user_id', $admin->id)
            ->where('type', 'zio_browser_release_stale')->get();
        $this->assertCount(1, $alerts);
        $this->assertTrue((bool) ($this->health()['alerting'] ?? false));

        // Second run inside the cooldown: no duplicate spam.
        $this->artisan('zio-browser:check-freshness')->assertExitCode(0);
        $this->assertSame(1, UserNotification::where('user_id', $admin->id)
            ->where('type', 'zio_browser_release_stale')->count());
    }

    public function test_realerts_after_cooldown_expires(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();

        $this->putHealth([
            'failing_since' => now()->subDays(4)->toIso8601String(),
            'alerting'      => true,
            'last_sent_at'  => now()->subHours(CheckZioBrowserReleaseFreshness::REALERT_COOLDOWN_HOURS + 1)->toIso8601String(),
        ]);

        $this->artisan('zio-browser:check-freshness')->assertExitCode(0);

        $this->assertSame(1, UserNotification::where('user_id', $admin->id)
            ->where('type', 'zio_browser_release_stale')->count());
    }

    public function test_recovery_sends_all_clear_and_clears_episode(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();

        // Episode open, but the streak has since been cleared by a success.
        $this->putHealth([
            'last_success_at' => now()->toIso8601String(),
            'alerting'        => true,
            'last_sent_at'    => now()->subHours(3)->toIso8601String(),
        ]);

        $this->artisan('zio-browser:check-freshness')->assertExitCode(0);

        $this->assertSame(1, UserNotification::where('user_id', $admin->id)
            ->where('type', 'zio_browser_release_recovered')->count());

        $state = $this->health();
        $this->assertArrayNotHasKey('alerting', $state);
        $this->assertArrayNotHasKey('last_sent_at', $state);

        // Healthy re-run: silent.
        $this->artisan('zio-browser:check-freshness')->assertExitCode(0);
        $this->assertSame(1, UserNotification::where('user_id', $admin->id)->count());
    }

    public function test_open_episode_helper_feeds_dashboard_banner(): void
    {
        $this->assertNull(CheckZioBrowserReleaseFreshness::openEpisode());

        $this->putHealth([
            'failing_since'   => now()->subHours(30)->toIso8601String(),
            'last_success_at' => now()->subDays(2)->toIso8601String(),
            'alerting'        => true,
        ]);

        $episode = CheckZioBrowserReleaseFreshness::openEpisode();
        $this->assertIsArray($episode);
        $this->assertNotEmpty($episode['failing_since']);
        $this->assertNotEmpty($episode['last_success_at']);
        $this->assertArrayHasKey('version', $episode);
    }
}
