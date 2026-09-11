<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The seven rules of the marketing design system.
 *
 * Sana set them after looking at /pricing and saying both card designs
 * still looked ugly:
 *
 *   1. no coloured text
 *   2. bordered cards
 *   3. sectional dividers
 *   4. one set of CTA designs
 *   5. grids
 *   6. gradient ribbons
 *   7. fewer coloured icons
 *
 * They live in resources/views/partials/marketing-system.blade.php. This
 * checks that the pages built on it keep to them, because the failure mode
 * is not a broken page -- it is one hurried edit adding `text-blue-300`,
 * and then another, until the page is back where it started.
 *
 * SCOPE. /pricing only, for now. The system is meant for every marketing
 * page and the rest have not been converted yet; pointing this at all 42
 * today would produce a wall of failures about pages nobody has touched,
 * and a wall of failures gets switched off. Widen it one page at a time,
 * as each is converted.
 */
class MarketingSystemRulesTest extends TestCase
{
    /** @return list<string> */
    private function convertedViews(): array
    {
        return glob(resource_path('views/public/pricing/*.blade.php')) ?: [];
    }

    private function system(): string
    {
        return (string) file_get_contents(
            resource_path('views/partials/marketing-system.blade.php')
        );
    }

    /** A view's markup: no <style> block, no Blade comments. */
    private function markup(string $path): string
    {
        $s = (string) file_get_contents($path);
        $s = preg_replace('/<style>.*?<\/style>/s', '', $s) ?? $s;

        return preg_replace('/\{\{--.*?--\}\}/s', '', $s) ?? $s;
    }

    /** RULE 1 — no coloured text. */
    public function test_no_coloured_text(): void
    {
        $offenders = [];

        foreach ($this->convertedViews() as $path) {
            $short = str_replace(resource_path('views/'), '', $path);
            $markup = $this->markup($path);

            preg_match_all(
                '/\btext-(?:blue|amber|emerald|cyan|indigo|violet|purple|fuchsia|pink|rose|red|orange|yellow|lime|green|teal|sky)-\d{2,3}(?:\/\d+)?/',
                $markup,
                $hits,
                PREG_OFFSET_CAPTURE
            );

            foreach ($hits[0] as [$cls, $offset]) {
                $line = substr_count(substr($markup, 0, $offset), "\n") + 1;
                $offenders[] = "{$short}  {$cls}  (near line {$line} of the stripped markup)";
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Rule 1: no coloured text.\n\n"
            . "Words take --sy-ink / --sy-ink-2 / --sy-ink-3. Colour belongs to ribbons,\n"
            . "borders and filled buttons. A blue eyebrow here and an amber number there is\n"
            . "how a page stops looking designed.\n\n"
            . "Use .sy-eyebrow, .sy-blurb, .sy-note, or .sy-ico for a glyph.\n\n%d found:\n  %s",
            count($offenders),
            implode("\n  ", $offenders)
        ));
    }

