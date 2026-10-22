<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A floating surface has to BE the ground it draws on.
 *
 * --bg-card, --bg-glass and --bg-sidebar are glass tokens, for surfaces
 * that sit ON an opaque page. In the default dark theme they are 4%, 3%
 * and 2% white. Painted onto a dropdown, tooltip, popover, drawer or
 * toast, they cover nothing: the page shows straight through and the
 * two layers of text overlap.
 *
 * WHY NOBODY NOTICED FOR SO LONG
 *
 * Only the default dark theme is affected. html.light-mode defines all
 * three as #ffffff and html.aurora as opaque near-blacks, so two of the
 * three themes render these overlays correctly. The broken one happens
 * to be the default.
 *
 * --bg-dropdown is the floating-surface token: #12141c, #ffffff,
 * #14131d, #ffffff across the four theme roots. Opaque everywhere.
 *
 * This test is a scan plus an allowlist, in the same spirit as the
 * em-dash guard. The allowlist is the interesting half: it is where a
 * deliberate translucent overlay gets written down and justified,
 * rather than being indistinguishable from a mistake.
 */
class AnOverlayHasToCoverWhatIsBehindItTest extends TestCase
{
    /**
     * Rules that legitimately keep a glass token.
     *
     * Each entry is a selector substring plus the reason. A new entry
     * here should be an argued exception, not a way to quiet the test.
     */
    private const ALLOWED = [
        // position: sticky on the panel itself; the absolute inside it
        // is a child, and the panel is meant to read as glass ON the
        // page rather than to cover anything.
        '.palette-panel' => 'a sticky page panel, not an overlay',
    ];

    /** Tokens that are translucent in the default dark theme. */
    private const GLASS = ['--bg-card', '--bg-glass', '--bg-sidebar'];

    public function test_no_floating_overlay_is_painted_with_a_glass_token(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $source = (string) file_get_contents($file);
            $short  = str_replace(resource_path('views') . '/', '', $file);

            foreach ($this->suspectBlocks($source) as [$selector, $token]) {
                foreach (self::ALLOWED as $needle => $_why) {
                    if (str_contains($selector, $needle)) {
                        continue 2;
                    }
                }

                $offenders[] = "{$short}  {$selector}  uses {$token}";
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['These overlays are painted with a token that is 2-4% opaque in the',
             'default dark theme, so they do not cover the page behind them.',
             'Use --bg-dropdown, or add an argued entry to self::ALLOWED:',
             ''],
            $offenders
        )));
    }

    public function test_the_scan_can_actually_find_an_offender(): void
    {
        // The control. Without it the assertion above passes just as
        // happily when the block parser silently matches nothing, which
        // is the failure mode a scan-based guard actually has.
        $planted = <<<'CSS'
        <style>
        .some-popover {
            position: fixed;
            z-index: 50;
            background: var(--bg-card);
        }
        </style>
        CSS;

        $found = $this->suspectBlocks($planted);

        $this->assertCount(1, $found, 'the scan missed a planted offender, so it proves nothing');
        $this->assertStringContainsString('.some-popover', $found[0][0]);
        $this->assertSame('--bg-card', $found[0][1]);
    }

    public function test_bg_dropdown_is_opaque_in_every_theme(): void
    {
        // The fix depends on this. If a theme ever redefines
        // --bg-dropdown as an rgba(), every overlay above silently goes
        // back to being see-through in that theme only.
        $themes = (string) file_get_contents(
            resource_path('views/common/partials/theme-styles.blade.php')
        );

        preg_match_all('/--bg-dropdown:\s*([^;]+);/', $themes, $m);

        $this->assertGreaterThanOrEqual(4, count($m[1]),
            'theme-styles has four theme roots; --bg-dropdown should be defined in each');

        foreach ($m[1] as $value) {
            $this->assertDoesNotMatchRegularExpression('/rgba?\(/', trim($value),
                "--bg-dropdown is translucent somewhere: {$value}");
        }
    }

    /**
     * CSS blocks that both position themselves out of flow and paint
     * with a glass token.
     *
     * @return array<int, array{0: string, 1: string}> [selector, token]
     */
    private function suspectBlocks(string $source): array
    {
        $out = [];
        $glass = implode('|', array_map(
            fn (string $t) => preg_quote($t, '/'),
            self::GLASS
        ));

        $scan = function (string $chunk, string $label) use (&$out, $glass): void {
            if (! preg_match('/position\s*:\s*(fixed|absolute)/', $chunk)) {
                return;
            }
            if (! preg_match('/background\s*:[^;}]*var\(\s*(' . $glass . ')\s*\)/', $chunk, $hit)) {
                return;
            }
            $out[] = [$label, $hit[1]];
        };

        foreach (explode('}', $source) as $block) {
            $selector = trim(explode('{', $block)[0]);
            $selector = trim((string) strrchr("\n" . $selector, "\n"));
            $scan($block, $selector !== '' ? $selector : '(unnamed rule)');
        }

        foreach (preg_split('/style="/', $source) as $i => $part) {
            if ($i === 0) {
                continue;
            }
            $scan(explode('"', $part)[0], '(inline style attribute)');
        }

        return $out;
    }

    /** @return array<int, string> */
    private function bladeFiles(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $files[] = $f->getPathname();
            }
        }
        sort($files);

        // Sanity: this suite is worthless if the walk finds nothing.
        $this->assertGreaterThan(500, count($files), 'the view walk found almost no files');

        return $files;
    }
}
