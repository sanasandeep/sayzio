<?php

namespace Tests\Feature;

use App\Console\Commands\CheckStaleAssetImports;
use App\Modules\Admin\Models\AdminAssetImport;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the stale zip-import sweep (`assets:check-stale-imports` /
 * CheckStaleAssetImports) — the safety net for Asset Vault imports whose
 * worker died mid-run.
 *
 * Pins: stale active imports are auto-failed with the worker-lost error,
 * ops admins get exactly one in-app alert naming the import source and
 * reason, already-failed rows never re-alert on subsequent sweeps, fresh
 * active imports and terminal rows are untouched, and the sweep is
 * registered in the schedule registry.
 */
class StaleAssetImportAlertTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeImport(string $status, int $minutesStale, array $extra = []): AdminAssetImport
    {
        $import = AdminAssetImport::create(array_merge([
            'status'      => $status,
            'source_type' => 'upload',
            'source'      => 'template-pack.zip',
            'mode'        => 'skip',
            'started_at'  => now()->subMinutes($minutesStale + 5),
        ], $extra));

        // Bypass Eloquent timestamp touching to backdate the heartbeat.
        DB::table('admin_asset_imports')->where('id', $import->id)
            ->update(['updated_at' => now()->subMinutes($minutesStale)]);

        return $import->fresh();
    }

    // ─────────────────────────────────────────────────────────────

    public function test_sweep_is_registered_in_the_schedule_registry(): void
    {
        $def = \App\Modules\Admin\Support\ScheduledJobRegistry::find('assets:check-stale-imports');

        $this->assertNotNull($def, 'sweep must be a registry-driven scheduled job');
        $this->assertSame('health-checks', $def['group']);
    }

    public function test_stale_active_import_is_auto_failed_and_alerts_ops_admins(): void
    {
        $ops    = $this->makeOpsAdmin();
        $import = $this->makeImport('processing', CheckStaleAssetImports::staleMinutes() + 10, [
            'processed_entries' => 40,
            'total_entries'     => 200,
        ]);

        $this->artisan('assets:check-stale-imports')->assertExitCode(0);

        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertSame(CheckStaleAssetImports::WORKER_LOST_ERROR, $import->error);
        $this->assertNotNull($import->completed_at);

        $notes = UserNotification::where('user_id', $ops->id)
            ->where('type', 'asset_import_worker_lost')->get();
        $this->assertCount(1, $notes, 'exactly one in-app alert for the ops admin');
        $this->assertStringContainsString('worker lost', $notes->first()->data['subject']);
        $this->assertStringContainsString('template-pack.zip', $notes->first()->data['body']);
        $this->assertStringContainsString('40/200 entries', $notes->first()->data['body']);
        $this->assertStringContainsString('worker lost', strtolower($notes->first()->data['body']));
        $this->assertContains($import->id, $notes->first()->data['import_ids']);
    }

    public function test_already_failed_rows_never_realert(): void
    {
        $ops = $this->makeOpsAdmin();
        $this->makeImport('processing', CheckStaleAssetImports::staleMinutes() + 10);

        $this->artisan('assets:check-stale-imports')->assertExitCode(0);
        $this->artisan('assets:check-stale-imports')->assertExitCode(0);

        $this->assertSame(1, UserNotification::where('user_id', $ops->id)
            ->where('type', 'asset_import_worker_lost')->count(), 'a repeated sweep must not spam');
    }

    public function test_fresh_active_and_terminal_imports_are_untouched(): void
    {
        $ops = $this->makeOpsAdmin();

        $fresh     = $this->makeImport('processing', 5);
        $pending   = $this->makeImport('pending', 5);
        $completed = $this->makeImport('completed', CheckStaleAssetImports::staleMinutes() + 60);
        $failed    = $this->makeImport('failed', CheckStaleAssetImports::staleMinutes() + 60, [
            'error' => 'The file is not a readable zip archive (code 19).',
        ]);

        $this->artisan('assets:check-stale-imports')->assertExitCode(0);

        $this->assertSame('processing', $fresh->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('completed', $completed->fresh()->status);
        $this->assertSame('The file is not a readable zip archive (code 19).', $failed->fresh()->error, 'a real failure reason must never be overwritten');
        $this->assertSame(0, UserNotification::where('user_id', $ops->id)->count());
    }

    public function test_multiple_stale_imports_fan_out_one_combined_alert(): void
    {
        $ops = $this->makeOpsAdmin();
        $a   = $this->makeImport('downloading', CheckStaleAssetImports::staleMinutes() + 10, [
            'source_type' => 'url',
            'source'      => 'https://example.com/pack-a.zip',
        ]);
        $b = $this->makeImport('processing', CheckStaleAssetImports::staleMinutes() + 20, [
            'source' => 'pack-b.zip',
        ]);

        $this->artisan('assets:check-stale-imports')->assertExitCode(0);

        $this->assertSame('failed', $a->fresh()->status);
        $this->assertSame('failed', $b->fresh()->status);

        $notes = UserNotification::where('user_id', $ops->id)
            ->where('type', 'asset_import_worker_lost')->get();
        $this->assertCount(1, $notes, 'one combined alert, not one per import');
        $this->assertStringContainsString('pack-a.zip', $notes->first()->data['body']);
        $this->assertStringContainsString('pack-b.zip', $notes->first()->data['body']);
        $this->assertSame(2, $notes->first()->data['count']);
    }
}
