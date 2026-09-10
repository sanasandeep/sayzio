<?php

/**
 * Regression guard: two controllers rendering the same Blade view name with
 * different data.
 *
 * Background: Blade resolves a view by NAME alone. Nothing ties a template to
 * the controller that renders it, so two features can quietly claim the same
 * name and share one file -- and only one of them can be right about what
 * variables that file may read.
 *
 * That is exactly what happened to Link Verification. Both of these existed:
 *
 *     VerificationController::index()
 *         -> view('user.verification.index', compact('requests', 'biolinks'))
 *     ProfileVerificationController::index()
 *         -> view('user.verification.index', compact('user', 'requests', 'tickTypes'))
 *
 * One set of files sat on disk under those names. Profile verification's
 * version is the one that survived, so all four Link Verification pages
 * answered 500 in production -- the user's request list, the request form,
 * the admin queue and the admin review page -- every one of them dying on a
 * variable it was never passed ("Undefined variable $user"). No test failed,
 * no build broke, and the Settings menu kept linking to it.
 *
 * The fix was to give the two features separate view directories. This guard
 * is what stops the next one.
 *
 * What counts as a collision: the same view name rendered from two different
 * controller CLASSES where one of them omits a variable the template actually
 * READS UNGUARDED. Two call sites in the SAME class are fine -- that is one
 * feature rendering its own template, and its index()/filtered() pair
 * legitimately passes slightly different data. Two classes passing the
 * IDENTICAL variable set are fine too: a shared partial with one honest
 * contract.
 *
 * The unguarded part is what keeps this guard worth having. A template that
 * writes `$upgradePlan ?? null`, `isset($thing)` or `empty($thing)` is saying
 * out loud that the variable is optional, and a caller may omit it -- that is
 * a shared partial with a documented contract, not a collision. A template
 * that writes `$user->name` when a caller never passes `$user` is a 500
 * waiting for the first visitor. Only the second shape fails the build.
 *
 * Detection is deliberately syntactic. It reads `view('name', compact(...))`
 * and `view('name', ['key' => ...])` out of the controller source rather than
 * booting the app, because the collision is a naming fact, not a runtime one,
 * and a guard that needs a database is a guard that gets skipped.
 *
 * Not detected, by design: a view name built at runtime from a variable, and
 * variables a template reads from a view composer or @inject rather than from
 * its controller. Those are rare here and would need a much heavier tool.
 *
 * Adding a legit exception: append to ALLOWLIST below with a reason. Prefer
 * separate view names -- that is the actual fix, and it costs one directory.
 *
 * Usage:
 *   php scripts/check-view-name-collisions.php
 *
 * Exit codes:
 *   0  every view name is rendered with one consistent variable set
 *   1  at least one non-allowlisted collision
 */

declare(strict_types=1);

/**
 * View names that are deliberately rendered with different data by different
 * controllers. Key is the view name, value is the reason, shown in output.
 *
 * @var array<string, string>
 */
const ALLOWLIST = [];

/** Directories scanned for `view(...)` calls. */
const CONTROLLER_ROOTS = [
    __DIR__ . '/../app/Modules',
    __DIR__ . '/../app/Http/Controllers',
];

/**
 * @return list<string> every .php file under the roots that exist
 */
function phpFilesUnder(array $roots): array
{
    $files = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return $files;
}

/**
 * Variable names a `view()` call passes, or null when the second argument is
 * something this guard cannot read statically (a variable, a merge, a method
 * call). Null means "unknown", which is never reported as a collision --
 * guessing would produce false alarms, and a false alarm gets a guard deleted.
 *
 * @return list<string>|null
 */
