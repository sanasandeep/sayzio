<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * "When This Link Gets Clicked", after Sana's three notes on 2026-09-23.
 *
 * 1. "It should highlight value when cursor is on that tile." The grid could
 *    only say "darker than that one". Every block now carries its day, its
 *    two-hour window and its count, and hovering (or tapping, on a phone)
 *    writes them into a readout under the title.
 *
 * 2. "Why only blue? Can it be multi colour?" Yes -- with one rule kept: the
 *    ramp still gets DARKER as it gets busier. Blue -> indigo -> violet ->
 *    magenta reads as colour while staying ordered, which a rainbow does not:
 *    a red-green reader, or anyone printing it, still sees the order.
 *
 * 3. "Fix responsiveness of that page." At laptop width six action buttons
 *    squeezed the hero title to "Sa..." and broke the chips into one word per
 *    line, on top of the ribbon; on a phone the date filter ran off the right
 *    edge with the Apply button.
 */
class TheClickHeatmapSaysItsNumbersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Link $link;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00'));   // a Wednesday

        $this->user = User::factory()->create([
            'settings'     => ['ui_pack' => 'aurora'],
            'onboarded_at' => Carbon::parse('2026-01-01'),
        ]);
        $ws = app(WorkspaceContext::class)->resolve($this->user);

        $this->link = Link::create([
            'user_id' => $this->user->id, 'workspace_id' => $ws?->id,
            'type' => 'link', 'alias' => 'heat', 'url' => 'https://example.com',
            'title' => 'A link with a long enough title to be squeezed', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function clicks(string $when, int $n): void
    {
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = [
                'link_id' => $this->link->id, 'ip_address' => '203.0.113.'.($i % 250),
                'clicked_at' => Carbon::parse($when),
            ];
        }
        LinkClick::insert($rows);
    }

    private function stats(): string
    {
        return $this->actingAs($this->user)
            ->get('/user/links/'.$this->link->id)
            ->assertOk()->getContent();
    }

    // ===== 1. The value =====

    /** Every block carries the numbers a person needs read out to them. */
    public function test_every_block_carries_its_day_window_and_count(): void
    {
        $this->clicks('2026-09-22 13:10', 40);   // Tuesday, the 12:00 block

        $html = $this->stats();

        $this->assertStringContainsString('data-when="Tue 12:00–14:00"', $html);
        $this->assertMatchesRegularExpression(
            '/data-when="Tue 12:00–14:00"\s+data-count="40"\s+data-unit="clicks"/',
            $html,
            'the block must say which day, which two hours, and how many'
        );
        // And it still says so to a screen reader / native tooltip.
        $this->assertStringContainsString('title="Tuesday 12:00–14:00 &middot; 40 clicks"', $html);
    }

    /** The readout exists, starts on the peak, and can be put back to it. */
    public function test_the_readout_starts_on_the_peak(): void
    {
        $this->clicks('2026-09-22 13:10', 40);
        $this->clicks('2026-09-21 09:10', 5);

        $html = $this->stats();

        $this->assertMatchesRegularExpression(
            '/id="when-readout"[^>]*data-idle="Peak <b>40<\/b> clicks &middot; Tue 12:00"/',
            $html,
            'the readout must default to the busiest block'
        );
        $this->assertStringContainsString('hover a block', $html);

        // The peak is no longer ALSO a pill in the header: one place, not two.
        $this->assertStringNotContainsString('<span class="section-pill">Peak', $html);
    }

    /** Hover and tap both drive it, so a phone is not left out. */
    public function test_hover_and_tap_both_read_a_block_out(): void
    {
        $this->clicks('2026-09-22 13:10', 12);

        $html = $this->stats();

        $this->assertStringContainsString("grid.addEventListener('mouseover'", $html);
        $this->assertStringContainsString("grid.addEventListener('click'", $html,
            'a phone has no hover; a tap must read the block out');
        $this->assertStringContainsString("grid.addEventListener('mouseleave'", $html,
            'and moving away must put the peak back');
    }

    // ===== 2. The colour =====

    /** Busier blocks sit further up the ramp; empty ones are level 0. */
    public function test_the_step_follows_the_count(): void
    {
        $this->clicks('2026-09-22 13:10', 100);   // the peak
        $this->clicks('2026-09-21 09:10', 1);     // a single click

        $html = $this->stats();
        preg_match_all('/data-level="(\d)"\s+data-when="([^"]+)"\s+data-count="([^"]+)"/', $html, $m, PREG_SET_ORDER);

        $byWhen = [];
        foreach ($m as $row) {
            $byWhen[$row[2]] = ['level' => (int) $row[1], 'count' => $row[3]];
        }

        $this->assertSame(6, $byWhen['Tue 12:00–14:00']['level'], 'the busiest block is the top step');
        $this->assertSame(1, $byWhen['Mon 08:00–10:00']['level'], 'one click out of a hundred is the first step, not an invisible one');
        $this->assertSame(0, $byWhen['Fri 02:00–04:00']['level'], 'a block with no clicks is not on the ramp at all');
        $this->assertCount(84, $m, 'seven days by twelve two-hour blocks');
    }

    /**
     * The ramp is multi-colour, and it is ordered.
     *
     * Both halves matter. Six tints of one blue was the complaint; a rainbow
     * would be the wrong fix, because hue alone carries no order. These six
     * steps turn blue -> indigo -> violet -> magenta AND get darker, which is
     * what keeps them readable for a colour-blind reader and in greyscale.
     */
    public function test_the_ramp_is_multi_colour_and_still_ordered(): void
    {
        $css = (string) file_get_contents(resource_path('views/user/links/show.blade.php'));

        preg_match('/#when-card \{(.+?)\}/s', $css, $light);
        preg_match('/html:not\(\.light-mode\) #when-card \{(.+?)\}/s', $css, $dark);

        foreach (['light' => $light[1] ?? '', 'dark' => $dark[1] ?? ''] as $mode => $block) {
            preg_match_all('/--hl([1-6]): (#[0-9a-f]{6})/i', $block, $steps, PREG_SET_ORDER);
            $this->assertCount(6, $steps, "the {$mode} ramp must have six steps");

            $hues = [];
            $lums = [];
            foreach ($steps as $s) {
                [$r, $g, $b] = sscanf($s[2], '#%02x%02x%02x');
                $lums[] = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $hues[] = $max === $min ? 0 : (int) round(rad2deg(atan2(
                    sqrt(3) * ($g - $b),
                    2 * $r - $g - $b
                )) + 360) % 360;
            }

            $this->assertGreaterThan(30, max($hues) - min($hues),
                "the {$mode} ramp is a single hue again; it was asked to carry colour");

            $ordered = $lums;
            sort($ordered);
            if ($mode === 'light') {
                $ordered = array_reverse($ordered);   // light surface: busiest is darkest
            }
            $this->assertSame($ordered, $lums,
                "the {$mode} ramp must get steadily darker (or lighter, on dark) as it gets busier, "
                .'or the colours stop meaning more and less');
        }
    }

    /** The legend shows the same six steps the grid uses. */
    public function test_the_legend_shows_the_whole_ramp(): void
    {
        $this->clicks('2026-09-22 13:10', 9);

        $html = $this->stats();

        foreach (range(1, 6) as $step) {
            $this->assertStringContainsString('background: var(--hl'.$step.')', $html);
        }
    }

    // ===== 3. The page at other widths =====

    /**
     * The hero gives the title and chips a column of their own, so the
     * actions wrap under them instead of squeezing them.
     */
    public function test_the_hero_lets_the_title_keep_its_room(): void
    {
        $hero   = (string) file_get_contents(resource_path('views/user/partials/page-hero.blade.php'));
        $layout = (string) file_get_contents(resource_path('views/user/layouts/app.blade.php'));

        $this->assertStringContainsString('class="hero-main"', $hero);
        $this->assertStringContainsString('class="hero-actions"', $hero);
        $this->assertMatchesRegularExpression('/\.hero-main \{[^}]*flex: 1 1 340px/', $layout,
            'without a flex-basis the identity column collapses and the title truncates');
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 900px\) \{\s*\.hero-actions \{ width: 100%; \}/',
            $layout,
            'below 900px the buttons take their own row'
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 900px\) \{.*?\.page-hero > \.cribbon \{ display: none; \}/s',
            $layout,
            'and the ribbon stops being drawn behind them'
        );

        // It still renders, with the real page.
        $this->assertStringContainsString('hero-actions', $this->stats());
    }

    /** The date filter wraps rather than running off a phone screen. */
    public function test_the_date_filter_wraps_on_a_phone(): void
    {
        $html = $this->stats();

        $this->assertStringContainsString('class="period-dates"', $html);
        $this->assertStringContainsString('aria-label="From date"', $html);

        $css = (string) file_get_contents(resource_path('views/user/links/show.blade.php'));
        $this->assertMatchesRegularExpression('/\.period-dates \{[^}]*flex-wrap: wrap/', $css);
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 640px\) \{\s*\.period-dates \{ margin-left: 0; width: 100%; \}/',
            $css
        );
    }

    /** The grid narrows on a phone instead of overflowing its card. */
    public function test_the_grid_narrows_on_a_phone(): void
    {
        $css = (string) file_get_contents(resource_path('views/user/links/show.blade.php'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 560px\) \{\s*\.when-heat, \.when-heat-hours \{ grid-template-columns: 22px repeat\(12, minmax\(0, 1fr\)\)/',
            $css,
            'the day column and the twelve blocks must both shrink, together'
        );
        $this->assertStringContainsString('repeat(12, minmax(0, 1fr))', $css,
            '1fr tracks do not shrink below their content; minmax(0, 1fr) does');
    }
}
