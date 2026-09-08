<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\HomeShots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Locks the admin round-trip for the home page product-shot slots.
 *
 * Each slot holds an image URL that the marketing home page shows in place of
 * its drawn mock UI. The default is blank, and blank has to keep meaning "draw
 * the mock", because that is what stops a deploy putting a real customer
 * account on the marketing site. This asserts: a saved URL persists and comes
 * back on the settings page, clearing a field returns the slot to its drawing,
 * and a value that is not a safe image URL is rejected without overwriting
 * what is already stored.
 */
class HomeShotSlotsTest extends TestCase
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
            'status'   => 'active',
            'role_id'  => $role->id,
        ]);
    }

    /** The settings form needs home_design on every submit. */
    private function payload(array $extra = []): array
    {
        return array_merge(['home_design' => 'classic'], $extra);
    }

    public function test_every_slot_is_empty_by_default_so_the_page_draws_its_own(): void
    {
        foreach (array_keys(HomeShots::KEYS) as $slot) {
            $this->assertNull(HomeShots::url($slot), "slot {$slot} should start empty");
            $this->assertFalse(HomeShots::has($slot));
        }
    }

    public function test_saving_urls_persists_them_and_the_settings_page_shows_them_back(): void
    {
        $admin = $this->admin();
        $url = 'https://sayzio.app/storage/admin-assets/demo-dashboard.png';

        $this->actingAs($admin, 'admin')
            ->put(route('admin.marketing-settings.update'), $this->payload([
                'home_shot_dashboard' => $url,
                'home_shot_links'     => '/storage/admin-assets/demo-links.png',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($url, (string) AppSetting::get(HomeShots::KEYS['dashboard']));
        $this->assertSame($url, HomeShots::url('dashboard'));
        $this->assertSame('/storage/admin-assets/demo-links.png', HomeShots::url('links'));
        // Untouched slots stay empty and keep drawing themselves.
        $this->assertNull(HomeShots::url('menu'));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.marketing-settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($url, $html);
    }

    public function test_clearing_a_slot_returns_it_to_the_drawn_fallback(): void
    {
        $admin = $this->admin();
        AppSetting::put(HomeShots::KEYS['dashboard'], 'https://sayzio.app/shot.png');
        $this->assertTrue(HomeShots::has('dashboard'));

        $this->actingAs($admin, 'admin')
            ->put(route('admin.marketing-settings.update'), $this->payload([
                'home_shot_dashboard' => '',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse(HomeShots::has('dashboard'));
        $this->assertNull(HomeShots::url('dashboard'));
    }

    public function test_an_unsafe_value_is_rejected_and_leaves_the_stored_url_alone(): void
    {
        $admin = $this->admin();
        $kept = 'https://sayzio.app/storage/admin-assets/demo-dashboard.png';
        AppSetting::put(HomeShots::KEYS['dashboard'], $kept);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.marketing-settings.update'), $this->payload([
                'home_shot_dashboard' => 'javascript:alert(1)',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('home_shot_dashboard');

        $this->assertSame($kept, (string) AppSetting::get(HomeShots::KEYS['dashboard']));
    }

    public function test_sanitise_only_admits_http_urls_and_site_root_paths(): void
    {
        $this->assertSame('https://a.test/x.png', HomeShots::sanitise('  https://a.test/x.png '));
        $this->assertSame('http://a.test/x.png', HomeShots::sanitise('http://a.test/x.png'));
        $this->assertSame('/storage/x.png', HomeShots::sanitise('/storage/x.png'));

        $this->assertNull(HomeShots::sanitise(''));
        $this->assertNull(HomeShots::sanitise('   '));
        $this->assertNull(HomeShots::sanitise('javascript:alert(1)'));
        $this->assertNull(HomeShots::sanitise('data:image/png;base64,AAAA'));
        $this->assertNull(HomeShots::sanitise('//evil.test/x.png'));
        $this->assertNull(HomeShots::sanitise('not a url'));
    }

    public function test_an_unknown_slot_name_resolves_to_nothing(): void
    {
        $this->assertNull(HomeShots::url('nope'));
        $this->assertFalse(HomeShots::has('nope'));
    }
}
