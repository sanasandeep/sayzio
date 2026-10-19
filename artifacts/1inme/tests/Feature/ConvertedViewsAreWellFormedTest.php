<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The views converted to the shared page header still close every div.
 *
 * This exists because of a specific mistake. Swapping a hand-built header for
 * the shared hero means cutting a block of markup out of a view, and the cut
 * was made with a string search for "    </div>\n". That substring also occurs
 * inside "        </div>\n" -- the eight-space indented closing tag one level
 * further in -- so the slice ended one tag early and left the outer </div>
 * orphaned.
 *
 * Blade compiled it. PHPUnit passed it. BladeViewsCompileTest passed it,
 * because an unbalanced div is valid Blade and valid-enough HTML. What it
 * actually did was close the layout's content wrapper early, so the page
 * chrome collapsed to a narrow column and the footer floated beside it. It
 * shipped, and Sana found it by looking at the page.
 *
 * A strict div count is not safe for every Blade file -- a template can open a
 * div in one @if branch and close it in another -- so this guards only the
 * views converted in this run, each verified balanced when it was added.
 */
class ConvertedViewsAreWellFormedTest extends TestCase
{
    private const CONVERTED = [
        'views/user/links/create.blade.php',
        'views/user/links/teardown/create.blade.php',
        'views/user/links/teardown/show.blade.php',
        'views/user/social-proofs/index.blade.php',
        'views/user/pixels/index.blade.php',
        'views/user/followers/index.blade.php',
        'views/user/identifiers/index.blade.php',
        'views/user/notifications/index.blade.php',
        'views/user/posts/index.blade.php',
        'views/user/referrals/index.blade.php',
    ];

    public function test_every_converted_view_opens_and_closes_the_same_number_of_divs(): void
    {
        $unbalanced = [];

        foreach (self::CONVERTED as $view) {
            $path = resource_path($view);
            if (! is_file($path)) {
                $unbalanced[] = $view.' (file missing)';
                continue;
            }

            // Comments first. A comment that talks about markup is not markup,
            // and counting one as such is its own small version of this bug.
            $src = preg_replace('#\{\{--[\s\S]*?--\}\}#', '', file_get_contents($path));

            $open  = preg_match_all('/<div\b/i', $src);
            $close = preg_match_all('#</div>#i', $src);

            if ($open !== $close) {
                $unbalanced[] = sprintf('%s (%d open, %d close)', $view, $open, $close);
            }
        }

        sort($unbalanced);
        $this->assertSame([], $unbalanced, 'these views do not close every div they open, which silently closes the layout wrapper early: '.implode('; ', $unbalanced));
    }
}
