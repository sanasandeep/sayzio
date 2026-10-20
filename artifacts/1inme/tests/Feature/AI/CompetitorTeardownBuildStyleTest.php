<?php

namespace Tests\Feature\AI;

use App\Modules\User\Models\CompetitorTeardown;
use App\Services\AI\CompetitorTeardownService;
use Tests\TestCase;

/**
 * "Match their style" actually carries the competitor's palette, and
 * "Sayzio original" carries nothing.
 *
 * Background: buildBetterVersion() passed '' as the builder's ninth argument
 * -- $brandDirectives -- on every build. That is the channel the builder reads
 * for colour and brand direction, so a page built from a teardown could never
 * resemble the page it was built to beat. The colours were not even missing:
 * CompetitorPageFetcher was fetching Linktree's --profileBackground and
 * --button-style-background and dropping them into text_excerpt as prose for a
 * conversion critic to ignore.
 *
 * These assert the composition only. Whether the builder honours the directive
 * is the builder's own contract, covered by its own tests.
 */
class CompetitorTeardownBuildStyleTest extends TestCase
{
    private function directivesFor(array $extracted): string
    {
        $teardown = new CompetitorTeardown();
        $teardown->extracted = $extracted;

        $service = $this->app->make(CompetitorTeardownService::class);

        $method = new \ReflectionMethod($service, 'brandDirectivesFor');
        $method->setAccessible(true);

        return (string) $method->invoke($service, $teardown);
    }

    public function test_a_teardown_with_no_palette_yields_no_directive(): void
    {
        // Every teardown run before palette extraction existed looks like this.
        $this->assertSame('', $this->directivesFor([
            'title'        => 'Someone',
            'text_excerpt' => 'words words words',
        ]));

        $this->assertSame('', $this->directivesFor([
            'palette' => ['theme_color' => null, 'colors' => [], 'roles' => []],
        ]));
    }

    public function test_the_palette_reaches_the_directive_by_role(): void
    {
        // The values the real Linktree page served.
        $out = $this->directivesFor([
            'palette' => [
                'theme_color' => '#eceef1',
                'colors'      => ['#000000', '#ffffff', '#eceef1'],
                'roles'       => [
                    'background'  => '#eceef1',
                    'text'        => '#000000',
                    'button_bg'   => '#ffffff',
                    'button_text' => '#000000',
                ],
            ],
        ]);

        $this->assertStringContainsString('Page background: #eceef1', $out);
        $this->assertStringContainsString('Body text: #000000', $out);
        $this->assertStringContainsString('Button background: #ffffff', $out);
        $this->assertStringContainsString('Button text: #000000', $out);

        // theme_color duplicates the background here, so it should not be
        // restated as a separate line.
        $this->assertStringNotContainsString('Declared theme colour', $out);
    }

    public function test_the_directive_carries_its_two_guards(): void
    {
        $out = $this->directivesFor([
            'palette' => ['theme_color' => '#112233', 'colors' => ['#112233'], 'roles' => []],
        ]);

        // A palette lifted wholesale can put text below legibility; say so.
        $this->assertStringContainsString('4.5:1', $out);

        // Colours are fair game. Their logo and photography are not, and the
        // instruction has to say that or the model will happily oblige.
        $this->assertStringContainsString('do not imitate their wordmark, logo or photography', $out);
    }

    public function test_an_unrecognised_style_is_not_treated_as_match(): void
    {
        $this->assertSame('original', CompetitorTeardownService::STYLE_ORIGINAL);
        $this->assertSame('match', CompetitorTeardownService::STYLE_MATCH);
        $this->assertNotSame(CompetitorTeardownService::STYLE_ORIGINAL, CompetitorTeardownService::STYLE_MATCH);
    }
}
