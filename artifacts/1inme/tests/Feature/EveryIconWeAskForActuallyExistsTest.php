<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A Font Awesome Pro icon name in a Free build renders nothing at all.
 * No error, no fallback, no console warning -- just an empty box where an
 * icon should be, which is the kind of thing that survives review and
 * ships.
 *
 * It shipped here: `fa-calendar-star` went into the sidebar's My Events
 * item and left a blank space next to the label. The icon looked right in
 * the markup and the page rendered fine; there was simply nothing to see.
 *
 * So this walks every icon class in the user views and checks it against
 * the Font Awesome build actually on disk.
 *
 * On the baseline: eight Pro-only names were already in use when this
 * test was written, in pages unrelated to the change that prompted it.
 * They are listed rather than fixed, because picking a replacement for
 * someone else's icon is a design decision and not one to make in a test
 * file. The list is here so the count can only go down -- a new Pro name
 * fails immediately, and burning these eight down is a separate, visible
 * piece of work.
 */
class EveryIconWeAskForActuallyExistsTest extends TestCase
{
    /**
     * Pro-only icon names already in the user views. Fix one, remove it
     * from here. Do not add to this list to make a build pass.
     */
    private const KNOWN_MISSING = [
        'fa-arrow-down-to-bracket',  // connected-apps/index
        'fa-badge-check',            // dialer/index
        'fa-grid-2',                 // creator-profile/edit
        'fa-shield-check',           // account/two-factor, forms, inbox
        'fa-shield-exclamation',     // files/scan-flagged, forms, inbox
        'fa-shield-xmark',           // account/two-factor
        'fa-sparkles',               // coming-soon, contacts, dashboard
        'fa-user-heart',             // links/partials/analytics-tabs
    ];

    /**
     * Sizing, animation and style classes share the `fa-` prefix but name
     * no glyph, so they are not icons and must not be looked up.
     */
    private const UTILITY = '~^fa-(solid|regular|light|thin|duotone|brands|sharp|fw|lg|sm|xs|xl|2xl|\d+x'
        . '|spin|spin-pulse|spin-reverse|pulse|border|pull-left|pull-right'
        . '|rotate-(90|180|270|by)|flip-(horizontal|vertical|both)'
        . '|stack|stack-1x|stack-2x|inverse|beat|beat-fade|fade|bounce|shake|li|ul|layers|swap-opacity)$~';

    /** Every glyph the bundled Font Awesome build can actually draw. */
    private function availableIcons(): array
    {
        $sheets = glob(public_path('css/vendor/fontawesome-free-*/css/all.min.css'));
        $this->assertNotEmpty($sheets, 'no bundled Font Awesome stylesheet found');

        $css = file_get_contents($sheets[0]);
        $this->assertNotFalse($css);

        preg_match_all('~\.(fa-[a-z0-9-]+):before~', $css, $m);
        $icons = array_unique($m[1]);
        sort($icons);

        // A build with almost nothing in it would make this test pass by
        // accident, so check the sheet looks like Font Awesome first.
        $this->assertGreaterThan(1000, count($icons), 'the Font Awesome sheet parsed to too few icons to trust');

        return $icons;
    }

    /** Every icon name asked for in a class attribute across the user views. */
    private function requestedIcons(): array
    {
        $dir = resource_path('views/user');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        $used = [];

        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all('~class="([^"]*)"~', file_get_contents($file->getPathname()), $m);

            foreach ($m[1] as $attr) {
                foreach (preg_split('~\s+~', $attr, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                    // A whole, clean token only. Class attributes here also
                    // carry JavaScript and Blade interpolation, which yields
                    // scraps like "fa-camera'," and "fa-{{" -- those are not
                    // icon names and looking them up would be noise.
                    if (!preg_match('~^fa-[a-z0-9]+(?:-[a-z0-9]+)*$~', $token)) {
                        continue;
                    }
                    if (preg_match(self::UTILITY, $token)) {
                        continue;
                    }
                    $used[$token] = true;
                }
            }
        }

        $used = array_keys($used);
        sort($used);

        $this->assertGreaterThan(100, count($used), 'parsed too few icon usages to trust');

        return $used;
    }

    public function test_no_icon_in_the_user_views_is_missing_from_the_build(): void
    {
        $missing = array_values(array_diff($this->requestedIcons(), $this->availableIcons()));
        sort($missing);

        $expected = self::KNOWN_MISSING;
        sort($expected);

        $this->assertSame(
            $expected,
            $missing,
            "A Pro-only Font Awesome name renders as blank space in this Free build.\n"
            . "If you added one, pick a Free equivalent. If you fixed one, remove it from KNOWN_MISSING."
        );
    }

    public function test_the_sidebar_icons_all_exist(): void
    {
        // The layout is where a blank icon is most visible and least
        // likely to be noticed in review -- it renders on every page.
        $available = $this->availableIcons();

        preg_match_all(
            '~class="[^"]*\bfas? (fa-[a-z0-9-]+)~',
            file_get_contents(resource_path('views/user/layouts/app.blade.php')),
            $m
        );

        $this->assertNotEmpty($m[1], 'no icons found in the layout');

        foreach (array_unique($m[1]) as $icon) {
            $this->assertContains($icon, $available, "The sidebar asks for {$icon}, which this build cannot draw");
        }
    }
}
