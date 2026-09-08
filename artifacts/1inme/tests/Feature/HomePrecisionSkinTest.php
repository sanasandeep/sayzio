<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Controllers\HomeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the Precision skin.
 *
 * The skin restyles the home page that already exists. It is not a new page,
 * and the whole value of that is reversibility: picking it must change only
 * the look, and unpicking it must restore today's page exactly. So the tests
 * that matter are about what does NOT change.
 */
class HomePrecisionSkinTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_skin_keeps_the_shared_shell_and_the_classic_content(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'precision');

        $this->assertSame('precision', HomeController::activeDesign());

        // Same shell view as classic: this design adds a stylesheet, not a page.
        $this->get('/')->assertOk()->assertViewIs('home');

        // And the same below-the-fold content, byte for byte the classic fragment.
        $this->get(route('home.sections'))
            ->assertOk()
            ->assertViewIs('home.deferred-sections');
    }

    public function test_the_skin_is_only_present_when_it_is_picked(): void
    {
        // Off by default.
        $this->get('/')->assertOk()->assertDontSee('szskin', false);

        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'precision');
        $this->get('/')->assertOk()->assertSee('szskin', false);

        // And gone again the moment it is switched back.
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'classic');
        $this->get('/')->assertOk()->assertDontSee('szskin', false);
    }

    public function test_the_skin_opens_light_but_still_obeys_a_visitor_who_chose_dark(): void
    {
        AppSetting::put(HomeController::DESIGN_SETTING_KEY, 'precision');

        // No preference saved: the skin is a light design, so it opens light.
        $this->get('/')->assertOk()->assertSee('light-mode', false);

        // An explicit dark choice still wins.
        $this->withCookie('1inme_theme', 'dark')
            ->get('/')
            ->assertOk()
            ->assertDontSee('class="szskin light-mode"', false);
    }

    public function test_classic_theme_behaviour_is_unchanged(): void
    {
        // Classic is dark unless the visitor asked for light. Unchanged.
        $this->get('/')->assertOk()->assertDontSee('light-mode"', false);

        $this->withCookie('1inme_theme', 'light')
            ->get('/')
            ->assertOk()
            ->assertSee('light-mode', false);
    }
}
