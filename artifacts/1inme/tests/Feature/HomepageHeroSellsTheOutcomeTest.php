<?php
namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The hero sells the outcome, and does it without JavaScript.
 *
 * ---- Why this test changed shape -------------------------------------
 *
 * It used to assert seven [data-journey-step] buttons, because the hero was
 * a seven-row journey panel. That panel is gone: Sana rejected it and two
 * reworkings of it ("design looks too old", "i neee cdless of text.. more
 * graphics and animations"), and chose an illustrated scene containing real
 * product surfaces instead.
 *
 * So the step count is not weakened, it is replaced by the claim the new
 * hero actually makes: ONE customer record holding SEVEN lines, with two of
 * them still unwritten. Same number, now asserted where it means something.
 *
 * What has not changed, and must not: the hero renders server-side, the CTA
 * points at the right place for guests and for signed-in users, and the
 * illustrative figures are labelled as illustrative.
 */
class HomepageHeroSellsTheOutcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scene_renders_without_javascript_and_guest_can_register(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertSee('From your first visitor')
            ->assertSee('repeat customer')
            ->assertSee('Illustrative journey')
            ->assertSee('Alex Morgan')
            ->assertSee('Start free')
            ->assertSee('See how Sayzio works');

        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);

        // Every surface is server-rendered. The scene animates with CSS, so a
        // visitor with JS blocked sees the whole picture, just not moving.
        foreach (['sj-scene', 'sj-map', 'sj-phone', 'sj-rec2', 'sj-rails'] as $part) {
            $this->assertGreaterThan(
                0,
                $xpath->query('//*[contains(@class,"'.$part.'")]')->length,
                "the hero scene is missing its '$part' layer in the server-rendered HTML"
            );
        }

        // Seven lines in one record: the whole argument of the hero.
        $this->assertSame(7, $xpath->query('//*[@data-chip]')->length);
        $this->assertSame(
            2,
            $xpath->query('//*[@data-chip and contains(@class,"pend")]')->length,
            'two lines must still be unwritten -- a record with every box ticked is not a live one'
        );

        $cta = $xpath->query('//*[@id="hero"]//a[contains(@class,"sj-primary")]')->item(0);
        $this->assertSame(route('register.page'), $cta->getAttribute('href'));

        if (getenv('HERO_UI_FIXTURE')) {
            file_put_contents('/tmp/hero-scene-guest.html', $response->getContent());
        }
    }

    /**
     * The scene moves a lot. Someone who has asked their system not to
     * animate things must get a still picture, and that is one CSS rule away
     * from being lost in any future edit -- so it is asserted.
     */
    public function test_the_scene_is_stilled_for_reduced_motion(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('prefers-reduced-motion', $html);
        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion[^}]*\}[\s\S]{0,2200}?\.sj-scene/',
            $html,
            'the hero scene has no reduced-motion rule: every float, blink and pulse would still run'
        );
    }

    public function test_signed_in_cta_opens_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/')->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $cta = $xpath->query('//*[@id="hero"]//a[contains(@class,"sj-primary")]')->item(0);
        $this->assertSame(route('user.dashboard'), $cta->getAttribute('href'));
        $this->assertStringContainsString('Go to dashboard', $cta->textContent);
        if (getenv('HERO_UI_FIXTURE')) {
            file_put_contents('/tmp/hero-scene-auth.html', $response->getContent());
        }
    }

    public function test_previous_shipped_bundle_upgrades_but_custom_copy_is_preserved(): void
    {
        $copy = SitePagesContent::normalizeHeroCopy(['headline' => 'Never miss another customer.', 'highlight' => 'customer', 'subheading' => 'One link for your page, your QR codes and your short links. And when you are busy, **your AI Zio answers your visitors and picks up your calls**. **Free forever**, no card.']);
        $this->assertStringContainsString('repeat customer', $copy['headline']);
        $copy = SitePagesContent::normalizeHeroCopy(['headline' => 'Custom title', 'subheading' => 'Custom content']);
        $this->assertSame('Custom title', $copy['headline']);
        $this->assertSame('Custom content', $copy['subheading']);
    }
}
