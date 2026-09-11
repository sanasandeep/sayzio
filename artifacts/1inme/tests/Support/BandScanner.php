<?php

namespace Tests\Support;

/**
 * Finding the full-bleed bands in a Blade view, and the views a page is made
 * of.
 *
 * Shared because the same rule now applies to the homepage and to the 35
 * marketing pages, and the rule is only worth having if both are judged by the
 * same code. The homepage's own guard and the marketing one differ in what
 * they enumerate, not in what counts as a band.
 */
trait BandScanner
{
    /**
     * A band is a <section> that spans the page, recognised by its vertical
     * rhythm.
     *
     * The first version of this wanted `py-(16|20|24)` AND Tailwind's
     * `relative`, and two live homepage bands walked straight through it. Then
     * the marketing pages turned out to use `pb-20` and `pb-24` with no `py-`
     * at all, and 90 more bands were invisible to it. Any vertical padding
     * step from 10 up, on any of the three axes, which is what every band on
     * this site uses and what no card inside one does.
     *
     * @return list<array{0:string,1:string,2:int}> [tag, classes, line]
     */
    protected function bandsIn(string $source): array
    {
        $bands = [];

        // Attribute-aware, because `[^>]*` is not.
        //
        // features.blade.php has a <section> whose x-data holds
        // `fn($c) => [...]`, and the `>` in that arrow ends the match early --
        // the tag is truncated before `class=`, the band looks like it has no
        // classes, and it is skipped silently. One band on a live page went
        // undeclared for exactly that reason. Quoted values are consumed
        // whole, so a `>` inside one is just a character.
        preg_match_all('/<section\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i', $source, $tags, PREG_OFFSET_CAPTURE);

        foreach ($tags[0] as [$tag, $offset]) {
            // Single or double quotes: an earlier pattern accepted only
            // double, so one single-quoted list would have been invisible
            // rather than reported.
            if (! preg_match('/\bclass=(["\'])(.*?)\1/is', $tag, $c)) {
                continue;
            }

            $classes = $c[2];

            // Any vertical padding step from 8 up. The floor was 16, then 10,
            // and each time a live band sat just under it: the trust band at
            // py-14, the marketing pages at pb-20 with no py- at all, and the
            // contact page at pb-8. The browser's own definition -- full width,
            // over 120px tall -- is the one that matters, and 8 is where the
            // static matcher stops disagreeing with it.
            if (! preg_match('/(?<![\w:-])(?:py|pt|pb)-([89]|[1-9]\d)\b/', $classes)) {
                continue;
            }

            $bands[] = [$tag, $classes, substr_count(substr($source, 0, $offset), "\n") + 1];
        }

        return $bands;
    }

    /**
     * Three declarations, and every band makes exactly one:
     *
     *   sec-rule    sits on the page's ground and draws the hairline
     *   sec-ground  paints a ground of its own; the colour change separates
     *   sec-first   nothing above it -- it is the first band on the page
     *
     * `sec-first` exists because the third case cannot be inferred. It was,
     * for a while: "the first <section> in a view that @extends a layout".
     * That reads well and is wrong -- the homepage's hero is in a partial, not
     * in home.blade.php, so the inference exempted nothing and demanded a
     * hairline directly under the site header.
     */
    protected function declaresSeparator(string $classes): bool
    {
        return (bool) preg_match('/(?<![\w-])sec-(rule|ground|first)(?![\w-])/', $classes);
    }

    /**
     * Every view reachable from these roots by following @include.
     *
     * Globbing a directory instead is what let six homepage fragments and a
     * whole partial directory go unchecked; and globbing more of them swings
     * the other way and drags in pages that are not this test's problem.
     * Following the includes is how the page itself finds them.
     *
     * @param  list<string>  $roots
     * @return list<string>
     */
    protected function viewsReachableFrom(array $roots): array
    {
        $seen = [];
        $queue = $roots;

        while ($queue) {
            $path = array_shift($queue);

            if (! is_file($path) || isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;

            preg_match_all(
                '/@include(?:If|When|Unless|First)?\s*\(\s*(?:[^)\'"]*?,\s*)?([\'"])([a-z0-9_.\-]+)\1/i',
                (string) file_get_contents($path),
                $m
            );

            foreach ($m[2] as $view) {
                $queue[] = resource_path('views/' . str_replace('.', '/', $view) . '.blade.php');
            }
        }

        $paths = array_keys($seen);
        sort($paths);

        return $paths;
    }

    /**
     * Is this view a PAGE rather than a partial?
     *
     * It matters because the first band on a page has nothing above it to
     * divide from, and that is the only exemption the rule allows. It used to
     * be an id in a list -- `'hero' => 'the first band on the page'` -- which
     * does not scale to 35 marketing heroes and, worse, invites the list to
     * grow back with entries that are claims rather than facts. Being first in
     * a page view is a fact this code can check.
     */
    protected function isPageView(string $path): bool
    {
        $source = (string) file_get_contents($path);

        return str_contains($source, '@extends(')
            || str_ends_with($path, '/views/home.blade.php');
    }
}
