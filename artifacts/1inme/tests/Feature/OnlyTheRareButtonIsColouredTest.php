<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * One weight is coloured, and it is not the default one.
 *
 * The app had a single idea of "this is a button you press", and it was
 * blue. `.btn-primary` painted the accent, `.btn-primary-gradient` painted an
 * accent gradient, the editor and pill tabs painted that same gradient when
 * active, the row buttons in the menu editors painted it again, and several
 * hundred call sites painted `bg-blue-600` by hand. A restaurant-menu editor
 * showed eight blue slabs of equal weight at once, and nothing on the screen
 * said which one mattered.
 *
 * The system is three weights: a reserved gradient for the page's own primary
 * action and the AI entry points, a solid near-black (elevated charcoal in
 * dark mode) for every other real action, and the existing ghost for
 * secondary ones. Selection is not an action, so the active tab takes a
 * neutral ink tint instead of borrowing the primary's surface.
 *
 * These are source-level tests, because the defect is a source-level one: the
 * weights are decided by which stylesheet wins and by which call sites wear
 * which class. What they CANNOT prove is that the override actually wins in a
 * browser -- that was measured on the live page instead, rule by rule, and
 * the one rule that could not win on specificity (the tabs, whose styles are
 * pushed per-page and so load last) is the only one carrying !important.
 */
class OnlyTheRareButtonIsColouredTest extends TestCase
{
    private function read(string $relative): string
    {
        $path = resource_path($relative);
        $this->assertFileExists($path, "{$relative} is missing");

        return file_get_contents($path);
    }

    private function system(): string
    {
        return $this->read('views/common/partials/button-system.blade.php');
    }

    /**
     * The layout loads it, and loads it LAST.
     *
     * The whole file is an override of theme-styles' own `.btn-primary`. An
     * include placed before theme-styles still parses, still defines every
     * rule, and still loses every one of them -- silently, which is the worst
     * kind of wrong.
     */
    public function test_the_button_system_is_loaded_after_the_theme(): void
    {
        $layout = $this->read('views/user/layouts/app.blade.php');

        $theme = strpos($layout, "@include('common.partials.theme-styles')");
        $buttons = strpos($layout, "@include('common.partials.button-system')");

        $this->assertNotFalse($theme, 'the user layout no longer includes theme-styles');
        $this->assertNotFalse($buttons, 'the user layout does not include the button system');
        $this->assertGreaterThan(
            $theme,
            $buttons,
            'the button system is included BEFORE theme-styles, so every rule in it loses'
        );
    }

    /**
     * Dark mode does not get black.
     *
     * The page ground is #0a0a0f. A black button on it is a hole, not a
     * button, so the token lifts to an elevated charcoal with a hairline.
     * Asserting the two values DIFFER is the point: one black for both modes
     * is exactly the mistake.
     */
    public function test_the_solid_is_a_different_black_in_each_mode(): void
    {
        $css = $this->system();

        preg_match('/:root\s*\{[^}]*--btn-solid:\s*([^;]+);/s', $css, $dark);
        preg_match('/html\.light-mode\s*\{[^}]*--btn-solid:\s*([^;]+);/s', $css, $light);

        $this->assertNotEmpty($dark[1] ?? null, 'no --btn-solid on :root');
        $this->assertNotEmpty($light[1] ?? null, 'no --btn-solid under html.light-mode');
        $this->assertNotSame(
            trim($dark[1]),
            trim($light[1]),
            'the same black is used in both modes; in dark mode that is a hole in the page'
        );
    }

    /**
     * The solid rule does not swallow the variants that are not solid.
     *
     * This is a regression, and it was caught by looking at the page rather
     * than at the CSS: a blanket `.rm-btn` rule blackened the ghost row
     * arrows and the red delete too, turning a row of four quiet icon buttons
     * into four black slabs.
     */
    public function test_the_solid_rule_spares_the_variants_that_are_not_solid(): void
    {
        $css = $this->system();

        foreach ([':not(.btn-primary-gradient)', ':not(.is-ai-cta)', ':not(.keep-accent)'] as $guard) {
            $this->assertStringContainsString(
                $guard,
                $css,
                "the solid rule has no {$guard} guard, so it repaints a button that is meant to keep its colour"
            );
        }

        $this->assertMatchesRegularExpression(
            '/\.rm-btn:not\(\.ghost\):not\(\.danger\)/',
            $css,
            'the row-button rule does not spare .ghost and .danger, so the quiet icon buttons and the delete go black too'
        );
    }

