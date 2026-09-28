<?php

/**
 * Every element an ordering page reaches for must exist on that page.
 *
 * ---- Why this guard exists ---------------------------------------------
 *
 * On 30 June 2026 the coupons/GST commit removed `<div id="doneLines">`
 * from the restaurant's confirmation panel and left the `lines('doneLines')`
 * call that fills it. `getElementById` returned null, the next line threw,
 * and the throw landed inside `showDone()` -- which runs AFTER the order has
 * already been created.
 *
 * So for three months every restaurant order did this: the order was saved,
 * the kitchen was notified, and the guest sat looking at a button that said
 * "Placing..." and never changed. No reference, no status, no WhatsApp
 * handoff, no way to tell whether to order again. Nothing failed loudly
 * enough for anyone to see it; it was one silent TypeError per order.
 *
 * A test would have caught the one id. This catches the next one, on the
 * three pages where getting it wrong means an order the customer never
 * learns about.
 *
 * ---- What it checks ----------------------------------------------------
 *
 * For each ordering page: every `getElementById('literal')` in the page or
 * in a partial it includes must have a matching `id="literal"` somewhere in
 * that same set of files.
 *
 * Direct calls are not enough on their own. The bug above went through a
 * HELPER -- `lines('doneLines')`, which does the getElementById inside with
 * a variable -- so a guard that only reads direct calls would have watched
 * it ship. Helpers that take an element id are therefore listed explicitly
 * in HELPERS below and their literal arguments checked the same way. The
 * list is short and adding to it is the price of writing another one.
 *
 * Only string literals are checked; a computed id is invisible to this and
 * always was.
 *
 * Run: php scripts/check-menu-dom-ids.php
 */

$root = dirname(__DIR__);
$viewRoot = $root.'/resources/views';

/** The pages where a missing element means a lost order. */
const PAGES = [
    'common/restaurant-menu.blade.php',
    'common/store-menu.blade.php',
    'common/service-booking.blade.php',
];

/**
 * Ids created by script at runtime rather than written in markup, so they
 * are legitimately absent. Each one needs a reason, because "add it to the
 * allowlist" is how a guard stops guarding.
 */
const CREATED_AT_RUNTIME = [
    // e.g. 'mpp-leaflet-css' => 'injected <link> for the map library',
];

/**
 * Page helpers whose argument is an element id. These are the indirect
 * route the 30 June bug took, so they are checked exactly like a direct
 * getElementById call.
 */
const HELPERS = [
    'lines',          // fills a container with the cart's line items
    'renderBill',     // takes (breakdownId, totalId)
];

/** Turn `@include('a.b.c')` into a view path, when it resolves to a file. */
function includedFiles(string $source, string $viewRoot): array
{
    if (! preg_match_all("/@include\(\s*'([a-zA-Z0-9_.\-]+)'/", $source, $m)) {
        return [];
    }

    $files = [];
    foreach (array_unique($m[1]) as $view) {
        $path = $viewRoot.'/'.str_replace('.', '/', $view).'.blade.php';
        if (is_file($path)) {
            $files[] = $path;
        }
    }

    return $files;
}

$failures = [];
$checked = 0;

foreach (PAGES as $page) {
    $path = $viewRoot.'/'.$page;
    if (! is_file($path)) {
        continue;
    }

    $source = file_get_contents($path);

    // The page plus one level of its own partials: that is the unit a
    // browser actually assembles, so it is the unit to check against.
    $haystack = $source;
    foreach (includedFiles($source, $viewRoot) as $included) {
        $haystack .= "\n".file_get_contents($included);
    }

    $ids = [];

    preg_match_all("/getElementById\(\s*'([a-zA-Z0-9_:\-]+)'\s*\)/", $haystack, $m);
    $ids = array_merge($ids, $m[1]);

    // The indirect route: every literal argument to a helper that takes an
    // element id is an element id.
    foreach (HELPERS as $helper) {
        preg_match_all(
            '/\b'.preg_quote($helper, '/')."\\(([^)]*)\\)/",
            $haystack,
            $calls
        );
        foreach ($calls[1] as $args) {
            // Only the leading string arguments are ids. Anything from the
            // first `{` on is an options object, whose values are labels
            // and figures -- `tax_label: 'Tax'` is not an element.
            $brace = strpos($args, '{');
            $leading = $brace === false ? $args : substr($args, 0, $brace);

            if (preg_match_all("/'([a-zA-Z0-9_:\-]+)'/", $leading, $literals)) {
                $ids = array_merge($ids, $literals[1]);
            }
        }
    }

    foreach (array_unique($ids) as $id) {
        $checked++;
        if (array_key_exists($id, CREATED_AT_RUNTIME)) {
            continue;
        }
        if (! str_contains($haystack, 'id="'.$id.'"')) {
            $failures[] = $page.' reaches for #'.$id.', which nothing on the page defines.';
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "check-menu-dom-ids: FAILED\n\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '  '.$failure."\n");
    }
    fwrite(STDERR, "\n  An ordering page that reaches for a missing element throws mid-order.\n");
    fwrite(STDERR, "  Add the element, or drop the call that wants it.\n");
    exit(1);
}

echo 'check-menu-dom-ids: ok — '.$checked.' element reference(s) across '
    .count(PAGES)." ordering page(s), all defined.\n";
