<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\SiteStat;
use App\Modules\Common\Support\AboutFigures;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The site's figures are real in the HTML, and the same figure everywhere.
 *
 * Two bugs, both about a number the page told the truth about only after
 * JavaScript had run.
 *
 * 1. The count-up band rendered `0` and animated up to the real value. So the
 *    served HTML said "0+ Users Worldwide", "0+ Biolinks Created", "0+
 *    Countries Reached" -- which is what a crawler indexes, what a reader with
 *    no JS sees, and what anyone opening view-source concludes about the size
 *    of the product. The partial's own comment claimed the opposite ("the
 *    rendered text is the final value so the figure is correct with JS off"),
 *    which is how it survived: the code said 0 and the comment said otherwise,
 *    and the comment is what got read.
 *
 *    Zeroing is now the runtime's job, done at init before the band is on
 *    screen, so the animation is unchanged.
 *
 * 2. The About page read `SiteStat->value` -- the raw column, holding what an
 *    admin typed -- while the home page read `displayValue()`, which regroups
 *    Indian-grouped figures for an international audience. One number, two
 *    spellings: `375,000+` on the home page and `3,75,000+` on About. A reader
 *    does not conclude "different convention", they conclude one of them is
 *    wrong and quietly discount every other figure on the page.
 *
 * Unlike the recent layout fixes, both of these are visible over HTTP, so
 * these are real assertions on real responses rather than string checks
 * against a Blade file.
 */
class StatsAreRealInTheServedHtmlTest extends TestCase
{
    use RefreshDatabase;

    private function seedStats(): void
    {
        SiteStat::create([
            'label' => 'Users Worldwide', 'value' => '3,75,000', 'suffix' => '+',
            'icon' => 'fa-users', 'color' => '#3d6bff', 'is_active' => true, 'sort_order' => 1,
        ]);
        SiteStat::create([
            'label' => 'Biolinks Created', 'value' => '1,05,000', 'suffix' => '+',
            'icon' => 'fa-link', 'color' => '#1bd4d9', 'is_active' => true, 'sort_order' => 2,
        ]);
    }

    public function test_the_served_html_carries_the_real_figures(): void
    {
        $this->seedStats();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            '375,000',
            $html,
            'the home page no longer serves its headline figure in the HTML'
        );

        // The exact shape the bug produced: a count-up span whose text is 0,
        // followed by the suffix.
        $this->assertSame(
            0,
            preg_match_all('/js-stat-count[^>]*>\s*0\s*<\/span>/', $html),
            'A statistic is being served as 0 and counted up by JavaScript. '
            . 'That is the number Google indexes and the number a reader with '
            . 'JS off is shown. Render the real value and let the runtime zero '
            . 'it at init instead.'
        );
    }

    /**
     * The animation still has what it needs, so the fix above cannot be
     * "solved" by deleting the count-up.
     */
    public function test_the_count_up_still_has_its_target_and_display(): void
    {
        $this->seedStats();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/js-stat-count[^>]*data-target="\d+"[^>]*data-display="[^"]+"/',
            $html,
            'the count-up contract (data-target / data-display) is gone, so the '
            . 'figures can no longer animate'
        );
    }

    public function test_about_and_home_print_the_same_number_the_same_way(): void
    {
        $this->seedStats();

        $home  = $this->get('/')->assertOk()->getContent();
        $about = $this->get('/about')->assertOk()->getContent();

        $this->assertStringContainsString('375,000', $about);
        $this->assertStringContainsString('375,000', $home);

        // The raw stored spelling must not reach either page AS COPY. Script
        // and style contents are excluded the same way the em-dash guard
        // excludes them: the count-up runtime carries a comment naming
        // `3,75,000` as the thing that went wrong, and a rule that cannot tell
        // an explanation from the mistake it explains only teaches people to
        // delete the explanation.
        $visible = static fn (string $h): string => (string) preg_replace(
            '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $h
        );

        foreach (['home' => $visible($home), 'about' => $visible($about)] as $name => $html) {
            $this->assertStringNotContainsString(
                '3,75,000',
                $html,
                "The $name page is printing the raw stored value with Indian "
                . 'grouping instead of going through SiteStat::displayValue(). '
                . 'The other page uses displayValue(), so the two now show one '
                . 'number in two spellings.'
            );
        }
    }

    /**
     * The resolver About goes through, asserted directly: the failure above is
     * only visible when a figure happens to be Indian-grouped, and this says
     * which call is responsible.
     */
    public function test_the_about_resolver_uses_the_display_value(): void
    {
        $this->seedStats();

        $creators = AboutFigures::creators();

        $this->assertNotNull($creators);
        $this->assertSame(
            '375,000',
            $creators['value'],
            'AboutFigures::creators() is reading SiteStat->value (what an admin '
            . 'typed) rather than displayValue() (how it should be read)'
        );
    }

    /**
     * The third place the same number appears: the green badge under the hero
     * buttons. It was a hard-coded literal, so the page agreed with itself
     * only until somebody edited Site Stats -- the next version of the bug
     * above, waiting for the next update. It now reads the same row.
     */
    public function test_the_hero_badge_tracks_the_stats_row(): void
    {
        SiteStat::create([
            'label' => 'Users Worldwide', 'value' => '4,20,000', 'suffix' => '+',
            'icon' => 'fa-users', 'color' => '#3d6bff', 'is_active' => true, 'sort_order' => 1,
        ]);

        $badge = SitePagesContent::heroBadgesDefault()[0];

        $this->assertSame(
            '420,000+',
            $badge['value'],
            'the hero badge is printing a figure of its own instead of the one '
            . 'in Site Stats, so the home page can show two different counts '
            . 'of the same thing'
        );
    }

    /**
     * The shipped seed writes `3.75 Lakh`, and nothing understood it: the
     * band printed the unit, and the count-up -- which reads the digits out
     * of the string -- animated the headline figure up to three point seven
     * five. Both now read it as the number it names.
     */
    public function test_an_indian_unit_is_read_as_the_number_it_names(): void
    {
        SiteStat::query()->delete();

        $stat = SiteStat::create([
            'label' => 'Users Worldwide', 'value' => '3.75 Lakh', 'suffix' => '+',
            'icon' => 'fa-users', 'color' => '#3d6bff', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->assertSame('375,000', $stat->displayValue());
        $this->assertSame(375000.0, $stat->numericTarget());
    }

    /**
     * But a value with a separator in it is ambiguous -- `1,43 Lakh` is
     * either 1.43 Lakh mistyped or 143 Lakh missing a zero -- so it is
     * printed as written rather than multiplied by a guess.
     */
    public function test_an_ambiguous_unit_is_left_alone(): void
    {
        $stat = new SiteStat(['value' => '1,43 Lakh']);

        $this->assertSame('1,43 Lakh', $stat->displayValue());
    }

    /**
     * And it still says something on an install with no stats row at all,
     * rather than shipping a badge with no figure in it.
     */
    public function test_the_hero_badge_has_a_figure_with_no_stats_row(): void
    {
        SiteStat::query()->delete();

        $this->assertMatchesRegularExpression(
            '/^[\d,]+\+$/',
            SitePagesContent::heroBadgesDefault()[0]['value'],
            'with no Site Stats row the hero badge has no figure to show'
        );
    }
}
