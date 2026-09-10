<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The capability marquee at the bottom of the landing hero.
 *
 * It was a hardcoded array in `home/partials/hero.blade.php`, so renaming a
 * feature in the first thing a visitor reads after the headline meant
 * shipping a release. It is now edited under Marketing Settings.
 *
 * The round trip is what matters here -- an admin screen that saves to a
 * setting nothing reads is the failure mode worth guarding against -- so the
 * middle test posts the form and then asserts on the rendered homepage.
 */
class HeroMarqueeEditorTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_home_renders_the_shipped_marquee_when_nothing_is_saved(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Live geo heatmap')
            ->assertSee('Built-in dialer');
    }

    public function test_admin_save_reaches_the_rendered_hero(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->put('/admin/marketing-settings', [
                'home_design'  => 'classic',
                'hero_marquee' => [
                    ['icon' => 'fa-rocket',  'label' => 'Launch checklist'],
                    ['icon' => 'fa-inbox',   'label' => 'Unified inbox'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            [
                ['icon' => 'fa-rocket', 'label' => 'Launch checklist'],
                ['icon' => 'fa-inbox',  'label' => 'Unified inbox'],
            ],
            AppSetting::get('marketing_hero_marquee')
        );

        $this->get('/')
            ->assertOk()
            ->assertSee('Launch checklist')
            ->assertSee('Unified inbox')
            // The shipped list must be gone, not merged with the saved one.
            // "Live geo heatmap" is the phrase to check: "Dynamic QR codes"
            // also appears in the hero's own copy, so its presence would say
            // nothing about the marquee.
            ->assertDontSee('Live geo heatmap');
    }

    /**
     * There is no meaningful "no marquee" state: the band's height is one
     * lattice cell of the hero's grid, so an empty one would leave a ruled
     * gap where the wording should be. Clearing every row restores the
     * shipped list rather than emptying the band.
     */
    public function test_clearing_every_row_falls_back_to_the_shipped_list(): void
    {
        AppSetting::put('marketing_hero_marquee', [
            ['icon' => 'fa-rocket', 'label' => 'Launch checklist'],
        ]);

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')
            ->put('/admin/marketing-settings', ['home_design' => 'classic', 'hero_marquee' => []])
            ->assertSessionHasNoErrors();

        $this->get('/')
            ->assertOk()
            ->assertSee('Live geo heatmap')
            ->assertDontSee('Launch checklist');
    }

    public function test_normalizer_drops_unlabelled_rows_defaults_the_icon_and_caps_the_list(): void
    {
        $out = SitePagesContent::normalizeHeroMarquee([
            ['icon' => 'fa-star', 'label' => '  Kept  '],
            ['icon' => 'fa-star', 'label' => ''],          // no label: dropped
            ['label' => 'No icon given'],                   // icon defaulted
            'not an array',                                 // ignored
        ]);

        $this->assertSame([
            ['icon' => 'fa-star',        'label' => 'Kept'],
            ['icon' => 'fa-circle-check', 'label' => 'No icon given'],
        ], $out);

        $long = array_fill(0, 30, ['icon' => 'fa-star', 'label' => 'x']);
        $this->assertCount(18, SitePagesContent::normalizeHeroMarquee($long));
    }

    public function test_marketing_settings_screen_lists_the_marquee_rows(): void
    {
        AppSetting::put('marketing_hero_marquee', [
            ['icon' => 'fa-rocket', 'label' => 'Launch checklist'],
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/marketing-settings')
            ->assertOk()
            ->assertSee('Hero capability marquee')
            ->assertSee('Launch checklist');
    }
}
