<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Controllers\HomeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the Precision home design.
 *
 * Precision is the first design with a shell view of its own; every other
 * design shares home.blade.php and swaps only the deferred fragment. So the
 * two things worth locking are that picking it swaps BOTH halves, and that
 * not picking it changes nothing at all for the designs that were here first.
 *
 * The render assertions matter more than they look: this page pulls its
 * numbers from Site Stats and its questions from the admin FAQ rows, and both
 * of those are empty in a fresh test database. A page that only renders when
 * those tables happen to be populated would break on a cold install.
 */
class HomePrecisionDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_picking_precision_swaps_both_the_shell_and_the_fragment(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'precision');

        $this->assertSame('precision', HomeController::activeDesign());
        $this->assertSame('home-precision', HomeController::activeShellView());

        $this->get('/')
            ->assertOk()
            ->assertViewIs('home-precision')
            ->assertSee('One address for everything you share.', false);

        $this->get(route('home.sections'))
            ->assertOk()
            ->assertViewIs('home.deferred-sections-precision');
    }

    public function test_the_page_renders_with_no_site_stats_and_no_faqs_saved(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'precision');

        // Empty both sources explicitly. A migration seeds Site Stats, so
        // "fresh database" is not the same thing as "nothing saved", and the
        // case worth guarding is the one where an admin has cleared them.
        \App\Modules\Admin\Models\SiteStat::query()->delete();
        \App\Modules\Common\Models\FaqItem::query()->delete();
        \Illuminate\Support\Facades\Cache::flush();

        // The band and the FAQ section drop out rather than rendering
        // invented figures or an empty accordion.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('<div class="band">', $html);
        $this->assertStringContainsString('One address for everything you share.', $html);

        $this->get(route('home.sections'))
            ->assertOk()
            ->assertDontSee('Before you pick.', false);
    }

    public function test_the_band_renders_the_admin_site_stats_when_there_are_some(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'precision');

        \App\Modules\Admin\Models\SiteStat::query()->delete();
        \App\Modules\Admin\Models\SiteStat::create([
            'label' => 'Creators and businesses',
            'value' => '3.75 Lakh',
            'suffix' => '+',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->get('/')
            ->assertOk()
            ->assertSee('Creators and businesses', false)
            ->assertSee('3.75 Lakh', false);
    }

    public function test_precision_carries_its_own_seo(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'precision');

        $seo = HomeController::activeDesignSeo();
        $this->assertIsArray($seo);
        $this->assertNotSame('', (string) ($seo['title'] ?? ''));
    }

    public function test_the_designs_that_were_here_first_are_untouched(): void
    {
        // Default install.
        $this->assertSame('classic', HomeController::activeDesign());
        $this->assertSame('home', HomeController::activeShellView());
        $this->get('/')->assertOk()->assertViewIs('home');

        // And an explicitly chosen older design still uses the shared shell.
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'ai');
        $this->assertSame('home', HomeController::activeShellView());
    }

    public function test_an_unknown_stored_design_falls_back_to_classic(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'precision-typo');

        $this->assertSame('classic', HomeController::activeDesign());
        $this->assertSame('home', HomeController::activeShellView());
    }
}