function variablesFrom(string $argsSource): ?array
{
    $argsSource = trim($argsSource);

    // view('name')  -- no data at all.
    if ($argsSource === '') {
        return [];
    }

    // view('name', compact('a', 'b'))
    if (preg_match('/^compact\(\s*(.*?)\s*\)$/s', $argsSource, $m)) {
        if (! preg_match_all('/[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]/', $m[1], $names)) {
            return null;
        }

        $vars = $names[1];
        sort($vars);

        return $vars;
    }

    // view('name', ['a' => ..., 'b' => ...])  -- top-level keys only.
    if (str_starts_with($argsSource, '[') && str_ends_with($argsSource, ']')) {
        $depth = 0;
        $keys = [];
        $len = strlen($argsSource);
        $segmentStart = 1;

        // Split on commas at depth 1 so nested arrays/calls do not confuse us.
        for ($i = 1; $i < $len - 1; $i++) {
            $c = $argsSource[$i];
            if ($c === '[' || $c === '(') {
                $depth++;
            } elseif ($c === ']' || $c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $keys[] = substr($argsSource, $segmentStart, $i - $segmentStart);
                $segmentStart = $i + 1;
            }
        }
        $keys[] = substr($argsSource, $segmentStart, $len - 1 - $segmentStart);

        $vars = [];
        foreach ($keys as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            if (! preg_match('/^[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]\s*=>/', $pair, $km)) {
                // A spread, a variable key, or a bare value: unreadable.
                return null;
            }
            $vars[] = $km[1];
        }

        sort($vars);

        return $vars;
    }

    return null;
}

/**
 * Balanced-paren extraction of a `view(` call's arguments starting at the
 * offset of its opening paren. Returns null on an unbalanced source.
 */
function argumentsAt(string $src, int $openParen): ?string
{
    $depth = 0;
    $len = strlen($src);

    for ($i = $openParen; $i < $len; $i++) {
        $c = $src[$i];

        // Skip over string literals so a paren inside one does not count.
        if ($c === "'" || $c === '"') {
            $quote = $c;
            for ($i++; $i < $len; $i++) {
                if ($src[$i] === '\\') {
                    $i++;
                    continue;
                }
                if ($src[$i] === $quote) {
                    break;
                }
            }
            continue;
        }

        if ($c === '(') {
            $depth++;
        } elseif ($c === ')') {
            $depth--;
            if ($depth === 0) {
                return substr($src, $openParen + 1, $i - $openParen - 1);
            }
        }
    }

    return null;
}

/** Absolute path of the Blade file a view name resolves to, or null. */
function viewFileFor(string $viewName): ?string
{
    $base = __DIR__ . '/../resources/views/' . str_replace('.', '/', $viewName);

    foreach (['.blade.php', '.php'] as $ext) {
        if (is_file($base . $ext)) {
            return $base . $ext;
        }
    }

    return null;
}

/**
 * Does $viewSource read $var somewhere that would blow up if it were missing?
 *
 * An occurrence inside `?? `, `isset()`, `empty()` or `??=` is the template
 * declaring the variable optional. If every occurrence is one of those, the
 * caller that omits it is honouring the contract, not breaking it.
 */
function readsUnguarded(string $viewSource, string $var): bool
{
    $q = preg_quote($var, '/');

    $total = preg_match_all('/\$' . $q . '\b/', $viewSource);
    if ($total === 0 || $total === false) {
        // The template never reads it. Passing it is harmless; omitting it is
        // harmless too.
        return false;
    }

    $guarded = 0;
    $guarded += (int) preg_match_all('/\$' . $q . '\s*\?\?/', $viewSource);
    $guarded += (int) preg_match_all('/\bisset\s*\(\s*\$' . $q . '\b/', $viewSource);
    $guarded += (int) preg_match_all('/\bempty\s*\(\s*\$' . $q . '\b/', $viewSource);
    $guarded += (int) preg_match_all('/@isset\s*\(\s*\$' . $q . '\b/', $viewSource);

    return $total > $guarded;
}

/** @var array<string, array<string, array{vars: list<string>, sites: list<string>}>> */
$byView = [];

