<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Controllers\HomeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Task #6734 — locks the admin Marketing Settings round-trip for the
 * homepage design toggle (`marketing_home_design`).
 *
 * The settings page (MarketingSettingsController::index) reflects the active
 * design via HomeController::activeDesign(); update() validates
 * 'home_design' in:classic,compact. A stale/broken round-trip would show the
 * wrong radio as active or silently default an invalid value. This asserts:
 * saving 'compact' persists the AppSetting AND the index page re-renders
 * with the compact radio checked (same for 'classic'), and an invalid value
 * fails validation without touching the stored setting.
 */
class AdminHomeDesignToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
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

    /** Extract the home_design radio input tag for the given value from the page HTML. */
    private function radioTag(string $html, string $value): string
    {
        $ok = preg_match(
            '/<input[^>]*name="home_design"[^>]*value="' . $value . '"[^>]*>/s',
            $html,
            $m
        );
        $this->assertSame(1, $ok, "home_design radio for '{$value}' not found on the settings page");

        return $m[0];
    }

    public function test_saving_compact_persists_and_shows_compact_as_active(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.marketing-settings.update'), [
                'home_design' => 'compact',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'compact',
            (string) AppSetting::get(HomeController::DESIGN_SETTING_KEY)
        );
        $this->assertSame('compact', HomeController::activeDesign());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.marketing-settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('checked', $this->radioTag($html, 'compact'));
        $this->assertStringNotContainsString('checked', $this->radioTag($html, 'classic'));
    }

    public function test_saving_classic_persists_and_shows_classic_as_active(): void
    {
        $admin = $this->admin();

        // Start from compact so the flip back is a real state change.
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'compact');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.marketing-settings.update'), [
                'home_design' => 'classic',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'classic',
            (string) AppSetting::get(HomeController::DESIGN_SETTING_KEY)
        );
        $this->assertSame('classic', HomeController::activeDesign());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.marketing-settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('checked', $this->radioTag($html, 'classic'));
        $this->assertStringNotContainsString('checked', $this->radioTag($html, 'compact'));
    }

    public function test_invalid_design_value_fails_validation_and_leaves_setting_untouched(): void
    {
        $admin = $this->admin();

        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'compact');

        // Web form path: redirect back with a validation error.
        $this->actingAs($admin, 'admin')
            ->from(route('admin.marketing-settings.index'))
            ->put(route('admin.marketing-settings.update'), [
                'home_design' => 'neon-mega-variant',
            ])
            ->assertRedirect(route('admin.marketing-settings.index'))
            ->assertSessionHasErrors('home_design');

        // JSON path: explicit 422.
        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.marketing-settings.update'), [
                'home_design' => 'neon-mega-variant',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('home_design');

        // Missing entirely is also rejected (field is required).
        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.marketing-settings.update'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('home_design');

        // The stored setting never changed.
        $this->assertSame(
            'compact',
            (string) AppSetting::get(HomeController::DESIGN_SETTING_KEY)
        );
        $this->assertSame('compact', HomeController::activeDesign());
    }
}
