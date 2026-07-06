<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\StatsRetentionPolicy;
use App\Modules\Common\Support\StatsStorageHealth;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the mobile analytics-storage parity endpoints (Task #2356):
 *
 *   GET /api/v1/admin/stats-storage   (read-only health report)
 *   PUT /api/v1/admin/stats-storage   (set/clear hard cap + alert threshold)
 *
 * Both mirror the web admin "Analytics Storage" panel, read the same
 * {@see StatsStorageHealth} / {@see StatsRetentionPolicy}, and are gated behind
 * the same `settings.manage` permission, so a regular sanctum token must be
 * rejected. Set/clear must persist to the `stats.hard_max_days` /
 * `stats.alert_row_threshold` AppSettings exactly like the web controller.
 *
 * No local `1inme_testing` DB exists, so (like the other Feature tests) these
 * run against CI Postgres via RefreshDatabase.
 */
class MobileStatsStorageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StatsStorageHealth::flush();
    }

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    /** A user holding the web-guard `settings.manage` permission. */
    private function makeAdmin(): User
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

    private function asUser(User $user): self
    {
        $this->withToken($user->createToken('mobile-test')->plainTextToken);
        return $this;
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/stats-storage')->assertStatus(401);
        $this->putJson('/api/v1/admin/stats-storage')->assertStatus(401);
    }

    public function test_status_forbidden_for_a_non_admin_token(): void
    {
        $this->asUser($this->makeUser());

        $this->getJson('/api/v1/admin/stats-storage')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_update_forbidden_for_a_non_admin_token(): void
    {
        $this->asUser($this->makeUser());

        $this->putJson('/api/v1/admin/stats-storage', ['hard_max_days' => 90])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        // A blocked caller must not have changed any setting.
        $this->assertNull(AppSetting::get('stats.hard_max_days'));
    }

    public function test_status_returns_the_health_report_for_an_admin(): void
    {
        AppSetting::put('stats.hard_max_days', 120);
        AppSetting::put('stats.alert_row_threshold', 1000);
        StatsStorageHealth::flush();

        $this->asUser($this->makeAdmin());

        $resp = $this->getJson('/api/v1/admin/stats-storage');

        $resp->assertOk();
        $resp->assertJsonPath('data.hard_max_days', 120);
        $resp->assertJsonPath('data.alert_threshold', 1000);
        $resp->assertJsonPath('data.default_threshold', StatsRetentionPolicy::DEFAULT_ALERT_THRESHOLD);
        // `tables` is a flat array of {table,estimated_rows,over_threshold}.
        $this->assertIsArray($resp->json('data.tables'));
        $this->assertIsString($resp->json('data.reason'));
    }

    public function test_update_sets_the_hard_cap_and_alert_threshold(): void
    {
        $this->asUser($this->makeAdmin());

        $resp = $this->putJson('/api/v1/admin/stats-storage', [
            'hard_max_days'       => 60,
            'alert_row_threshold' => 250000,
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('data.hard_max_days', 60);
        $resp->assertJsonPath('data.alert_threshold', 250000);

        $this->assertSame(60, (int) AppSetting::get('stats.hard_max_days'));
        $this->assertSame(250000, (int) AppSetting::get('stats.alert_row_threshold'));
    }

    public function test_update_clears_settings_when_clear_flags_are_set(): void
    {
        AppSetting::put('stats.hard_max_days', 90);
        AppSetting::put('stats.alert_row_threshold', 7000);
        StatsStorageHealth::flush();

        $this->asUser($this->makeAdmin());

        $resp = $this->putJson('/api/v1/admin/stats-storage', [
            'clear_hard_max_days'       => true,
            'clear_alert_row_threshold' => true,
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('data.hard_max_days', null);
        // Cleared threshold falls back to the built-in default.
        $resp->assertJsonPath('data.alert_threshold', StatsRetentionPolicy::DEFAULT_ALERT_THRESHOLD);

        $this->assertNull(AppSetting::get('stats.hard_max_days'));
        $this->assertNull(AppSetting::get('stats.alert_row_threshold'));
    }

    public function test_clear_flag_wins_over_a_provided_value(): void
    {
        AppSetting::put('stats.hard_max_days', 30);
        StatsStorageHealth::flush();

        $this->asUser($this->makeAdmin());

        $this->putJson('/api/v1/admin/stats-storage', [
            'hard_max_days'       => 365,
            'clear_hard_max_days' => true,
        ])->assertOk()->assertJsonPath('data.hard_max_days', null);

        $this->assertNull(AppSetting::get('stats.hard_max_days'));
    }

    public function test_update_validates_the_hard_cap_range(): void
    {
        $this->asUser($this->makeAdmin());

        $this->putJson('/api/v1/admin/stats-storage', ['hard_max_days' => 0])
            ->assertStatus(422);

        $this->assertNull(AppSetting::get('stats.hard_max_days'));
    }
}
