<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsAgainstLargeSubjects;
use Tests\Support\BandScanner;
use Tests\TestCase;

/**
 * The same rule the homepage lives by, applied to the 35 marketing pages.
 *
 * The homepage got section separators because a long scroll with no rules
 * between its bands reads as one continuous surface with headings scattered
 * through it. Every marketing page had exactly that problem and none of the
 * fix: measured in Chromium before this landed, all 41 of them had bands with
 * no separator of any kind -- 162 in total, from /about's eight to the AI
 * marketing strategist's ten.
 *
 * They also had none of the CSS. The rules lived inside home.blade.php's
 * <style>, so `sec-rule` was a class only one page in the site could use. It
 * is public/partials/section-surfaces.blade.php now, included by home.blade.php
 * and by public/layouts/site.blade.php, which is what made this test possible
 * rather than just desirable.
 *
 * The rule: a band carries `sec-rule` (it sits on the page's ground and draws
 * the hairline), `sec-ground` (it paints a ground of its own, in both themes,
 * and the colour change is the separator), or `sec-first` (nothing above it).
 * There is no fourth option and no opt-out list.
 *
 * `sec-first` is declared rather than inferred, and that was learnt the hard
 * way. The first attempt computed it -- "the first <section> in a view that
 * @extends a layout" -- which reads well and is wrong: the homepage's hero is
 * in a partial, not in home.blade.php, so the inference exempted nothing and
 * asked for a hairline directly under the site header. An id-keyed exemption
 * list was the other option, and a list of exemptions is exactly what let the
 * homepage's dividers go missing in the first place.
 */
class MarketingPageSectionDividerTest extends TestCase
{
    use RefreshDatabase;
    use AssertsAgainstLargeSubjects;
    use BandScanner;

    /**
     * Every view that renders through the shared marketing layout.
     *
     * Found by looking for the layout, not by globbing one directory. The
     * first version globbed `views/public/*.blade.php` and missed fourteen
     * pages living one level down -- pricing, compare, the blog index and its
     * category and tag pages, the subscription manager -- along with the
     * event pages under views/common. Ten bands stayed undeclared and the
     * test said nothing, because it had never looked at the files.
     */
    private function marketingPageViews(): array
    {
        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), "@extends('public.layouts.site')")) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /** Those pages plus everything they and the layout include. */
    private function marketingViews(): array
    {
        return $this->viewsReachableFrom(array_merge(
            $this->marketingPageViews(),
            [resource_path('views/public/layouts/site.blade.php')]
        ));
    }

    public function test_the_scan_finds_the_marketing_pages(): void
    {
        $pages = array_map(fn ($p) => basename($p, '.blade.php'), $this->marketingPageViews());

        $this->assertGreaterThan(40, count($pages), 'the page scan collapsed; it is not finding the layout any more');

        foreach (['plans', 'index', 'manage'] as $nested) {
            $this->assertContains($nested, $pages, "the scan is not reaching views one level down (expected {$nested})");
        }

        foreach (['about', 'features', 'domains', 'analytics', 'forms', 'notifications', 'workspace-team'] as $expected) {
            $this->assertContains($expected, $pages, "the scan never found the {$expected} page");
        }
    }

    public function test_every_marketing_band_declares_a_rule_or_a_ground(): void
    {
        $undeclared = [];

        foreach ($this->marketingViews() as $path) {
            $short = str_replace(resource_path('views/'), '', $path);
            $source = (string) file_get_contents($path);

            foreach ($this->bandsIn($source) as [$tag, $classes, $line]) {
                if ($this->declaresSeparator($classes)) {
                    continue;
                }

                preg_match('/\bid=(["\'])(.*?)\1/is', $tag, $idMatch);

                $undeclared[] = sprintf(
                    '%s:%d  %s',
                    $short,
                    $line,
                    ($idMatch[2] ?? '') !== '' ? 'id="' . $idMatch[2] . '"' : trim(substr($classes, 0, 46))
                );
            }
        }

        $this->assertSame([], $undeclared, sprintf(
            "These marketing bands say nothing about how they separate from the band above.\n\n"
            . "Add ONE of:\n"
            . "  sec-rule    the band sits on the page's ground and carries the hairline\n"
            . "  sec-ground  the band paints a ground of its own, in BOTH themes\n"
            . "  sec-first   nothing above it; it is the first band on the page\n\n"
            . "There is no opt-out list. If the band really has nothing above it, say so\n"
            . "with sec-first -- which draws nothing, and exists so that a band with no\n"
            . "declaration is one somebody forgot rather than one somebody decided about.\n\n%d found:\n  %s",
            count($undeclared),
            implode("\n  ", $undeclared)
        ));
    }

    /** A band declaring both is a band whose author has not decided. */
    public function test_no_marketing_band_claims_both(): void
    {
        $both = [];

        foreach ($this->marketingViews() as $path) {
            $short = str_replace(resource_path('views/'), '', $path);

            foreach ($this->bandsIn((string) file_get_contents($path)) as [, $classes, $line]) {
                if (preg_match('/(?<![\w-])sec-rule(?![\w-])/', $classes)
                    && preg_match('/(?<![\w-])sec-ground(?![\w-])/', $classes)) {
                    $both[] = "{$short}:{$line}";
                }
            }
        }

        $this->assertSame([], $both, 'a band carries the hairline or brings a ground, not both: ' . implode(', ', $both));
    }

    /**
     * The marketing layout has to actually load the stylesheet. Marking 162
     * bands with a class no page defines would leave the pages exactly as they
     * were, with a green test.
     */
    public function test_the_marketing_layout_loads_the_separator_stylesheet(): void
    {
        $layout = (string) file_get_contents(resource_path('views/public/layouts/site.blade.php'));

        $this->assertSubjectContains(
            'public.partials.section-surfaces',
            $layout,
            'the marketing layout does not include the stylesheet that defines sec-rule'
        );

        $partial = (string) file_get_contents(resource_path('views/public/partials/section-surfaces.blade.php'));

        $this->assertSubjectContains('.sec-rule::before', $partial, 'the partial does not draw the hairline');
        $this->assertSubjectContains('html.light-mode .sec-rule::before', $partial, 'the hairline has no light-mode colour');
    }

    /**
     * And it has to reach the browser. The layout include is markup; this is
     * the rendered page.
     */
    public function test_a_rendered_marketing_page_carries_the_stylesheet_and_the_class(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertSubjectContains('.sec-rule::before', $html, '/about does not load the separator stylesheet');
        $this->assertSubjectContains('sec-rule', $html, '/about has no band carrying the class');
    }
}
