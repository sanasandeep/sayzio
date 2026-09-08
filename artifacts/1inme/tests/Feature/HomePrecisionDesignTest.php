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

        // Nothing seeded: the band and the FAQ section drop out rather than
        // rendering invented figures or an empty accordion.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('class="band"', $html);

        $this->get(route('home.sections'))->assertOk();
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
