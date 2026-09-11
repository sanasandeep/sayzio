<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsAgainstLargeSubjects;
use Tests\Support\BandScanner;
use Tests\TestCase;

/**
 * A coloured button keeps its white label in light mode.
 *
 * The light-mode sheet rewrites `.text-white` to #1f2937 with !important and
 * no scope. That is right for the 95% of a page that turns white with it, and
 * wrong for anything that does not -- which was every primary call to action
 * on the marketing site. Measured in Chromium on /domains before the fix:
 *
 *     class       "px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white ..."
 *     background  oklch(0.546 0.245 262.881)   (blue-600)
 *     color       rgb(31, 41, 55)              (near-black)
 *
 * About 2.2:1 -- readable if you look for it, and not what the button was
 * drawn to be. 191 occurrences across 94 views: "Claim your link free", "Get
 * started free", "See plans", "Subscribe".
 *
 * The fix is one rule in public/partials/section-surfaces.blade.php, so this
 * guard is in two halves. The first says the rule is still there and still
 * shaped the way it has to be. The second says it still covers everything on
 * the marketing site that needs it -- which is the half that catches the
 * regression nobody would think of as one: a new page with a new button
 * colour, months from now, quietly rendering near-black.
 */
class MarketingButtonInkTest extends TestCase
{
    use RefreshDatabase;
    use AssertsAgainstLargeSubjects;
    use BandScanner;

    private function stylesheet(): string
    {
        return (string) file_get_contents(
            resource_path('views/public/partials/section-surfaces.blade.php')
        );
    }

    /**
     * The one rule, as a string.
     *
     * Read out of the shipped partial rather than restated here, so the test
     * cannot drift away from what actually ships. If the rule is gone this
     * returns '' and every assertion below says so.
     */
    private function inkRule(): string
    {
        preg_match(
            '/html\.light-mode\s*\[class\*="text-white"\]\s*:is\((.*?)\)\s*\{[^}]*\}/s',
            $this->stylesheet(),
            $m
        );

        return $m[1] ?? '';
    }

    /** The class tokens the rule covers, as written in its `[class~="..."]` list. */
    private function coveredTokens(): array
    {
        preg_match_all('/\[class~="([^"]+)"\]/', $this->inkRule(), $m);

        return $m[1];
    }

    public function test_the_rule_is_still_in_the_shipped_stylesheet(): void
    {
        $this->assertNotSame('', $this->inkRule(), <<<'WHY'
            The coloured-button ink rule is gone from
            public/partials/section-surfaces.blade.php.

            Without it, light mode paints every primary call to action on the
            marketing site near-black on a saturated fill -- measured at about
            2.2:1 on /domains, across 191 buttons. It is one rule; it looks
            redundant next to Tailwind's own `text-white`, and it is not.
            WHY);
    }

    /**
     * Two shapes the rule depends on, both of which a well-meaning tidy-up
     * would undo.
     */
    public function test_the_rule_matches_whole_tokens_and_not_prefixes(): void
    {
        $rule = $this->inkRule();

        $this->assertSubjectDoesNotContain('[class*="bg-', $rule, <<<'WHY'
            The rule is matching background classes by substring.

            `[class*="bg-blue-5"]` matches `bg-blue-50`, a near-white tint --
            so a prefix match here puts white text on a white ground, which is
            the same defect this rule exists to fix, pointing the other way.

            Use `~=`, which matches a whole class token. That is also why
            `hover:bg-blue-700` does not trip the rule: it is its own token, so
            only the button's base state is matched.
            WHY);

        $this->assertSubjectContains('[class*="text-white"]', $this->stylesheet(), <<<'WHY'
            The rule has stopped matching `text-white` by substring.

            The substring is deliberate: it covers `text-white/80`, `/70`,
            `/60` and `/45` as well, and nothing else in the utility vocabulary
            contains the string. Narrowing it to `.text-white` leaves every
            softened label on a coloured fill near-black.
            WHY);
    }

    /**
     * The gradient fills.
     *
     * They are just as much a coloured button, and the first pass missed them
     * because they are not `bg-<colour>-<step>` tokens. Six labels survived on
     * three pages until these were added -- "Try a template free", "Contact
     * support", "Customize my dashboard".
     */
    public function test_the_rule_still_covers_the_gradient_fills(): void
    {
        $covered = $this->coveredTokens();

        foreach (['grad-bar', 'btn-cta', 'bg-gradient-to-r', 'bg-gradient-to-br'] as $token)
        {
            $this->assertContains($token, $covered, "the rule no longer covers `{$token}`, which paints a coloured fill just as a bg- token does");
        }
    }

