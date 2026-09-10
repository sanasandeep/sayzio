<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\MarketingPageCache;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The reliability signals in the landing trust band.
 *
 * `marketing_trust_strip` has existed in Marketing Settings for a while, with
 * a repeatable editor and a live preview, and NOTHING on the site read it.
 * Meanwhile the row it describes -- icon, bold value, trailing label, under
 * the band's big figures -- was four hardcoded entries in the partial.
 *
 * Same failure the hero marquee had, and the same guard: post the admin form,
 * then assert on the rendered public page.
 */
class TrustStripEditorTest extends TestCase
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

    public function test_band_renders_the_shipped_signals_when_nothing_is_saved(): void
    {
        $this->get('/home/sections')
            ->assertOk()
            ->assertSee('multi-region edge')
            ->assertSee('EU/UK SCCs in place');
    }

    /**
     * The defaults must be what the page actually ships, or "reset to
     * defaults" hands an admin four things that have never been on the site.
     * They used to be vanity metrics -- active creators, average rating --
     * which is the job of the Site stats screen one row above.
     */
    public function test_the_shipped_defaults_are_what_the_band_renders(): void
    {
        $html = $this->get('/home/sections')->assertOk()->getContent();

        foreach (SitePagesContent::trustStripDefault() as $row) {
            $this->assertStringContainsString($row['value'], $html);
            $this->assertStringContainsString($row['label'], $html);
        }
    }

    public function test_admin_save_reaches_the_rendered_band(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->put('/admin/marketing-settings', [
                'home_design'  => 'classic',
                'trust_strip'  => [
                    ['value' => 'SOC 2 Type II', 'label' => 'audited annually', 'icon' => 'fa-certificate'],
                    ['value' => '24/7 support',  'label' => 'real people',      'icon' => 'fa-headset'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->get('/home/sections')
            ->assertOk()
            ->assertSee('SOC 2 Type II')
            ->assertSee('audited annually')
            ->assertSee('fa-certificate', false)
            // The shipped four must be replaced, not joined.
            ->assertDontSee('multi-region edge');
    }

    /**
     * A heading with no signals under it is worse than the four it started
     * with, so clearing every row restores the shipped set.
     */
    public function test_clearing_every_row_restores_the_shipped_signals(): void
    {
        AppSetting::put('marketing_trust_strip', [
            ['value' => 'Temporary', 'label' => 'row', 'icon' => 'fa-star'],
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->put('/admin/marketing-settings', ['home_design' => 'classic', 'trust_strip' => []])
            ->assertSessionHasNoErrors();

        $this->get('/home/sections')
            ->assertOk()
            ->assertSee('multi-region edge')
            ->assertDontSee('Temporary');
    }

    /**
     * Read on every marketing page that carries the band, so it has to be one
     * of the keys the warmer primes -- an unlisted key costs a cross-region
     * query on the first request after a deploy.
     */
    public function test_the_key_is_warmed_with_its_neighbours(): void
    {
        $this->assertContains('marketing_trust_strip', MarketingPageCache::LAYOUT_SETTING_KEYS);
        $this->assertContains('marketing_hero_marquee', MarketingPageCache::LAYOUT_SETTING_KEYS);
    }

    /**
     * The figures above these signals come from the Site stats screen and a
     * different model. Keeping the two apart is the reason this setting drives
     * the signal row rather than the figure row.
     */
    public function test_the_setting_does_not_touch_the_figures(): void
    {
        \App\Modules\Admin\Models\SiteStat::create([
            'label' => 'Creators on board', 'value' => '375,000', 'suffix' => '+',
            'is_active' => true, 'sort_order' => 1,
        ]);
        // SiteStat has no flushCache() helper of its own, unlike Testimonial
        // and ZioLine -- it caches under a public key constant instead.
        \Illuminate\Support\Facades\Cache::forget(\App\Modules\Admin\Models\SiteStat::ACTIVE_CACHE_KEY);

        AppSetting::put('marketing_trust_strip', [
            ['value' => 'Signal row only', 'label' => 'not a figure', 'icon' => 'fa-star'],
        ]);

        $this->get('/home/sections')
            ->assertOk()
            ->assertSee('Creators on board')
            ->assertSee('Signal row only');
    }
}
