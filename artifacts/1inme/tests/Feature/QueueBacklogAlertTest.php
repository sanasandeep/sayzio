<?php

namespace Tests\Feature;

use App\Console\Commands\CheckQueueBacklog;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the queue-backlog watchdog (`queue:check-backlog` /
 * CheckQueueBacklog) — the safety net for login-event delivery when the
 * queue worker is down (RecordLoginEventJob piles up unprocessed).
 *
 * Pins: alert fan-out to ops admins once enough stale pending jobs
 * accumulate, idempotency for an open episode, the all-clear + re-arm once
 * the backlog drains, that fresh/reserved jobs never count as stale, and
 * that the watchdog itself is registered in the schedule registry.
 */
class QueueBacklogAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The test env runs the sync queue driver; the watchdog inspects the
        // database connection's jobs table, so point the default at it.
        config(['queue.default' => 'database']);
    }

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

    /** Insert a raw pending row into the `jobs` table. */
    private function seedPendingJob(int $minutesOld, ?int $reservedMinutesAgo = null): void
    {
        $ts = now()->subMinutes($minutesOld)->getTimestamp();

        DB::table('jobs')->insert([
            'queue'        => 'default',
            'payload'      => json_encode(['displayName' => 'App\\Jobs\\RecordLoginEventJob']),
            'attempts'     => 0,
            'reserved_at'  => $reservedMinutesAgo !== null ? now()->subMinutes($reservedMinutesAgo)->getTimestamp() : null,
            'available_at' => $ts,
            'created_at'   => $ts,
        ]);
    }

    private function seedStaleBacklog(int $count, int $minutesOld = 30): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->seedPendingJob($minutesOld + $i);
        }
    }

    // ─────────────────────────────────────────────────────────────

    public function test_watchdog_is_registered_in_the_schedule_registry(): void
    {
        $def = \App\Modules\Admin\Support\ScheduledJobRegistry::find('queue:check-backlog');

        $this->assertNotNull($def, 'watchdog must be a registry-driven scheduled job');
        $this->assertSame('health-checks', $def['group']);
        $this->assertTrue((bool) ($def['protected'] ?? false), 'queue watchdog must not be pausable');
    }

    public function test_alerts_ops_admins_when_stale_backlog_reaches_threshold(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedStaleBacklog(CheckQueueBacklog::backlogThreshold());

        $this->artisan('queue:check-backlog')->assertExitCode(0);

        $notes = UserNotification::where('user_id', $ops->id)
            ->where('type', 'queue_backlog_unhealthy')->get();
        $this->assertCount(1, $notes, 'exactly one in-app alert for the ops admin');
        $this->assertStringContainsString('worker may be down', $notes->first()->data['subject']);
        $this->assertStringContainsString('login-event recording', $notes->first()->data['body']);
        $this->assertStringContainsString('queue:work --stop-when-empty', $notes->first()->data['body']);

        $state = AppSetting::get(CheckQueueBacklog::STATE_KEY, []);
        $this->assertTrue((bool) ($state['alerting'] ?? false), 'episode must be open');
        $this->assertSame(CheckQueueBacklog::backlogThreshold(), $state['count'] ?? null);
        $this->assertNotEmpty($state['last_sent_at'] ?? null);
    }

    public function test_no_alert_below_the_threshold(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedStaleBacklog(CheckQueueBacklog::backlogThreshold() - 1);

        $this->artisan('queue:check-backlog')->assertExitCode(0);

        $this->assertSame(0, UserNotification::where('user_id', $ops->id)->count());
    }

    public function test_fresh_and_reserved_jobs_do_not_count_as_stale(): void
    {
        $ops = $this->makeOpsAdmin();

        // Fresh jobs still inside the grace window.
        for ($i = 0; $i < CheckQueueBacklog::backlogThreshold(); $i++) {
            $this->seedPendingJob(1);
        }
        // Jobs a worker has already reserved (in flight).
        for ($i = 0; $i < CheckQueueBacklog::backlogThreshold(); $i++) {
            $this->seedPendingJob(30, reservedMinutesAgo: 1);
        }

        $this->artisan('queue:check-backlog')->assertExitCode(0);

        $this->assertSame(0, UserNotification::where('user_id', $ops->id)->count());
    }

    public function test_open_episode_is_not_realerted(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedStaleBacklog(CheckQueueBacklog::backlogThreshold());

        $this->artisan('queue:check-backlog')->assertExitCode(0);
        $this->artisan('queue:check-backlog')->assertExitCode(0);

        $this->assertSame(1, UserNotification::where('user_id', $ops->id)
            ->where('type', 'queue_backlog_unhealthy')->count(), 'a repeated run must not spam');
    }

    public function test_all_clear_fires_once_backlog_drains_and_rearms(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->seedStaleBacklog(CheckQueueBacklog::backlogThreshold());

        $this->artisan('queue:check-backlog')->assertExitCode(0);

        DB::table('jobs')->delete();

        $this->artisan('queue:check-backlog')->assertExitCode(0);

        $this->assertSame(1, UserNotification::where('user_id', $ops->id)
            ->where('type', 'queue_backlog_recovered')->count(), 'one all-clear after the drain');

        $state = AppSetting::get(CheckQueueBacklog::STATE_KEY, []);
        $this->assertEmpty($state['alerting'] ?? null, 'episode must be closed');

        // Re-armed: a new backlog alerts again.
        $this->seedStaleBacklog(CheckQueueBacklog::backlogThreshold());
        $this->artisan('queue:check-backlog')->assertExitCode(0);

        $this->assertSame(2, UserNotification::where('user_id', $ops->id)
            ->where('type', 'queue_backlog_unhealthy')->count());
    }

    public function test_non_database_queue_driver_is_a_noop(): void
    {
        config(['queue.default' => 'sync']);
        $ops = $this->makeOpsAdmin();
        $this->seedStaleBacklog(CheckQueueBacklog::backlogThreshold());

        $this->artisan('queue:check-backlog')->assertExitCode(0);

        $this->assertSame(0, UserNotification::where('user_id', $ops->id)->count());
    }
}
