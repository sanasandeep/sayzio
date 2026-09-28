<?php

/**
 * Regression guard: a public page that renders biolink blocks without the
 * stylesheet those blocks are written in.
 *
 * Background: every block in resources/views/common/blocks/* is written in
 * Tailwind utility classes and FontAwesome icon classes. Nothing in the block
 * markup declares that -- the classes are just strings, and a page missing
 * Tailwind renders them as raw HTML rather than erroring.
 *
 * That is exactly what happened to the menus. The block loop used to live
 * inline in common/biolink.blade.php, which loads Tailwind in its own head.
 * Moving the loop into common/partials/biolink-block-list.blade.php let the
 * restaurant and store menus render blocks too -- and those pages have no
 * @vite. So from that day every block on a menu page rendered unstyled: a
 * Heading block at the browser's default h2 (42px with 35px margins instead
 * of 24px with none), a full-width button collapsed to the width of its own
 * label, blank space where icons go.
 *
 * Nothing failed. No test broke, no route 500'd, the blocks were all present
 * in the DOM. The only signal was a visitor looking at the page, which is the
 * worst place for a bug to surface and the reason this guard exists.
 *
 * The rule: a Blade template that includes `common.partials.biolink-block-list`
 * must also load the block assets, by one of two means --
 *
 *   1. including `common.partials.biolink-block-assets` (what a menu does), or
 *   2. calling @vite with resources/css/app.css in its own head, AND including
 *      the fontawesome partial (what common/biolink.blade.php has always done;
 *      including the assets partial too would double-load them).
 *
 * A template that includes the list only inside another template -- i.e. is
 * itself a partial with no <head> of its own -- is checked against whichever
 * page includes IT, walked up to one enclosing level. That covers the case of
 * a future page type factoring its block section into its own partial.
 *
 * Usage:  php scripts/check-block-assets.php
 * Exit 0 = clean, 1 = at least one page renders blocks with nothing to style
 * them.
 */

$root = dirname(__DIR__);
$viewRoot = $root.'/resources/views';

if (! is_dir($viewRoot)) {
    fwrite(STDERR, "check-block-assets: no resources/views at {$root}\n");
    exit(1);
}

const LIST_PARTIAL = 'common.partials.biolink-block-list';
const ASSETS_PARTIAL = 'common.partials.biolink-block-assets';

/** Every .blade.php under resources/views, keyed by dotted view name. */
function allViews(string $viewRoot): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($viewRoot) + 1);
        $name = str_replace(['/', '.blade.php'], ['.', ''], $rel);
        $out[$name] = $file->getPathname();
    }

    ksort($out);

    return $out;
}

/**
 * View names this template @includes. Matches @include, @includeIf,
 * @includeWhen and @includeFirst, with either quote style, and normalises
 * slash-separated names to dots so 'common/partials/x' and 'common.partials.x'
 * are the same edge.
 */
function includedViews(string $source): array
{
    $names = [];

    if (preg_match_all(
        '/@include(?:If|When|Unless|First)?\s*\(\s*\[?\s*[\'"]([^\'"]+)[\'"]/',
        $source,
        $m
    )) {
        foreach ($m[1] as $raw) {
            $names[] = str_replace('/', '.', $raw);
        }
    }

    return array_values(array_unique($names));
}

/** Does this source load Tailwind itself? */
function loadsAppCss(string $source): bool
{
    return (bool) preg_match('/@vite\s*\(.*app\.css/s', $source);
}

/** Does this source pull in the FontAwesome partial itself? */
function loadsFontAwesome(string $source): bool
{
    return in_array('common.partials.fontawesome', includedViews($source), true);
}

$views = allViews($viewRoot);
$sources = [];
foreach ($views as $name => $path) {
    $sources[$name] = file_get_contents($path) ?: '';
}

// Who includes whom, so a partial can be judged by its host page.
$includedBy = [];
foreach ($sources as $name => $source) {
    foreach (includedViews($source) as $child) {
        $includedBy[$child][] = $name;
    }
}

/** A template satisfies the requirement on its own. */
function satisfiesLocally(string $source): bool
{
    $includes = includedViews($source);

    if (in_array(ASSETS_PARTIAL, $includes, true)) {
        return true;
    }

    // The page that has always carried these in its own head.
    return loadsAppCss($source) && loadsFontAwesome($source);
}

$offenders = [];

foreach ($sources as $name => $source) {
    if ($name === LIST_PARTIAL || $name === ASSETS_PARTIAL) {
        continue;
    }
    if (! in_array(LIST_PARTIAL, includedViews($source), true)) {
        continue;
    }
    if (satisfiesLocally($source)) {
        continue;
    }

    // This template renders blocks but loads nothing. If it is itself only
    // ever included by pages that DO load the assets, it is a section partial
    // and the page above it is responsible -- that is fine.
    $hosts = $includedBy[$name] ?? [];
    if ($hosts !== []) {
        $unsatisfiedHosts = array_values(array_filter(
            $hosts,
            static fn ($h) => ! satisfiesLocally($sources[$h] ?? '')
        ));
        if ($unsatisfiedHosts === []) {
            continue;
        }
        $offenders[$name] = $unsatisfiedHosts;
        continue;
    }

    $offenders[$name] = [];
}

if ($offenders === []) {
    $rendering = 0;
    foreach ($sources as $name => $source) {
        if ($name !== LIST_PARTIAL && in_array(LIST_PARTIAL, includedViews($source), true)) {
            $rendering++;
        }
    }
    echo "check-block-assets: ok — {$rendering} template(s) render biolink blocks, all with the stylesheet they are written in.\n";
    exit(0);
}

fwrite(STDERR, "\n".str_repeat('=', 78)."\n");
fwrite(STDERR, 'BLOCKS WITHOUT STYLES: '.count($offenders)." template(s) render biolink blocks\n");
fwrite(STDERR, "with nothing loaded to style them.\n");
fwrite(STDERR, str_repeat('=', 78)."\n\n");

fwrite(STDERR, "Blocks are written in Tailwind utility classes and FontAwesome icon\n");
fwrite(STDERR, "classes. A page without them still renders every block — as raw HTML.\n");
fwrite(STDERR, "A Heading comes out at the browser's default h2, a full-width button\n");
fwrite(STDERR, "shrinks to the width of its label, icons are blank. Nothing errors, so\n");
fwrite(STDERR, "the only way to find out is for a visitor to look at the page.\n\n");

foreach ($offenders as $name => $hosts) {
    fwrite(STDERR, "  {$name}\n");
    fwrite(STDERR, '      includes '.LIST_PARTIAL."\n");
    if ($hosts !== []) {
        fwrite(STDERR, "      and is included by page(s) that also load nothing:\n");
        foreach ($hosts as $h) {
            fwrite(STDERR, "          {$h}\n");
        }
    }
    fwrite(STDERR, "\n");
}

fwrite(STDERR, "Fix: add this to that page's <head> —\n\n");
fwrite(STDERR, "      @include('".ASSETS_PARTIAL."')\n\n");
fwrite(STDERR, "It must be in the HEAD, not beside the blocks: a stylesheet linked from\n");
fwrite(STDERR, "the body repaints the blocks mid-scroll, and these pages are read on\n");
fwrite(STDERR, "phones on bad connections.\n\n");

exit(1);
