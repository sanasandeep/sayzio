<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\StatsStorageHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the web admin "Analytics Storage" panel (Task #2356):
 *
 *   GET /admin/stats-storage   (read the health report)
 *   PUT /admin/stats-storage   (set/clear hard cap + alert threshold)
 *
 * The page surfaces the effective retention window, hard cap, per-table
 * estimated row counts and the last sweep outcome, and lets an operator set or
 * clear `stats.hard_max_days` / `stats.alert_row_threshold`. Both routes are
 * gated behind `settings.manage`.
 */
class StatsStorageAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StatsStorageHealth::flush();
    }

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

    public function test_index_requires_authentication(): void
    {
        $this->get('/admin/stats-storage')->assertRedirect();
    }

    public function test_index_renders_for_an_admin(): void
    {
        AppSetting::put('stats.hard_max_days', 90);
        StatsStorageHealth::flush();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/stats-storage')
            ->assertOk()
            ->assertSee('Analytics Storage')
            ->assertSee('Storage limits');
    }

    public function test_update_sets_the_hard_cap_and_alert_threshold(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->put('/admin/stats-storage', [
                'hard_max_days'       => 120,
                'alert_row_threshold' => 1000000,
            ])
            ->assertRedirect(route('admin.stats-storage.index'));

        $this->assertSame(120, (int) AppSetting::get('stats.hard_max_days'));
        $this->assertSame(1000000, (int) AppSetting::get('stats.alert_row_threshold'));
    }

    public function test_update_clears_settings_via_clear_checkboxes(): void
    {
        AppSetting::put('stats.hard_max_days', 30);
        AppSetting::put('stats.alert_row_threshold', 5000);
        StatsStorageHealth::flush();

        $this->actingAs($this->makeAdmin(), 'admin')
            ->put('/admin/stats-storage', [
                'clear_hard_max_days'       => '1',
                'clear_alert_row_threshold' => '1',
            ])
            ->assertRedirect();

        $this->assertNull(AppSetting::get('stats.hard_max_days'));
        $this->assertNull(AppSetting::get('stats.alert_row_threshold'));
    }

    public function test_update_validates_the_hard_cap(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->put('/admin/stats-storage', ['hard_max_days' => -5])
            ->assertSessionHasErrors('hard_max_days');

        $this->assertNull(AppSetting::get('stats.hard_max_days'));
    }
}
