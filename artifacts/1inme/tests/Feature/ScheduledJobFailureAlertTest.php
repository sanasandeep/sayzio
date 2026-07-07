<?php

namespace Tests\Feature;

use App\Console\Commands\CheckScheduledJobFailures;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\ScheduledJobRun;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the scheduled-job failure watchdog
 * (`scheduled-jobs:check-failures` / CheckScheduledJobFailures).
 *
 * Pins: alert fan-out to ops admins once a registry job racks up 3+
 * consecutive failed runs, idempotency for an open episode (hourly cadence
 * must not spam), the all-clear + re-arm once the job succeeds again, that
 * sub-threshold streaks and non-registry job keys never alert, and that the
 * watchdog itself is registered in the schedule registry.
 */
class ScheduledJobFailureAlertTest extends TestCase
{
    use RefreshDatabase;

    /** A real registry key so the watchdog considers the runs. */
    private const JOB_KEY = 'domains:check-health';

    private function makeOpsAdmin(): User
    {
        $role = Role::create([
            'name'  => 'Ops ' . Str::random(4),
            'slug'  => 'ops-' . Str::lower(Str::random(6)),
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

    private function recordRun(string $key, string $status, int $minutesAgo, ?string $error = null): ScheduledJobRun
    {
        return ScheduledJobRun::create([
            'job_key'     => $key,
            'source'      => ScheduledJobRun::SOURCE_SCHEDULE,
            'status'      => $status,
            'started_at'  => now()->subMinutes($minutesAgo),
            'finished_at' => now()->subMinutes($minutesAgo)->addSeconds(5),
            'runtime'     => 5.0,
            'exit_code'   => $status === ScheduledJobRun::STATUS_FAILED ? 1 : 0,
            'error'       => $error,
        ]);
    }

    private function seedFailureStreak(int $failures, string $key = self::JOB_KEY): void
    {
        // A success further back, then N consecutive failures.
        $this->recordRun($key, ScheduledJobRun::STATUS_SUCCESS, 600);
        for ($i = 0; $i < $failures; $i++) {
            $this->recordRun($key, ScheduledJobRun::STATUS_FAILED, 500 - $i * 60, 'Simulated failure #' . ($i + 1));
        }
    }

    // ─────────────────────────────────────────────────────────────

    public function test_watchdog_is_registered_in_the_schedule_registry(): void
    {
        $def = \App\Modules\Admin\Support\ScheduledJobRegistry::find('scheduled-jobs:check-failures');

        $this->assertNotNull($def, 'watchdog must be a registry-driven scheduled job');
        $this->assertSame('health-checks', $def['group']);
    }

    public function test_alerts_ops_admins_after_three_consecutive_failures(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedFailureStreak(3);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $notes = UserNotification::where('user_id', $ops->id)
            ->where('type', 'scheduled_job_failing')->get();
        $this->assertCount(1, $notes, 'exactly one in-app alert for the ops admin');
        $this->assertStringContainsString(self::JOB_KEY, $notes->first()->data['subject']);
        $this->assertStringContainsString('Simulated failure #3', $notes->first()->data['body']);
        $this->assertSame(self::JOB_KEY, $notes->first()->data['jobs'][0]['key']);

        $state = AppSetting::get(CheckScheduledJobFailures::STATE_KEY, []);
        $job   = $state['jobs'][self::JOB_KEY] ?? [];
        $this->assertTrue((bool) ($job['alerting'] ?? false), 'episode must be open');
        $this->assertSame(3, $job['streak'] ?? null);
        $this->assertNotEmpty($job['last_sent_at'] ?? null);
    }

    public function test_no_alert_below_the_threshold(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedFailureStreak(2);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(
            0,
            UserNotification::where('user_id', $ops->id)->count(),
            'two consecutive failures must not alert yet'
        );
    }

    public function test_success_between_failures_resets_the_streak(): void
    {
        $ops = $this->makeOpsAdmin();

        // failed, failed, success, failed, failed — never 3 in a row.
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 500);
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 400);
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_SUCCESS, 300);
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 200);
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 100);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(0, UserNotification::where('user_id', $ops->id)->count());
    }

    public function test_open_episode_is_not_realerted_and_force_bypasses(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedFailureStreak(3);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);
        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_failing')->count(),
            'second run for the same streak must not re-send'
        );

        // Even a GROWING streak stays quiet inside the re-alert cooldown.
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 10, 'Simulated failure #4');
        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);
        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_failing')->count(),
            'a growing streak within the cooldown must not re-send'
        );

        $this->artisan('scheduled-jobs:check-failures', ['--force' => true])->assertExitCode(0);
        $this->assertSame(
            2,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_failing')->count(),
            '--force must re-send an open episode'
        );
    }

    public function test_growing_streak_realerts_after_cooldown(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedFailureStreak(3);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        // Age the episode past the re-alert cooldown, then grow the streak.
        $state = AppSetting::get(CheckScheduledJobFailures::STATE_KEY, []);
        $state['jobs'][self::JOB_KEY]['last_sent_at'] =
            now()->subHours(CheckScheduledJobFailures::REALERT_COOLDOWN_HOURS + 1)->toIso8601String();
        AppSetting::put(CheckScheduledJobFailures::STATE_KEY, $state);

        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 5, 'Simulated failure #4');
        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(
            2,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_failing')->count(),
            'a grown streak past the cooldown must send a reminder'
        );
    }

    public function test_recovery_all_clear_and_rearm(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedFailureStreak(3);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        // The job succeeds again.
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_SUCCESS, 5);
        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_recovered')->count(),
            'all-clear must be sent once the job succeeds'
        );
        $state = AppSetting::get(CheckScheduledJobFailures::STATE_KEY, []);
        $this->assertArrayNotHasKey(self::JOB_KEY, $state['jobs'] ?? [], 'episode must be closed');

        // A healthy follow-up run sends nothing more.
        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);
        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_recovered')->count()
        );

        // A brand-new streak alerts immediately (re-armed).
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 4);
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 3);
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 2);
        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(
            2,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_failing')->count(),
            'a fresh streak after recovery must alert again'
        );
    }

    public function test_non_registry_job_keys_never_alert(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedFailureStreak(5, 'ghost:job-removed-from-registry');

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(0, UserNotification::where('user_id', $ops->id)->count());
    }

    // ── Admin-tunable threshold & cooldown ───────────────────────

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function setAlertSettings(int $threshold, ?int $cooldownHours = null): void
    {
        $settings = ['threshold' => $threshold];
        if ($cooldownHours !== null) {
            $settings['cooldown_hours'] = $cooldownHours;
        }
        AppSetting::put(CheckScheduledJobFailures::SETTINGS_KEY, $settings);
    }

    public function test_custom_lower_threshold_alerts_earlier(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->setAlertSettings(2);
        $this->seedFailureStreak(2);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_failing')->count(),
            'with an admin threshold of 2, two consecutive failures must alert'
        );
    }

    public function test_custom_higher_threshold_delays_the_alert(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->setAlertSettings(5);
        $this->seedFailureStreak(4);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);
        $this->assertSame(
            0,
            UserNotification::where('user_id', $ops->id)->count(),
            'four failures must stay quiet when the admin threshold is 5'
        );

        // The fifth failure crosses the custom threshold.
        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 10, 'Simulated failure #5');
        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);
        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_failing')->count(),
            'the fifth failure must alert at threshold 5'
        );
    }

    public function test_threshold_below_the_minimum_is_clamped(): void
    {
        $this->setAlertSettings(1);
        $this->assertSame(
            CheckScheduledJobFailures::MIN_THRESHOLD,
            CheckScheduledJobFailures::failureThreshold(),
            'a stored threshold of 1 must clamp to the minimum of ' . CheckScheduledJobFailures::MIN_THRESHOLD
        );

        AppSetting::put(CheckScheduledJobFailures::SETTINGS_KEY, ['threshold' => 'nonsense']);
        $this->assertSame(
            CheckScheduledJobFailures::FAILURE_THRESHOLD,
            CheckScheduledJobFailures::failureThreshold(),
            'a non-numeric stored threshold must fall back to the default'
        );
    }

    public function test_custom_cooldown_governs_growing_streak_reminders(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->setAlertSettings(3, 2); // re-alert reminders after only 2 hours
        $this->seedFailureStreak(3);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        // Age the episode past the CUSTOM 2h cooldown (but well under the 24h default).
        $state = AppSetting::get(CheckScheduledJobFailures::STATE_KEY, []);
        $state['jobs'][self::JOB_KEY]['last_sent_at'] = now()->subHours(3)->toIso8601String();
        AppSetting::put(CheckScheduledJobFailures::STATE_KEY, $state);

        $this->recordRun(self::JOB_KEY, ScheduledJobRun::STATUS_FAILED, 5, 'Simulated failure #4');
        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(
            2,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_failing')->count(),
            'a grown streak past the custom 2h cooldown must send a reminder'
        );
    }

    public function test_admin_can_save_alert_settings_from_the_panel(): void
    {
        $resp = $this->actingAs($this->makeAdmin(), 'admin')->post('/admin/cron-jobs/failure-alert-settings', [
            'threshold'           => 5,
            'cooldown_hours'      => 6,
            'stale_after_minutes' => 30,
        ]);

        $resp->assertRedirect()->assertSessionHas('success');
        $this->assertSame(5, CheckScheduledJobFailures::failureThreshold());
        $this->assertSame(6, CheckScheduledJobFailures::realertCooldownHours());
        $this->assertSame(30 * 60, \App\Modules\Admin\Support\ScheduledJobHealthAlerts::schedulerStaleAfterSeconds());
    }

    public function test_alert_settings_endpoint_enforces_bounds_and_auth(): void
    {
        // Guests can't touch the endpoint (checked first — actingAs persists
        // for the rest of the test case).
        $this->post('/admin/cron-jobs/failure-alert-settings', [
            'threshold'           => 4,
            'cooldown_hours'      => 6,
            'stale_after_minutes' => 30,
        ])->assertRedirect();
        $this->assertNull(
            AppSetting::get(CheckScheduledJobFailures::SETTINGS_KEY),
            'a guest submission must not persist anything'
        );

        // Below the minimum threshold is rejected.
        $this->actingAs($this->makeAdmin(), 'admin')
            ->from('/admin/cron-jobs')
            ->post('/admin/cron-jobs/failure-alert-settings', [
                'threshold'           => 1,
                'cooldown_hours'      => 6,
                'stale_after_minutes' => 30,
            ])
            ->assertSessionHasErrors('threshold');

        // Out-of-range cooldown is rejected.
        $this->actingAs($this->makeAdmin(), 'admin')
            ->from('/admin/cron-jobs')
            ->post('/admin/cron-jobs/failure-alert-settings', [
                'threshold'           => 3,
                'cooldown_hours'      => 9999,
                'stale_after_minutes' => 30,
            ])
            ->assertSessionHasErrors('cooldown_hours');

        // Out-of-range stale threshold is rejected.
        $this->actingAs($this->makeAdmin(), 'admin')
            ->from('/admin/cron-jobs')
            ->post('/admin/cron-jobs/failure-alert-settings', [
                'threshold'           => 3,
                'cooldown_hours'      => 6,
                'stale_after_minutes' => 2,
            ])
            ->assertSessionHasErrors('stale_after_minutes');

        $this->assertNull(
            AppSetting::get(CheckScheduledJobFailures::SETTINGS_KEY),
            'rejected submissions must not persist anything'
        );
    }

    public function test_panel_shows_the_current_alert_settings(): void
    {
        $this->setAlertSettings(7, 12);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->get('/admin/cron-jobs');

        $resp->assertOk()
            ->assertSee('Job-failure alert sensitivity')
            ->assertSee('value="7"', false)
            ->assertSee('value="12"', false);
    }

    public function test_running_rows_are_ignored_when_counting_the_streak(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedFailureStreak(3);
        // An in-flight retry must not mask the streak.
        ScheduledJobRun::create([
            'job_key'    => self::JOB_KEY,
            'source'     => ScheduledJobRun::SOURCE_SCHEDULE,
            'status'     => ScheduledJobRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $this->artisan('scheduled-jobs:check-failures')->assertExitCode(0);

        $this->assertSame(
            1,
            UserNotification::where('user_id', $ops->id)->where('type', 'scheduled_job_failing')->count()
        );
    }
}
