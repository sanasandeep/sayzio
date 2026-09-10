<?php

/**
 * Regression guard: an @extends or @include naming a view that does not exist.
 *
 * Background: Blade resolves these at RENDER time. A template that extends a
 * layout which was renamed, or was never there, looks completely fine in the
 * editor, compiles fine, and passes every static check -- then throws
 * "View [x] not found" the first time somebody opens the page. Nothing in the
 * controller hints at it, because the controller's view name is correct; it is
 * the template's own reference that dangles.
 *
 * Three pages were doing exactly this in production, all on the same typo:
 *
 *     user/links/insurance/dashboard.blade.php  @extends('layouts.user')
 *     user/links/insurance/settings.blade.php   @extends('layouts.user')
 *     user/roadmap/triage.blade.php             @extends('layouts.app')
 *
 * There is no resources/views/layouts/ directory at all -- the layout is
 * `user.layouts.app`, which the other 45 pages in those folders extend. Link
 * Health (/user/insurance) had been answering 500 to every visitor.
 *
 * What is checked: the target of every @extends and every @include /
 * @includeIf / @includeWhen / @includeUnless / @each written as a string
 * literal, across all of resources/views. A target resolves if a matching
 * .blade.php or .php file exists, or if it is a namespaced/package view
 * (`vendor::name`), or a component under components/.
 *
 * Not checked, by design: a target built at runtime (@include($partial),
 * @include('prefix.'.$type)). Those cannot be resolved without running the
 * app, and guessing produces false alarms -- a guard that cries wolf is a
 * guard somebody deletes. @includeIf is still checked: it swallows a missing
 * view at runtime, which makes a typo there permanently invisible, so a
 * dangling name in one is worth knowing about even though it will not throw.
 *
 * Adding a legit exception: append to ALLOWLIST below with a reason.
 *
 * Usage:
 *   php scripts/check-view-references.php
 *
 * Exit codes:
 *   0  every literal @extends/@include target resolves to a real view
 *   1  at least one dangling reference
 */

declare(strict_types=1);

/**
 * View names that legitimately do not resolve on disk (supplied by a package
 * at runtime, say). Key is the view name, value is the reason.
 *
 * @var array<string, string>
 */
const ALLOWLIST = [];

const VIEW_ROOT = __DIR__ . '/../resources/views';

/** Directives whose first string argument is a view name. */
const DIRECTIVES = ['extends', 'include', 'includeIf', 'includeWhen', 'includeUnless', 'includeFirst', 'each'];

/**
 * @return list<string>
 */
function bladeFiles(string $root): array
{
    if (! is_dir($root)) {
        return [];
    }

    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/** Does this view name resolve to a file under resources/views? */
function resolves(string $viewName): bool
{
    // Package / namespaced views (`mail::message`, `pagination::bootstrap-4`)
    // are supplied by whatever registered the namespace, not by this tree.
    if (str_contains($viewName, '::')) {
        return true;
    }

    $base = VIEW_ROOT . '/' . str_replace('.', '/', $viewName);

    foreach (['.blade.php', '.php', '.css', '.js'] as $ext) {
        if (is_file($base . $ext)) {
            return true;
        }
    }

    return false;
}

/**
 * The `@each` directive takes the PARTIAL as its first argument and an
 * "empty view" as an optional fourth; both are view names, but only the first
 * is reliably a literal, so that is what this reads.
 */
$dangling = [];
$checked = 0;

$directivePattern = '/@(' . implode('|', DIRECTIVES) . ')\s*\(\s*([\'"])([^\'"]+)\2/';

foreach (bladeFiles(VIEW_ROOT) as $file) {
    $src = file_get_contents($file);
    if ($src === false) {
        continue;
    }

    // Blade comments are not compiled, so a name inside one cannot dangle.
    $src = preg_replace('/\{\{--[\s\S]*?--\}\}/', '', $src) ?? $src;

    if (! preg_match_all($directivePattern, $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($matches as $m) {
        $directive = $m[1][0];
        $viewName = $m[3][0];
        $offset = $m[0][1];

        $checked++;

        if (resolves($viewName) || array_key_exists($viewName, ALLOWLIST)) {
            continue;
        }

        $line = substr_count(substr($src, 0, $offset), "\n") + 1;
        $rel = ltrim(str_replace(realpath(__DIR__ . '/..') ?: '', '', realpath($file) ?: $file), '/');

        $dangling[] = [
            'view'      => $viewName,
            'directive' => $directive,
            'site'      => $rel . ':' . $line,
        ];
    }
}

if ($dangling === []) {
    fwrite(STDOUT, "\u{2713} view reference guard passed \u{2014} {$checked} literal @extends/@include targets, all resolve.\n");
    exit(0);
}

fwrite(STDERR, "\n");
fwrite(STDERR, str_repeat('=', 78) . "\n");
fwrite(STDERR, 'DANGLING VIEW REFERENCE: ' . count($dangling) . " @extends/@include target(s) name a view that does not exist.\n");
fwrite(STDERR, str_repeat('=', 78) . "\n\n");

fwrite(STDERR, "Blade resolves these at render time, so each one below is a page that\n");
fwrite(STDERR, "throws \"View [...] not found\" the first time somebody opens it.\n\n");

foreach ($dangling as $d) {
    fwrite(STDERR, sprintf("  @%-14s %s\n", $d['directive'], $d['view']));
    fwrite(STDERR, "      {$d['site']}\n");

    // Point at the nearest real view, which is nearly always the intended one.
    $leaf = substr($d['view'], (int) strrpos('.' . $d['view'], '.'));
    $suggestions = [];
    foreach (bladeFiles(VIEW_ROOT) as $candidate) {
        $name = str_replace('/', '.', substr(
            $candidate,
            strlen(VIEW_ROOT) + 1,
            -strlen('.blade.php')
        ));
        if (str_ends_with($name, $leaf) && $name !== $d['view']) {
            $suggestions[] = $name;
        }
        if (count($suggestions) >= 3) {
            break;
        }
    }

    if ($suggestions !== []) {
        fwrite(STDERR, '      did you mean: ' . implode(', ', $suggestions) . "\n");
    }

    fwrite(STDERR, "\n");
}

exit(1);
