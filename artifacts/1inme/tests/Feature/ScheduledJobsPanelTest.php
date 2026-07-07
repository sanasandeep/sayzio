<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\ScheduledJobRun;
use App\Modules\Admin\Support\ScheduledJobRegistry;
use App\Modules\User\Models\User;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the registry-driven "Scheduled Jobs" control panel:
 *
 *  - the registry ↔ scheduler lockstep guard: every registry definition must
 *    materialise as exactly one schedule event (and nothing else may sneak
 *    into the schedule outside the registry), with cadences preserved;
 *  - persisted pause/resume semantics (AppSetting-backed, honoured by the
 *    scheduler's ->skip() filter, protected jobs refuse);
 *  - the web admin panel (grouped index, pause/resume/run/runs actions);
 *  - the REST parity surface at /api/v1/admin/scheduled-jobs.
 */
class ScheduledJobsPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ScheduledJobRegistry::flush();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

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

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    /** A web user holding the `settings.manage` permission (API admin). */
    private function makeApiAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'platform-settings'],
            ['name' => 'Platform Settings', 'guard' => 'web']
        );
        $perm = Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'Manage Settings', 'group' => 'settings']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = $this->makeUser();
        $user->roles()->attach($role->id);
        $user->flushPermissionCache();

        return $user->fresh();
    }

    /** Authenticate with a REAL Sanctum bearer token (never Sanctum::actingAs). */
    private function asUser(User $user): self
    {
        $this->withToken($user->createToken('mobile-test')->plainTextToken);

        return $this;
    }

    /** Locate the schedule event for a registry key, or null. */
    private function eventFor(string $key): ?Event
    {
        $command = ScheduledJobRegistry::find($key)['command'] ?? null;

        foreach (app(Schedule::class)->events() as $event) {
            if ($event instanceof CallbackEvent) {
                if ($event->description === $key) {
                    return $event;
                }

                continue;
            }

            $normalized = trim(preg_replace(
                '/^php\s+artisan\s+/',
                '',
                Event::normalizeCommand($event->command ?? '')
            ));

            if ($command !== null && $normalized === $command) {
                return $event;
            }
        }

        return null;
    }

    // ── Registry ↔ scheduler lockstep guard ──────────────────────────────

    public function test_every_registry_job_materialises_as_exactly_one_schedule_event(): void
    {
        $defs = ScheduledJobRegistry::all();
        $events = app(Schedule::class)->events();

        // Nothing scheduled outside the registry, nothing in the registry
        // that fails to schedule: counts must match exactly.
        $this->assertSame(count($defs), count($events),
            'The schedule and ScheduledJobRegistry have drifted apart: every scheduled job must be declared in routes/schedules/*.php.');

        foreach (array_keys($defs) as $key) {
            $this->assertNotNull($this->eventFor($key), "Registry job '{$key}' has no matching schedule event.");
        }
    }

    public function test_every_job_declares_a_known_group_and_description(): void
    {
        foreach (ScheduledJobRegistry::all() as $key => $def) {
            $this->assertArrayHasKey($def['group'], ScheduledJobRegistry::GROUPS,
                "Job '{$key}' declares unknown group '{$def['group']}'.");
            $this->assertNotSame('', trim($def['description']), "Job '{$key}' is missing a description.");
        }
    }

    public function test_cadences_are_preserved_exactly(): void
    {
        // Snapshot of representative pre-refactor cadences — these expressions
        // were captured from the original monolithic console.php and must
        // never drift as jobs move between group files.
        $expected = [
            'contacts:sync'               => '*/2 * * * *',
            'creator-digest:weekly'       => '0 8 * * 1',
            'tracking:maintain-partitions' => '30 2 1 * *',
            'queue:work'                  => '* * * * *',
        ];

        foreach ($expected as $key => $expression) {
            $event = $this->eventFor($key);
            $this->assertNotNull($event, "No schedule event for '{$key}'.");
            $this->assertSame($expression, $event->expression, "Cadence drifted for '{$key}'.");
        }
    }

    // ── Pause / resume semantics ─────────────────────────────────────────

    public function test_pausing_a_job_makes_the_scheduler_skip_it(): void
    {
        $event = $this->eventFor('contacts:sync');
        $this->assertNotNull($event);

        $this->assertTrue($event->filtersPass(app()), 'Job should run while not paused.');

        ScheduledJobRegistry::pause('contacts:sync');
        $this->assertTrue(ScheduledJobRegistry::isPaused('contacts:sync'));
        $this->assertFalse($event->filtersPass(app()), 'Paused job must be skipped by the scheduler.');

        ScheduledJobRegistry::resume('contacts:sync');
        $this->assertFalse(ScheduledJobRegistry::isPaused('contacts:sync'));
        $this->assertTrue($event->filtersPass(app()), 'Resumed job must run again.');
    }

    public function test_pause_state_is_persisted_in_app_settings(): void
    {
        ScheduledJobRegistry::pause('contacts:sync');

        $this->assertContains('contacts:sync', AppSetting::get('scheduled_jobs.paused', []));
    }

    public function test_protected_jobs_refuse_to_pause(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ScheduledJobRegistry::pause('subscriptions:renew-due');
    }

    // ── Web admin panel ──────────────────────────────────────────────────

    public function test_web_index_requires_authentication(): void
    {
        $this->get('/admin/cron-jobs')->assertRedirect();
    }

    public function test_web_index_renders_grouped_jobs_for_an_admin(): void
    {
        $resp = $this->actingAs($this->makeAdmin(), 'admin')->get('/admin/cron-jobs');

        $resp->assertOk();
        $resp->assertSee('Scheduled Jobs');
        // Group headings from the registry.
        $resp->assertSee('Billing &amp; Plans', false);
        $resp->assertSee('Health Checks', false);
        // A representative job with its cadence.
        $resp->assertSee('contacts:sync');
        $resp->assertSee('*/2 * * * *');
    }

    public function test_web_pause_and_resume_round_trip(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->post('/admin/cron-jobs/contacts:sync/pause')
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertTrue(ScheduledJobRegistry::isPaused('contacts:sync'));

        $this->actingAs($admin, 'admin')
            ->post('/admin/cron-jobs/contacts:sync/resume')
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertFalse(ScheduledJobRegistry::isPaused('contacts:sync'));
    }

    public function test_web_pause_refuses_a_protected_job(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post('/admin/cron-jobs/subscriptions:renew-due/pause')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse(ScheduledJobRegistry::isPaused('subscriptions:renew-due'));
    }

    public function test_web_pause_rejects_an_unknown_key(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post('/admin/cron-jobs/definitely-not-a-job/pause')
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_web_run_now_executes_the_job_and_records_a_manual_run(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post('/admin/cron-jobs/db:check-pending-migrations/run')
            ->assertRedirect()
            ->assertSessionHas('success');

        $run = ScheduledJobRun::where('job_key', 'db:check-pending-migrations')->latest('id')->first();
        $this->assertNotNull($run, 'Run-now must record a run-history row.');
        $this->assertSame('manual', $run->source);
        $this->assertSame(ScheduledJobRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(0, $run->exit_code);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->runtime);
    }

    public function test_web_runs_endpoint_returns_history_json(): void
    {
        ScheduledJobRun::create([
            'job_key'     => 'contacts:sync',
            'source'      => 'schedule',
            'status'      => ScheduledJobRun::STATUS_SUCCESS,
            'started_at'  => now()->subMinute(),
            'finished_at' => now(),
            'runtime'     => 1.5,
            'exit_code'   => 0,
        ]);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->getJson('/admin/cron-jobs/contacts:sync/runs');

        $resp->assertOk();
        $resp->assertJsonPath('data.job_key', 'contacts:sync');
        $this->assertCount(1, $resp->json('data.runs'));
        $this->assertSame('success', $resp->json('data.runs.0.status'));
        $this->assertSame(0, $resp->json('data.runs.0.exit_code'));
    }

    public function test_web_status_endpoint_returns_live_job_map(): void
    {
        // An unfinished manual run row must surface as running_now so the
        // panel's polling loop keeps going until the run finishes.
        ScheduledJobRun::create([
            'job_key'    => 'contacts:sync',
            'source'     => 'manual',
            'status'     => ScheduledJobRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->getJson('/admin/cron-jobs/status');

        $resp->assertOk();
        $jobs = $resp->json('data.jobs');
        $this->assertIsArray($jobs);
        $this->assertArrayHasKey('contacts:sync', $jobs);
        $this->assertTrue($jobs['contacts:sync']['running_now']);
        // A job with no in-flight run reports not running.
        $this->assertArrayHasKey('db:check-pending-migrations', $jobs);
        $this->assertFalse($jobs['db:check-pending-migrations']['running_now']);
    }

    public function test_web_status_endpoint_reflects_a_finished_run(): void
    {
        ScheduledJobRun::create([
            'job_key'     => 'contacts:sync',
            'source'      => 'manual',
            'status'      => ScheduledJobRun::STATUS_FAILED,
            'started_at'  => now()->subMinute(),
            'finished_at' => now(),
            'runtime'     => 2.5,
            'exit_code'   => 1,
            'error'       => 'boom',
        ]);

        $job = $this->actingAs($this->makeAdmin(), 'admin')
            ->getJson('/admin/cron-jobs/status')
            ->json('data.jobs.contacts:sync');

        $this->assertFalse($job['running_now']);
        $this->assertFalse($job['last_run_ok']);
        $this->assertSame('manual', $job['last_run_source']);
        $this->assertSame('boom', $job['last_run_error']);
        $this->assertSame(1, $job['last_exit_code']);
        $this->assertNotNull($job['last_run']);
        $this->assertNotNull($job['last_run_human']);
    }

    public function test_web_status_endpoint_ignores_stale_orphaned_running_rows(): void
    {
        // A running row abandoned by a killed background runner must not keep
        // the panel polling forever: rows older than 15 minutes are ignored.
        ScheduledJobRun::create([
            'job_key'    => 'contacts:sync',
            'source'     => 'manual',
            'status'     => ScheduledJobRun::STATUS_RUNNING,
            'started_at' => now()->subMinutes(30),
        ]);

        $job = $this->actingAs($this->makeAdmin(), 'admin')
            ->getJson('/admin/cron-jobs/status')
            ->json('data.jobs.contacts:sync');

        $this->assertFalse($job['running_now']);
    }

    public function test_web_status_endpoint_requires_authentication(): void
    {
        // Admin web routes redirect unauthenticated requests to the login
        // page (same behavior as the index).
        $this->getJson('/admin/cron-jobs/status')->assertRedirect();
    }

    public function test_web_run_now_flashes_the_watch_window_flag(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->post('/admin/cron-jobs/db:check-pending-migrations/run')
            ->assertRedirect()
            ->assertSessionHas('ran_job', 'db:check-pending-migrations');
    }

    public function test_web_runs_endpoint_404s_for_an_unknown_key(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->getJson('/admin/cron-jobs/definitely-not-a-job/runs')
            ->assertStatus(404);
    }

    // ── REST parity: /api/v1/admin/scheduled-jobs ────────────────────────

    public function test_api_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/scheduled-jobs')->assertStatus(401);
        $this->postJson('/api/v1/admin/scheduled-jobs/contacts:sync/pause')->assertStatus(401);
    }

    public function test_api_forbidden_for_a_non_admin_token(): void
    {
        $this->asUser($this->makeUser());

        $this->getJson('/api/v1/admin/scheduled-jobs')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_api_index_returns_grouped_jobs(): void
    {
        $this->asUser($this->makeApiAdmin());

        $resp = $this->getJson('/api/v1/admin/scheduled-jobs');

        $resp->assertOk();
        $this->assertIsString($resp->json('data.master_cron_line'));
        $this->assertContains($resp->json('data.scheduler.state'), ['ok', 'stale', 'unknown']);

        $groups = collect($resp->json('data.groups'));
        $this->assertNotEmpty($groups);

        // Every group slug must come from the registry (plus optional "other").
        foreach ($groups as $group) {
            $this->assertTrue(
                $group['slug'] === 'other' || array_key_exists($group['slug'], ScheduledJobRegistry::GROUPS),
                "Unexpected group '{$group['slug']}' in API payload."
            );
        }

        // Total job count matches the registry, and payloads carry the new fields.
        $jobs = $groups->flatMap(fn ($g) => $g['jobs']);
        $this->assertSame(count(ScheduledJobRegistry::all()), $jobs->count());

        $sample = $jobs->firstWhere('key', 'contacts:sync');
        $this->assertNotNull($sample);
        $this->assertSame('syncing-integrations', $sample['group']);
        $this->assertSame('*/2 * * * *', $sample['expression']);
        $this->assertFalse($sample['protected']);
        $this->assertFalse($sample['paused']);

        $protected = $jobs->firstWhere('key', 'subscriptions:renew-due');
        $this->assertNotNull($protected);
        $this->assertTrue($protected['protected']);
    }

    public function test_api_pause_and_resume_round_trip(): void
    {
        $this->asUser($this->makeApiAdmin());

        $this->postJson('/api/v1/admin/scheduled-jobs/contacts:sync/pause')
            ->assertOk()
            ->assertJsonPath('data.paused', true);
        $this->assertTrue(ScheduledJobRegistry::isPaused('contacts:sync'));

        // Paused state is reflected in the index payload.
        $jobs = collect($this->getJson('/api/v1/admin/scheduled-jobs')->json('data.groups'))
            ->flatMap(fn ($g) => $g['jobs']);
        $this->assertTrue($jobs->firstWhere('key', 'contacts:sync')['paused']);

        $this->postJson('/api/v1/admin/scheduled-jobs/contacts:sync/resume')
            ->assertOk()
            ->assertJsonPath('data.paused', false);
        $this->assertFalse(ScheduledJobRegistry::isPaused('contacts:sync'));
    }

    public function test_api_pause_refuses_a_protected_job_with_422(): void
    {
        $this->asUser($this->makeApiAdmin());

        $this->postJson('/api/v1/admin/scheduled-jobs/subscriptions:renew-due/pause')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'job_protected');

        $this->assertFalse(ScheduledJobRegistry::isPaused('subscriptions:renew-due'));
    }

    public function test_api_pause_404s_for_an_unknown_key(): void
    {
        $this->asUser($this->makeApiAdmin());

        $this->postJson('/api/v1/admin/scheduled-jobs/definitely-not-a-job/pause')
            ->assertStatus(404);
    }

    public function test_api_run_now_records_a_manual_run(): void
    {
        $this->asUser($this->makeApiAdmin());

        $this->postJson('/api/v1/admin/scheduled-jobs/db:check-pending-migrations/run')
            ->assertOk()
            ->assertJsonPath('data.started', true);

        $run = ScheduledJobRun::where('job_key', 'db:check-pending-migrations')->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame('manual', $run->source);
        $this->assertSame(ScheduledJobRun::STATUS_SUCCESS, $run->status);
    }

    public function test_api_runs_endpoint_returns_history(): void
    {
        ScheduledJobRun::create([
            'job_key'     => 'contacts:sync',
            'source'      => 'manual',
            'status'      => ScheduledJobRun::STATUS_FAILED,
            'started_at'  => now()->subMinute(),
            'finished_at' => now(),
            'runtime'     => 0.4,
            'exit_code'   => 1,
            'error'       => 'boom',
        ]);

        $this->asUser($this->makeApiAdmin());

        $resp = $this->getJson('/api/v1/admin/scheduled-jobs/contacts:sync/runs');

        $resp->assertOk();
        $resp->assertJsonPath('data.job_key', 'contacts:sync');
        $this->assertSame('failed', $resp->json('data.runs.0.status'));
        $this->assertSame('boom', $resp->json('data.runs.0.error'));
        $this->assertSame(1, $resp->json('data.runs.0.exit_code'));
    }

    // ── "Failing repeatedly" streak badge (web + API parity) ─────────────

    /** Seed a finished run-history row for a job. */
    private function seedRun(string $key, string $status, int $minutesAgo, ?string $error = null): void
    {
        ScheduledJobRun::create([
            'job_key'     => $key,
            'source'      => 'schedule',
            'status'      => $status,
            'started_at'  => now()->subMinutes($minutesAgo),
            'finished_at' => now()->subMinutes($minutesAgo)->addSeconds(5),
            'runtime'     => 5.0,
            'exit_code'   => $status === ScheduledJobRun::STATUS_FAILED ? 1 : 0,
            'error'       => $error,
        ]);
    }

    public function test_web_index_shows_failing_repeatedly_badge_after_three_consecutive_failures(): void
    {
        // An old success followed by 3 consecutive failures ⇒ streak of 3.
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_SUCCESS, 60);
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 30, 'boom');
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 20, 'boom');
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 10, 'boom');

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->get('/admin/cron-jobs');

        $resp->assertOk();
        $resp->assertSee('Failing repeatedly (3 in a row)');
    }

    public function test_web_index_hides_the_badge_below_the_threshold_and_after_recovery(): void
    {
        // Two failures — below the 3-failure threshold.
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 30, 'boom');
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 20, 'boom');

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->get('/admin/cron-jobs')
            ->assertOk()
            ->assertDontSee('Failing repeatedly');

        // A third failure crosses the threshold…
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 10, 'boom');
        $this->actingAs($admin, 'admin')->get('/admin/cron-jobs')
            ->assertOk()
            ->assertSee('Failing repeatedly (3 in a row)');

        // …and a fresh success clears the badge immediately (no watchdog run
        // needed — the streak is computed live from the run history).
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_SUCCESS, 1);
        $this->actingAs($admin, 'admin')->get('/admin/cron-jobs')
            ->assertOk()
            ->assertDontSee('Failing repeatedly');
    }

    public function test_api_index_includes_failing_streak_fields(): void
    {
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_SUCCESS, 60);
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 30, 'boom');
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 20, 'boom');
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 10, 'boom');

        $this->asUser($this->makeApiAdmin());

        $jobs = collect($this->getJson('/api/v1/admin/scheduled-jobs')->assertOk()->json('data.groups'))
            ->flatMap(fn ($g) => $g['jobs']);

        $failing = $jobs->firstWhere('key', 'contacts:sync');
        $this->assertNotNull($failing);
        $this->assertSame(3, $failing['failing_streak']);
        $this->assertTrue($failing['failing_repeatedly']);

        // A healthy job carries the fields too, zeroed/false.
        $healthy = $jobs->firstWhere('key', 'db:check-pending-migrations');
        $this->assertNotNull($healthy);
        $this->assertSame(0, $healthy['failing_streak']);
        $this->assertFalse($healthy['failing_repeatedly']);

        // Recovery clears the flag in the API payload as well.
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_SUCCESS, 1);
        $jobs = collect($this->getJson('/api/v1/admin/scheduled-jobs')->json('data.groups'))
            ->flatMap(fn ($g) => $g['jobs']);
        $recovered = $jobs->firstWhere('key', 'contacts:sync');
        $this->assertSame(0, $recovered['failing_streak']);
        $this->assertFalse($recovered['failing_repeatedly']);
    }

    public function test_api_cron_jobs_reference_includes_failing_streak_fields(): void
    {
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 30, 'boom');
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 20, 'boom');
        $this->seedRun('contacts:sync', ScheduledJobRun::STATUS_FAILED, 10, 'boom');

        $this->asUser($this->makeApiAdmin());

        $jobs = collect($this->getJson('/api/v1/admin/cron-jobs')->assertOk()->json('data.jobs'));

        $failing = $jobs->first(fn ($j) => str_starts_with($j['command'], 'contacts:sync'));
        $this->assertNotNull($failing);
        $this->assertSame(3, $failing['failing_streak']);
        $this->assertTrue($failing['failing_repeatedly']);
    }
}
