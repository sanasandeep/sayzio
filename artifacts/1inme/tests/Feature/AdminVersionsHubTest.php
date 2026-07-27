<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Release;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Support\VersionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the admin "Versions & Releases" hub:
 * index rendering (with and without the committed version snapshot),
 * release CRUD validation + duplicate-version rejection, guards:record
 * persistence, and VersionRegistry's graceful "unknown" degradation.
 */
class AdminVersionsHubTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        return Admin::create([
            'name'     => 'Versions Admin',
            'email'    => 'versions-admin-' . uniqid() . '@example.com',
            'password' => 'secret-password',
            'status'   => 'active',
            'role_id'  => $role->id,
        ]);
    }

    /**
     * Run $callback while the committed version-snapshot.json is temporarily
     * moved aside, always restoring it afterwards.
     */
    private function withoutSnapshot(callable $callback): void
    {
        $path   = base_path('version-snapshot.json');
        $backup = $path . '.test-bak';
        $moved  = false;

        if (is_file($path)) {
            rename($path, $backup);
            $moved = true;
        }

        try {
            $callback();
        } finally {
            if ($moved) {
                rename($backup, $path);
            }
        }
    }

    // ── Index rendering ────────────────────────────────────────────────

    public function test_index_renders_for_permitted_admin_with_snapshot(): void
    {
        $this->assertFileExists(base_path('version-snapshot.json'));

        $resp = $this->actingAs($this->admin(), 'admin')->get(route('admin.versions.index'));

        $resp->assertOk();
        foreach (Release::SURFACES as $label) {
            $resp->assertSee($label, false);
        }
        // Sync Status panel lists every guard label.
        foreach (VersionRegistry::GUARDS as $label) {
            $resp->assertSee($label, false);
        }
    }

    public function test_index_renders_without_snapshot_and_degrades_to_unknown(): void
    {
        $this->withoutSnapshot(function () {
            $this->assertNull(VersionRegistry::snapshot());

            $resp = $this->actingAs($this->admin(), 'admin')->get(route('admin.versions.index'));
            $resp->assertOk();

            // Snapshot-declared surfaces degrade gracefully instead of 500ing.
            $rows = collect(VersionRegistry::surfaces())->keyBy('key');
            $this->assertSame('unknown', $rows['mobile']['status']);
            $this->assertNull($rows['mobile']['current']);
            $this->assertSame(
                'Declared version not found in the committed snapshot.',
                $rows['mobile']['detail']
            );
            // Docs surface points at the snapshot generator when missing.
            $this->assertSame('unknown', $rows['docs']['status']);
        });
    }

    public function test_guest_cannot_view_versions_hub(): void
    {
        $this->get(route('admin.versions.index'))->assertRedirect();
    }

    // ── Guard status parsing ───────────────────────────────────────────

    public function test_index_parses_guard_statuses_and_ignores_garbage(): void
    {
        AppSetting::put(VersionRegistry::GUARD_STATUS_KEY, [
            'dialer_sync'   => ['status' => 'pass', 'ran_at' => now()->toIso8601String()],
            'docs_parity'   => ['status' => 'fail', 'ran_at' => now()->toIso8601String(), 'note' => '3 endpoints undocumented'],
            'doc_constants' => ['status' => 'bogus-value'],
            'unknown_guard' => ['status' => 'pass'],
        ]);

        $resp = $this->actingAs($this->admin(), 'admin')->get(route('admin.versions.index'));

        $resp->assertOk();
        $resp->assertSee('3 endpoints undocumented', false);
        $guards = collect($resp->viewData('guards'))->keyBy('key');
        $this->assertSame('pass', $guards['dialer_sync']['status']);
        $this->assertSame('fail', $guards['docs_parity']['status']);
        // Unrecognised status strings normalise to null (never-ran display).
        $this->assertNull($guards['doc_constants']['status']);
        // Only the canonical guard list is surfaced.
        $this->assertEqualsCanonicalizing(array_keys(VersionRegistry::GUARDS), $guards->keys()->all());
    }

    // ── Release CRUD ───────────────────────────────────────────────────

    public function test_store_creates_manual_release(): void
    {
        $resp = $this->actingAs($this->admin(), 'admin')->post(route('admin.versions.releases.store'), [
            'surface'     => 'mobile',
            'version'     => '9.9.9',
            'released_at' => '2026-07-01',
            'notes'       => 'Big release.',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('success');
        $this->assertDatabaseHas('releases', [
            'surface' => 'mobile',
            'version' => '9.9.9',
            'source'  => 'manual',
        ]);
    }

    public function test_store_rejects_duplicate_surface_version(): void
    {
        Release::create(['surface' => 'mobile', 'version' => '9.9.9', 'source' => 'manual']);

        $resp = $this->actingAs($this->admin(), 'admin')->post(route('admin.versions.releases.store'), [
            'surface' => 'mobile',
            'version' => '9.9.9',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('error');
        $this->assertSame(1, Release::where('surface', 'mobile')->where('version', '9.9.9')->count());
    }

    public function test_store_validates_surface_version_and_date(): void
    {
        $admin = $this->admin();

        $resp = $this->actingAs($admin, 'admin')->post(route('admin.versions.releases.store'), [
            'surface'     => 'not-a-surface',
            'version'     => '',
            'released_at' => 'not-a-date',
        ]);

        $resp->assertSessionHasErrors(['surface', 'version', 'released_at']);
        $this->assertDatabaseCount('releases', 0);
    }

    public function test_update_edits_release_and_validates(): void
    {
        $release = Release::create(['surface' => 'mobile', 'version' => '1.2.3', 'source' => 'manual']);
        $admin   = $this->admin();

        $this->actingAs($admin, 'admin')->put(route('admin.versions.releases.update', $release), [
            'surface'     => 'mobile',
            'version'     => '1.2.4',
            'released_at' => '2026-07-15',
            'notes'       => 'Patched.',
        ])->assertRedirect()->assertSessionHas('success');

        $release->refresh();
        $this->assertSame('1.2.4', $release->version);
        $this->assertSame('Patched.', $release->notes);
        $this->assertSame('2026-07-15', $release->released_at?->toDateString());

        // Invalid surface on update is rejected.
        $this->actingAs($admin, 'admin')->put(route('admin.versions.releases.update', $release), [
            'surface' => 'nope',
            'version' => '1.2.5',
        ])->assertSessionHasErrors(['surface']);
        $this->assertSame('1.2.4', $release->fresh()->version);
    }

    public function test_destroy_deletes_release(): void
    {
        $release = Release::create(['surface' => 'extension', 'version' => '0.2.0', 'source' => 'manual']);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.versions.releases.destroy', $release))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('releases', ['id' => $release->id]);
    }

    // ── guards:record command ──────────────────────────────────────────

    public function test_guards_record_writes_app_settings(): void
    {
        $this->artisan('guards:record', ['guard' => 'dialer_sync', 'status' => 'pass'])
            ->assertExitCode(0);

        $this->artisan('guards:record', [
            'guard'  => 'docs_parity',
            'status' => 'FAIL',
            '--note' => '3 endpoints undocumented',
        ])->assertExitCode(0);

        $state = AppSetting::get(VersionRegistry::GUARD_STATUS_KEY, []);
        $this->assertSame('pass', $state['dialer_sync']['status']);
        $this->assertNotEmpty($state['dialer_sync']['ran_at']);
        $this->assertNull($state['dialer_sync']['note']);
        $this->assertSame('fail', $state['docs_parity']['status']);
        $this->assertSame('3 endpoints undocumented', $state['docs_parity']['note']);
    }

    public function test_guards_record_rejects_invalid_status_and_preserves_state(): void
    {
        AppSetting::put(VersionRegistry::GUARD_STATUS_KEY, [
            'dialer_sync' => ['status' => 'pass', 'ran_at' => now()->toIso8601String(), 'note' => null],
        ]);

        $this->artisan('guards:record', ['guard' => 'dialer_sync', 'status' => 'maybe'])
            ->assertExitCode(1);

        $state = AppSetting::get(VersionRegistry::GUARD_STATUS_KEY, []);
        $this->assertSame('pass', $state['dialer_sync']['status']);
    }

    // ── VersionRegistry unknown degradation ────────────────────────────

    public function test_registry_zio_browser_row_unknown_without_declared_version(): void
    {
        $this->withoutSnapshot(function () {
            $rows = collect(VersionRegistry::surfaces())->keyBy('key');
            $row  = $rows['zio_browser'];

            $this->assertSame('unknown', $row['status']);
            $this->assertNull($row['current']);
            $this->assertSame(
                'Declared version not found in the committed snapshot.',
                $row['detail']
            );
        });
    }

    public function test_registry_declared_surface_up_to_date_without_changelog_entries(): void
    {
        Release::query()->delete();

        $rows = collect(VersionRegistry::surfaces())->keyBy('key');
        $row  = $rows['mobile'];

        $this->assertNotNull($row['current']);
        $this->assertSame('up_to_date', $row['status']);
        $this->assertSame('No changelog entries recorded yet.', $row['detail']);
    }
}
