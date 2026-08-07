<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Controllers\HomeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the marketing homepage design switch (`marketing_home_design`).
 *
 * The admin panel can flip `/` between the classic full-size landing page
 * (home.blade.php + home/deferred-sections.blade.php) and the compact
 * Variant B (home-b.blade.php + home/deferred-sections-b.blade.php) via
 * HomeController::activeDesign(). A regression in that branching would
 * silently serve the wrong page — or worse, a classic shell whose deferred
 * loader injects the Variant-B fragment (mismatched hb-* CSS renders
 * unstyled). This locks: both views AND the matching /home/sections
 * fragment per design, plus the unknown-value → classic fallback.
 *
 * AppSetting::put() forgets the per-key cache, so flipping the setting
 * mid-test takes effect immediately (no 5-min cache window in tests).
 */
class HomeDesignSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_compact_setting_serves_variant_b_page_and_fragment(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'compact');

        $this->assertSame('compact', HomeController::activeDesign());

        $home = $this->get('/');
        $home->assertOk();
        $home->assertViewIs('home-b');
        // Variant-B layout primitive — only the compact hero uses it.
        $home->assertSee('hb-hero-grid', false);

        $sections = $this->get(route('home.sections'));
        $sections->assertOk();
        $sections->assertViewIs('home.deferred-sections-b');
        $sections->assertSee('hb-shell', false);
    }

    public function test_classic_setting_serves_original_page_and_fragment(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'classic');

        $this->assertSame('classic', HomeController::activeDesign());

        $home = $this->get('/');
        $home->assertOk();
        $home->assertViewIs('home');
        $home->assertDontSee('hb-hero-grid', false);

        $sections = $this->get(route('home.sections'));
        $sections->assertOk();
        $sections->assertViewIs('home.deferred-sections');
        $sections->assertDontSee('hb-shell', false);
    }

    public function test_unknown_setting_value_falls_back_to_classic(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'neon-mega-variant');

        $this->assertSame('classic', HomeController::activeDesign());

        $this->get('/')->assertOk()->assertViewIs('home');
        $this->get(route('home.sections'))->assertOk()->assertViewIs('home.deferred-sections');
    }

    public function test_missing_setting_defaults_to_classic(): void
    {
        AppSetting::query()->where('key', HomeController::DESIGN_SETTING_KEY)->delete();
        \Illuminate\Support\Facades\Cache::flush();

        $this->assertSame('classic', HomeController::activeDesign());
        $this->get('/')->assertOk()->assertViewIs('home');
    }
}