    /** Selection is styled, and from the page's own ink rather than a fixed grey. */
    public function test_the_active_tab_is_a_neutral_tint(): void
    {
        $css = $this->system();

        $this->assertStringContainsString('.editor-tab.is-active', $css);
        $this->assertStringContainsString('.pill-tab.is-active', $css);
        $this->assertStringContainsString(
            'var(--text-primary)',
            $css,
            'the active tab tint is not derived from the page ink, so it will not follow a theme or a nav preset'
        );
    }

    /**
     * Every `is-ai-cta` is on an AI entry point.
     *
     * The class exists to promote a button whose geometry comes from
     * elsewhere. If it starts appearing on ordinary buttons, the gradient
     * stops being rare, which is the only reason it works.
     */
    public function test_the_promoted_class_is_only_on_ai_entry_points(): void
    {
        $stray = [];

        foreach ($this->userViewFiles() as $path) {
            foreach (file($path) as $i => $line) {
                if (! str_contains($line, 'is-ai-cta')) {
                    continue;
                }
                if (preg_match('/ai-type-builder|Build with AI|ai_cta|is-ai-cta\b.*ai/i', $line)) {
                    continue;
                }
                $stray[] = basename($path) . ':' . ($i + 1);
            }
        }

        $this->assertSame([], $stray, "is-ai-cta on something that is not an AI entry point:\n" . implode("\n", $stray));
    }

    /**
     * The gradient is rare, and it still exists.
     *
     * Both halves matter. A ceiling alone passes if someone deletes the class
     * everywhere and leaves the app with no primary action at all; a floor
     * alone passes if it creeps back onto every button, which is the state
     * this change exists to end.
     */
    public function test_the_gradient_is_rare_and_still_there(): void
    {
        $sites = [];

        foreach ($this->userViewFiles() as $path) {
            foreach (file($path) as $i => $line) {
                // The definitions live in the stylesheets, not the markup.
                if (str_contains($line, '{') && str_contains($line, 'background')) {
                    continue;
                }
                if (str_contains($line, 'btn-primary-gradient') || str_contains($line, 'is-ai-cta')) {
                    $sites[] = basename($path) . ':' . ($i + 1);
                }
            }
        }

        $this->assertGreaterThan(0, count($sites), 'nothing in the user app wears the gradient any more');
        $this->assertLessThanOrEqual(
            14,
            count($sites),
            "the gradient is back on too many buttons to mean anything:\n" . implode("\n", $sites)
        );
    }

    /** The two demotions this change made, pinned so they do not drift back. */
    public function test_a_search_box_and_a_date_filter_are_not_primary_actions(): void
    {
        $this->assertStringNotContainsString(
            'links-search-submit btn-primary-gradient',
            $this->read('views/user/links/index.blade.php'),
            'the links search submit is wearing the reserved gradient again'
        );
        $this->assertStringNotContainsString(
            'btn-primary-gradient btn-primary">Apply dates',
            $this->read('views/user/stats/index.blade.php'),
            'the stats date filter is wearing the reserved gradient again'
        );
    }

    /**
     * The control for the two tests above: the collector really does read the
     * user views, and really does find the class somewhere.
     *
     * Without this, a glob that silently matched nothing would make both of
     * them pass, and a check that can only pass looks exactly like a check
     * that passed.
     */
    public function test_the_collector_actually_reads_the_views(): void
    {
        $files = $this->userViewFiles();

        $this->assertGreaterThan(100, count($files), 'the user view glob found almost nothing');
        $this->assertNotEmpty(
            array_filter($files, fn ($p) => str_ends_with($p, 'user/layouts/app.blade.php')),
            'the glob does not reach the user layout, where the primary CTA lives'
        );
    }

    /** @return array<int,string> */
    private function userViewFiles(): array
    {
        $dir = resource_path('views/user');
        $out = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $out[] = $file->getPathname();
            }
        }

        sort($out);

        return $out;
    }
}
