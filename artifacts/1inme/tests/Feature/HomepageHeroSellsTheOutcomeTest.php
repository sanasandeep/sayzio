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

    /**
     * ...and the rule has to WIN, which is a different claim.
     *
     * The assertion above passed while reduced motion was broken on the live
     * page. `.sj-scene *{animation:none!important}` was present and correct,
     * and six of Zio's animations ran straight through it, because
     * `.sj-zio-stage .zio-face{animation:...!important}` is (0,2,0) against
     * the universal selector's (0,1,0) and both sides are `!important`, so
     * specificity decides and the shorter one loses. A test that greps for
     * the rule's text cannot see that; it reads a lost cascade battle and a
     * won one exactly the same way.
     *
     * Short of running a browser in PHPUnit, the checkable invariant is the
     * one that was violated: anything in the Zio layer that FORCES an
     * animation with `!important` must be named, by that same selector, in a
     * reduced-motion rule that switches it off. Equal selector, equal
     * specificity, later in the document -- so the off switch wins.
     *
     * This is a text-coupling test and does not prove the page holds still;
     * it proves nobody can add a forced Zio animation without also stopping
     * it. The behaviour itself was checked in a browser with
     * getAnimations(), which is where the original bug was finally caught.
     */
    public function test_every_forced_zio_animation_is_named_in_the_reduced_motion_block(): void
    {
        $css = '';
        if (preg_match_all('/<style\b[^>]*>([\s\S]*?)<\/style>/i', $this->get('/')->assertOk()->getContent(), $m)) {
            $css = implode("\n", $m[1]);
        }
        $this->assertNotSame('', $css, 'no inline stylesheet found on the homepage');

        // Strip CSS comments first. Without this, the prose in a comment
        // sitting above a rule is captured as part of that rule's selector
        // and the comparison below never matches -- found by running this
        // logic over the real stylesheet before trusting it.
        $css = (string) preg_replace('#/\*[\s\S]*?\*/#', '', $css);

        $normalise = fn (string $sel) => trim(preg_replace('/\s+/', ' ', $sel));

        // Rules that force an animation on something inside the scene's Zio
        // stage. `animation:` only -- an animation-delay on its own stops
        // mattering the moment the animation is none.
        preg_match_all(
            '/([^{}]*\.sj-zio-stage[^{}]*)\{([^{}]*)\}/',
            $css,
            $rules,
            PREG_SET_ORDER
        );

        $forced = [];
        $switchedOff = [];
        foreach ($rules as [, $selectorList, $body]) {
            if (! preg_match('/(?<![a-z-])animation\s*:[^;}]*!important/i', $body)) {
                continue;
            }
            $isOff = (bool) preg_match('/(?<![a-z-])animation\s*:\s*none\s*!important/i', $body);
            foreach (explode(',', $selectorList) as $sel) {
                $sel = $normalise($sel);
                if ($sel === '' || ! str_contains($sel, '.sj-zio-stage')) {
                    continue;
                }
                if ($isOff) {
                    $switchedOff[$sel] = true;
                } else {
                    $forced[$sel] = true;
                }
            }
        }

        $this->assertNotEmpty(
            $forced,
            'no forced Zio animations found -- this test has lost sight of the markup it guards'
        );

        $unstoppable = array_values(array_diff(array_keys($forced), array_keys($switchedOff)));
        $this->assertSame(
            [],
            $unstoppable,
            "these selectors force a Zio animation with !important but are not switched off by an "
            . "equally specific reduced-motion rule, so they keep running for a reader who asked the "
            . "page to hold still:\n  " . implode("\n  ", $unstoppable)
        );

        // And the off switch must still be carrying real specificity: if it
        // ever collapses back to `.sj-scene *` alone, this fails.
        $this->assertGreaterThanOrEqual(
            2,
            max(array_map(
                fn (string $sel) => preg_match_all('/\.[A-Za-z0-9_-]+/', $sel),
                array_keys($switchedOff)
            )),
            'the reduced-motion rules no longer out-specify the Zio layer they are meant to override'
        );
    }

    /**
     * Sana, of the three-row folder card: "Folder have 3 lines only... here i
     * want to show multiple blocks or rows with animations of how data is
     * connected within it". Six rows, each carrying its own index so the CSS
     * can walk the pulse down them, and one of them deliberately empty.
     */
    public function test_the_folder_card_shows_six_wired_stations(): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($this->get('/')->assertOk()->getContent());
        $xpath = new \DOMXPath($dom);

        $rows = $xpath->query('//*[contains(@class,"sj-map")]//*[contains(@class,"stn")]');
        $this->assertSame(6, $rows->length, 'the folder card must show six stations, not three');

        $this->assertSame(
            1,
            $xpath->query('//*[contains(@class,"sj-map")]//*[contains(@class,"rail")]')->length,
            'the stations need the rail behind them, or they read as six unrelated cards'
        );

        // Each row carries --si, which is the only thing sequencing the walk.
        // Without it every row lights at once.
        for ($i = 0; $i < 6; $i++) {
            $this->assertSame(
                1,
                $xpath->query('//*[contains(@class,"stn") and contains(@style,"--si:'.$i.'")]')->length,
                "station row $i has no --si index, so the pulse cannot walk to it"
            );
        }

        $this->assertSame(
            1,
            $xpath->query('//*[contains(@class,"sj-map")]//*[contains(@class,"stn") and contains(@class,"dim")]')->length,
            'one station stays empty and dashed -- a folder with every slot filled is not a real one'
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
