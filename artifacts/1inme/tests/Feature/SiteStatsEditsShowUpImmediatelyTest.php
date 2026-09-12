<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\SiteStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin -> Marketing Stats: an edit is on the public site on the next page
 * load, not in five minutes.
 *
 * The figures are read through a five-minute cache. Zio's lines and the
 * testimonials -- the two screens built to the same shape -- have always
 * dropped that cache on every write. This screen never did, so relabelling a
 * stat left the old label on the homepage for minutes: long enough for the
 * admin to reload, see no change, and conclude the save had failed.
 *
 * A test that only asserts the row changed cannot catch this, because the
 * row DID change. So each case here reads the public page after the save.
 */
class SiteStatsEditsShowUpImmediatelyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'staff-settings-manage'],
            ['name' => 'Staff (settings.manage)', 'guard' => 'admin']
        );
        $perm = Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'settings.manage', 'group' => 'settings']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        return Admin::create([
            'name'     => 'Admin '.Str::random(4),
            'email'    => 'a'.Str::random(8).'@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function stat(array $overrides = []): SiteStat
    {
        return SiteStat::create(array_merge([
            'label'      => 'Users Worldwide',
            'value'      => '3,75,000',
            'suffix'     => '+',
            'icon'       => 'fa-users',
            'color'      => '#3d6bff',
            'is_active'  => true,
            'sort_order' => 1,
        ], $overrides));
    }

    /** Warm the cache the way a visitor would, before the admin edits. */
    private function primeThePublicCache(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_a_relabelled_stat_is_on_the_home_page_on_the_next_load(): void
    {
        $stat = $this->stat();
        $this->primeThePublicCache();

        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/site-stats/'.$stat->id, [
                'label'      => 'Creators & Businesses Served',
                'value'      => '3,75,000',
                'suffix'     => '+',
                'is_active'  => 1,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('Creators &amp; Businesses Served', false);
    }

    public function test_a_deleted_stat_leaves_the_home_page_immediately(): void
    {
        $keep = $this->stat();
        $drop = $this->stat(['label' => 'QR codes generated', 'value' => '35,000', 'sort_order' => 2]);
        $this->primeThePublicCache();

        $this->actingAs($this->admin(), 'admin')
            ->delete('/admin/site-stats/'.$drop->id)
            ->assertRedirect();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('QR codes generated', $html);
        $this->assertStringContainsString($keep->label, $html);
    }

    public function test_hiding_a_stat_takes_it_off_the_page_immediately(): void
    {
        $this->stat();
        $hide = $this->stat(['label' => 'Countries Reached', 'value' => '67', 'sort_order' => 2]);
        $this->primeThePublicCache();

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/site-stats/'.$hide->id.'/toggle')
            ->assertRedirect();

        $this->assertStringNotContainsString(
            'Countries Reached',
            $this->get('/')->assertOk()->getContent()
        );
    }

    /**
     * The manual button: for a change that did not come through one of these
     * screens (edited straight in the database, say) there is no save to hang
     * the flush on, so there is a button that clears and rebuilds.
     */
    public function test_the_rebuild_button_picks_up_a_change_made_outside_the_admin(): void
    {
        $stat = $this->stat();
        $this->primeThePublicCache();

        // Straight to the table, deliberately bypassing the controller.
        SiteStat::withoutEvents(fn () => SiteStat::query()
            ->where('id', $stat->id)
            ->update(['label' => 'Changed Behind The Cache'])
        );

        $this->assertStringNotContainsString(
            'Changed Behind The Cache',
            $this->get('/')->getContent(),
            'the cache is not actually holding the old copy, so this test proves nothing'
        );

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/marketing-cache/refresh')
            ->assertRedirect();

        $this->assertStringContainsString(
            'Changed Behind The Cache',
            $this->get('/')->assertOk()->getContent(),
            'the rebuild button did not clear the cached figures'
        );
    }

    /** The button is actually on the screen, and the route it posts to exists. */
    public function test_the_stats_screen_offers_the_rebuild_button(): void
    {
        $this->stat();

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/site-stats')
            ->assertOk()
            ->assertSee('Rebuild public cache')
            ->assertSee(route('admin.marketing-cache.refresh'), false);
    }

    public function test_the_rebuild_button_needs_the_settings_permission(): void
    {
        $nobody = Admin::create([
            'name'     => 'No Rights',
            'email'    => 'n'.Str::random(8).'@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => Role::firstOrCreate(
                ['slug' => 'staff-nothing'],
                ['name' => 'Staff (no permissions)', 'guard' => 'admin']
            )->id,
            'status'   => 'active',
        ]);

        $this->actingAs($nobody, 'admin')
            ->post('/admin/marketing-cache/refresh')
            ->assertForbidden();
    }
}