    /** RULE 2 — cards are bordered, not glowing. */
    public function test_cards_are_bordered(): void
    {
        $system = $this->system();

        $this->assertMatchesRegularExpression(
            '/\.sy-card\s*\{[^}]*border:\s*1px solid var\(--sy-rule\)/s',
            $system,
            'the system card lost its hairline border'
        );

        // The old chrome was a blur, an inset highlight stack and a gradient
        // ring: three effects doing one job, which is what read as plastic.
        //
        // Scoped to CARD selectors, not to the file. The first version of
        // this banned `backdrop-filter` outright and failed on the feature
        // matrix's sticky header -- where a blur is correct, because rows
        // scroll underneath it and it has to obscure them.
        foreach ($this->convertedViews() as $path) {
            $css = (string) file_get_contents($path);
            preg_match_all('/([^{}]*card[^{}]*)\{([^}]*)\}/i', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as [, $selector, $body]) {
                if (str_contains($selector, '@')) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/backdrop-filter|inset 1\.5px|mask-composite/i',
                    $body,
                    str_replace(resource_path('views/'), '', $path)
                    . ' has glass chrome back on `' . trim(preg_replace('/\s+/', ' ', $selector) ?? '')
                    . '`; rule 2 says a hairline border and a soft shadow'
                );
            }
        }
    }

    /** RULE 3 — sections are separated by dividers. */
    public function test_sections_use_dividers(): void
    {
        $this->assertStringContainsString('.sy-divider-label', $this->system(), 'the system lost its divider');

        $plans = $this->markup(resource_path('views/public/pricing/plans.blade.php'));

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($plans, 'sy-divider'),
            'rule 3: /pricing runs plans, the comparison matrix and coins together '
            . 'with nothing between them but a margin'
        );
    }

    /** RULE 4 — one set of CTAs. */
    public function test_one_set_of_ctas(): void
    {
        $system = $this->system();

        foreach (['.sy-cta', '.sy-cta--ghost', '.sy-cta--held'] as $cta) {
            $this->assertStringContainsString($cta, $system, "the system lost {$cta}");
        }

        // Every call to action in the converted views goes through them.
        $plans = $this->markup(resource_path('views/public/pricing/plans.blade.php'));
        $this->assertStringNotContainsString(
            'btn-cta',
            $plans,
            'rule 4: a second button style (btn-cta) is back on /pricing'
        );
        $this->assertStringNotContainsString(
            'btn-coin',
            $plans,
            'rule 4: the amber coin button is back; there are three CTA styles, not four'
        );
    }

    /** RULE 5 — grids. */
    public function test_rows_are_grids(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.sy-grid\s*\{[^}]*display:\s*grid/s',
            $this->system(),
            'the system grid is gone'
        );

        $this->assertStringContainsString(
            'sy-grid',
            $this->markup(resource_path('views/public/pricing/plans.blade.php')),
            'rule 5: /pricing no longer lays anything out on the system grid'
        );
    }

    /** RULE 6 — the gradient is a ribbon, never a wash. */
    public function test_the_gradient_is_a_ribbon(): void
    {
        $system = $this->system();

        $this->assertMatchesRegularExpression(
            '/\.sy-ribbon::before\s*\{[^}]*height:\s*3px/s',
            $system,
            'rule 6: the ribbon is no longer a 3px edge. Thicker than a marker is a wash.'
        );

        $this->assertStringContainsString(
            'sy-ribbon',
            $this->markup(resource_path('views/public/pricing/plans.blade.php')),
            'rule 6: nothing on /pricing carries the gradient ribbon, so nothing is marked'
        );

        // Gradient-clipped text is coloured text wearing a hat.
        $this->assertStringNotContainsString(
            'grad-text',
            $this->markup(resource_path('views/public/pricing/plans.blade.php')),
            'rules 1 and 6: the gradient is clipped to text again'
        );
    }

    /** RULE 7 — icons are ink. */
    public function test_icons_are_not_coloured(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.sy-ico\s*\{[^}]*color:\s*var\(--sy-ink-3\)/s',
            $this->system(),
            'the system lost its icon colour'
        );

        $offenders = [];

        foreach ($this->convertedViews() as $path) {
            $short = str_replace(resource_path('views/'), '', $path);

            // An <i> carrying a hue, or a tinted ring/fill around one.
            preg_match_all('/<i\b[^>]*class="([^"]*)"/', $this->markup($path), $icons);

            foreach ($icons[1] as $cls) {
                if (preg_match('/\b(?:text|ring|bg)-(?:blue|amber|emerald|cyan|purple|rose|red|green)-\d{2,3}/', $cls, $m)) {
                    $offenders[] = "{$short}  <i class=\"…{$m[0]}…\">";
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Rule 7: icons are ink.\n\n"
            . "The page had emerald ticks, amber bolts, cyan arrows and blue stars, each a\n"
            . "different hue for no reason a reader could name. Use .sy-ico, or let the icon\n"
            . "inherit inside a .sy-cell.\n\n%d found:\n  %s",
            count($offenders),
            implode("\n  ", $offenders)
        ));
    }

    /**
     * And the scan has to be reading views that exist.
     *
     * Every assertion above passes against an empty set.
     */
    public function test_the_scan_reads_the_converted_views(): void
    {
        $views = $this->convertedViews();

        $this->assertNotEmpty($views, 'no pricing views found; this test is reading nothing');

        $seen = 0;
        foreach ($views as $path) {
            $seen += substr_count($this->markup($path), 'sy-');
        }

        $this->assertGreaterThan(
            20,
            $seen,
            'the pricing views barely mention the system; either they were reverted or '
            . 'this test is no longer reading them'
        );
    }
}