foreach (phpFilesUnder(CONTROLLER_ROOTS) as $file) {
    $src = file_get_contents($file);
    if ($src === false) {
        continue;
    }

    $class = preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $src, $cm)
        ? $cm[1]
        : basename($file, '.php');

    // `view('x'` / `View::make('x'` / `->view('x'` all reduce to this.
    if (! preg_match_all('/\bview\s*\(\s*[\'"]([A-Za-z0-9_.\-]+)[\'"]/', $src, $vm, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($vm[0] as $i => [$matchText, $matchOffset]) {
        $viewName = $vm[1][$i][0];

        $openParen = strpos($src, '(', $matchOffset);
        if ($openParen === false) {
            continue;
        }

        $args = argumentsAt($src, $openParen);
        if ($args === null) {
            continue;
        }

        // Drop the leading view-name argument; what is left is the data.
        $rest = preg_replace('/^\s*[\'"][A-Za-z0-9_.\-]+[\'"]\s*,?/', '', $args, 1);
        $vars = variablesFrom((string) $rest);

        if ($vars === null) {
            continue;
        }

        $line = substr_count(substr($src, 0, $matchOffset), "\n") + 1;
        $key = $class;

        if (! isset($byView[$viewName][$key])) {
            $byView[$viewName][$key] = ['vars' => $vars, 'sites' => []];
        }

        // Within one class, keep the widest set seen: an index() and its
        // filtered sibling are one contract, not two.
        $byView[$viewName][$key]['vars'] = array_values(array_unique(
            array_merge($byView[$viewName][$key]['vars'], $vars)
        ));
        sort($byView[$viewName][$key]['vars']);

        $rel = ltrim(str_replace(realpath(__DIR__ . '/..') ?: '', '', realpath($file) ?: $file), '/');
        $byView[$viewName][$key]['sites'][] = $rel . ':' . $line;
    }
}

$collisions = [];

foreach ($byView as $viewName => $byClass) {
    if (count($byClass) < 2) {
        continue;
    }

    if (array_key_exists($viewName, ALLOWLIST)) {
        continue;
    }

    $signatures = [];
    foreach ($byClass as $class => $info) {
        $signatures[implode(',', $info['vars'])] = true;
    }

    // Same name, same data, different classes: a shared partial. Fine.
    if (count($signatures) < 2) {
        continue;
    }

    $viewFile = viewFileFor($viewName);
    if ($viewFile === null) {
        // Can't resolve the template (a package view, or a name this guard
        // mis-read). Reporting it would be a guess.
        continue;
    }

    $viewSource = (string) file_get_contents($viewFile);

    $union = [];
    foreach ($byClass as $info) {
        $union = array_merge($union, $info['vars']);
    }
    $union = array_values(array_unique($union));

    // Which caller omits which variable, and does the template survive it?
    $offenders = [];
    foreach ($byClass as $class => $info) {
        $missing = array_values(array_diff($union, $info['vars']));
        $fatal = array_values(array_filter(
            $missing,
            static fn (string $var): bool => readsUnguarded($viewSource, $var)
        ));

        if ($fatal !== []) {
            $offenders[$class] = $fatal;
        }
    }

    if ($offenders === []) {
        continue;
    }

    $collisions[$viewName] = ['classes' => $byClass, 'offenders' => $offenders];
}

if ($collisions === []) {
    $total = count($byView);
    fwrite(STDOUT, "\u{2713} view-name collision guard passed \u{2014} {$total} view names, each rendered with one consistent variable set.\n");
    exit(0);
}

fwrite(STDERR, "\n");
fwrite(STDERR, str_repeat('=', 78) . "\n");
fwrite(STDERR, 'VIEW NAME COLLISION: ' . count($collisions) . " view name(s) rendered with different data by different controllers.\n");
fwrite(STDERR, str_repeat('=', 78) . "\n\n");

fwrite(STDERR, "Blade resolves a view by name alone, so both controllers below render the\n");
fwrite(STDERR, "SAME file. The one marked MISSING omits a variable that template reads\n");
fwrite(STDERR, "without an isset/?? guard, so that page dies the moment someone opens it.\n\n");

foreach ($collisions as $viewName => $found) {
    fwrite(STDERR, "  {$viewName}\n");

    foreach ($found['classes'] as $class => $info) {
        $vars = $info['vars'] === [] ? '(no data)' : implode(', ', $info['vars']);
        $verdict = isset($found['offenders'][$class])
            ? 'MISSING ' . implode(', ', array_map(static fn ($v) => '$' . $v, $found['offenders'][$class]))
            : 'ok';
        fwrite(STDERR, sprintf("      %-34s passes: %-34s %s\n", $class, $vars, $verdict));
        foreach (array_unique($info['sites']) as $site) {
            fwrite(STDERR, "          {$site}\n");
        }
    }

    fwrite(STDERR, "\n");
}

fwrite(STDERR, "Fix: give each feature its own view name. A directory per feature\n");
fwrite(STDERR, "(resources/views/user/link-verification/ next to\n");
fwrite(STDERR, "resources/views/user/verification/) is the cheap, permanent answer.\n");
fwrite(STDERR, "Allowlist only a genuinely shared partial whose contract is identical.\n\n");

exit(1);