    /**
     * The half that catches tomorrow's regression.
     *
     * Every marketing view is scanned for a class list that carries both
     * `text-white` and a saturated palette fill. Each fill token found has to
     * be one the rule covers. Today that set is small -- blue, emerald, green
     * -- and entirely dark, which is why this can be an exact check rather
     * than a hand-written table of which Tailwind colours are dark enough to
     * need it.
     *
     * Steps below 500 are not scanned: they are tints, they take dark ink by
     * design, and covering one would put white on near-white.
     *
     * If this ever fails on a genuinely light fill -- `bg-amber-500`, say --
     * the answer is still to look at the button rather than to widen the scan.
     * An author who wrote `text-white` on amber asked for a white label, and
     * white on amber is about 1.7:1 in either theme. That is a defect in the
     * button, not in this rule.
     */
    public function test_every_white_label_on_a_coloured_marketing_fill_is_covered(): void
    {
        $covered = $this->coveredTokens();
        $missing = [];

        foreach ($this->marketingViews() as $path) {
            $short = str_replace(resource_path('views/'), '', $path);
            $source = (string) file_get_contents($path);

            preg_match_all('/\bclass=(["\'])(.*?)\1/is', $source, $m, PREG_OFFSET_CAPTURE);

            foreach ($m[2] as $i => [$classes]) {
                if (! str_contains($classes, 'text-white')) {
                    continue;
                }

                foreach (preg_split('/\s+/', trim($classes)) as $token) {
                    if (! preg_match('/^bg-[a-z]+-(\d{3})$/', $token, $step) || (int) $step[1] < 500) {
                        continue;
                    }

                    if (in_array($token, $covered, true)) {
                        continue;
                    }

                    $line = substr_count(substr($source, 0, $m[2][$i][1]), "\n") + 1;
                    $missing[$token][] = "{$short}:{$line}";
                }
            }
        }

        $report = [];

        foreach ($missing as $token => $places) {
            $places = array_values(array_unique($places));
            $report[] = sprintf(
                '%s  (%d) %s%s',
                $token,
                count($places),
                implode(', ', array_slice($places, 0, 4)),
                count($places) > 4 ? ' ...' : ''
            );
        }

        $this->assertSame([], $report, sprintf(
            "These marketing buttons carry a white label on a saturated fill that the\n"
            . "light-mode ink rule does not cover. In light mode their text is rewritten\n"
            . "to #1f2937 -- near-black on a strong colour, around 2:1.\n\n"
            . "Add the fill token to the `:is(...)` list in\n"
            . "resources/views/public/partials/section-surfaces.blade.php, next to the\n"
            . "ones already there. Use `[class~=\"...\"]`, not `*=`.\n\n%s",
            implode("\n", $report)
        ));
    }

    /**
     * The scan has to be finding buttons at all.
     *
     * Every check above passes trivially against an empty set, and an empty
     * set is exactly what a broken class-attribute regex produces. This one
     * fails if the scan stops seeing the 80-odd blue CTAs that are certainly
     * there.
     */
    public function test_the_scan_finds_the_buttons_it_is_meant_to_be_judging(): void
    {
        $found = 0;

        foreach ($this->marketingViews() as $path) {
            preg_match_all('/\bclass=(["\'])(.*?)\1/is', (string) file_get_contents($path), $m);

            foreach ($m[2] as $classes) {
                if (str_contains($classes, 'text-white') && preg_match('/(?<![\w:-])bg-[a-z]+-[5-9]\d{2}\b/', $classes)) {
                    $found++;
                }
            }
        }

        $this->assertGreaterThan(50, $found, 'the button scan has collapsed; it is no longer finding white labels on coloured fills');
    }

    /** And the rule has to reach the browser, not just sit in a partial. */
    public function test_a_rendered_marketing_page_carries_the_rule(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertSubjectContains(
            '[class*="text-white"]',
            $html,
            '/about does not carry the rule that keeps a coloured button\'s label white'
        );
    }

    /** Every view that renders through the shared marketing layout, plus their includes. */
    private function marketingViews(): array
    {
        $pages = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), "@extends('public.layouts.site')")) {
                $pages[] = $file->getPathname();
            }
        }

        return $this->viewsReachableFrom(array_merge(
            $pages,
            [resource_path('views/public/layouts/site.blade.php')]
        ));
    }
}
