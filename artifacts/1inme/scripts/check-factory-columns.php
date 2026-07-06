<?php

/**
 * Regression guard: dead-column keys forwarded to `User::factory(...)`.
 *
 * Background: the makeUser refactor replaced hand-rolled `User::create([...])`
 * test helpers with `User::factory()->create([...])`. `User::create()` silently
 * dropped any key that was not `$fillable` (mass-assignment protection), which
 * hid the fact that many helpers passed a cosmetic `role => 'user'` even though
 * `users.role` no longer exists (roles moved to the `user_roles` pivot). A
 * factory, by contrast, forces every passed attribute into the INSERT, so those
 * dead keys turned into 54+ `column "role" does not exist` failures. The factory
 * now strips the known dead key(s) in `afterMaking` (see
 * {@see \Database\Factories\UserDatabaseFactory::DROPPED_LEGACY_ATTRIBUTES}), but
 * nothing stopped a future contributor from re-adding a *different* dropped or
 * renamed column key to a factory call site and reintroducing the same class of
 * failure.
 *
 * This guard statically scans every `User::factory(...)` call site in the test
 * suite (and any extra directories passed as arguments), extracts the attribute
 * keys each chain forwards to `create`/`make`/`state`/etc., and fails if any key
 * is neither:
 *   - a real `users` column (derived by replaying the migration files via
 *     {@see \App\Modules\Common\Support\SchemaManifest}, so it needs no live DB
 *     and stays automatically in sync as columns are added/renamed/dropped), nor
 *   - a key the factory intentionally strips
 *     ({@see \Database\Factories\UserDatabaseFactory::DROPPED_LEGACY_ATTRIBUTES}).
 *
 * Real non-fillable columns (e.g. `created_at`, `email_verified_at`) are genuine
 * `users` columns, so they pass. Genuine typos and re-added dead columns fail
 * loudly here, at CI time, instead of exploding across the whole suite at run
 * time.
 *
 * Only inline array literals are analysable statically; a chain that forwards a
 * variable (`User::factory()->create($attrs)`) or a spread is reported as
 * "unresolved" for visibility but never fails the build (the keys live at the
 * caller and are out of scope for a per-call-site scan).
 *
 * Usage:
 *   php scripts/check-factory-columns.php [dir ...]   # default: tests
 *
 * Exit codes:
 *   0  no dead-column keys forwarded to User::factory(...)
 *   1  at least one dead-column key found (or the users schema could not be derived)
 */

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

/*
 * ----------------------------------------------------------------------------
 * 1. Derive the real `users` columns + the factory's intentionally-dropped keys.
 * ----------------------------------------------------------------------------
 */
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$manifest = \App\Modules\Common\Support\SchemaManifest::build();
if (! ($manifest['available'] ?? false) || empty($manifest['tables']['users'])) {
    fwrite(STDERR, "Could not derive the `users` schema from the migration files"
        . (isset($manifest['error']) ? ' (' . $manifest['error'] . ')' : '') . ".\n"
        . "This guard cannot verify factory columns without it.\n");
    exit(1);
}

/** @var array<int,string> */
$userColumns = $manifest['tables']['users'];
$columnSet = array_fill_keys($userColumns, true);

/** @var array<int,string> Keys the factory intentionally strips before persist. */
$droppedKeys = \Database\Factories\UserDatabaseFactory::DROPPED_LEGACY_ATTRIBUTES;
$allowedSet = $columnSet + array_fill_keys($droppedKeys, true);

/*
 * ----------------------------------------------------------------------------
 * 2. Collect the files to scan.
 * ----------------------------------------------------------------------------
 */
$dirs = array_slice($argv, 1);
if ($dirs === []) {
    $dirs = ['tests'];
}

$files = [];
foreach ($dirs as $dir) {
    $path = preg_match('#^/#', $dir) ? $dir : $root . '/' . ltrim($dir, '/');
    if (! is_dir($path)) {
        fwrite(STDERR, "Skipping missing directory: {$path}\n");
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
$files = array_values(array_unique($files));
sort($files);

if ($files === []) {
    fwrite(STDERR, "No PHP files found to scan.\n");
    exit(0);
}

fwrite(STDERR, 'Scanning ' . count($files) . " file(s) for dead-column keys forwarded to User::factory(...)\n");
fwrite(STDERR, 'Comparing against ' . count($userColumns) . " real `users` column(s)"
    . ($droppedKeys ? ' + intentionally-dropped: ' . implode(', ', $droppedKeys) : '') . "\n\n");

/*
 * ----------------------------------------------------------------------------
 * 3. Scan each file for User::factory(...) chains and their forwarded keys.
 * ----------------------------------------------------------------------------
 */
$problems = [];   // ['file'=>, 'line'=>, 'key'=>]
$unresolved = []; // ['file'=>, 'line'=>] chains that forward a non-literal argument

foreach ($files as $file) {
    $src = @file_get_contents($file);
    if ($src === false) {
        continue;
    }
    if (strpos($src, 'User::factory') === false) {
        continue;
    }

    foreach (findFactoryChains($src) as $chain) {
        foreach ($chain['calls'] as $call) {
            $extract = extractArrayKeys($call['tokens'], $call['method']);

            if ($extract['unresolved']) {
                $unresolved[] = ['file' => $file, 'line' => $call['line']];
            }

            foreach ($extract['keys'] as $keyInfo) {
                if (! isset($allowedSet[$keyInfo['key']])) {
                    $problems[] = [
                        'file' => $file,
                        'line' => $keyInfo['line'],
                        'key'  => $keyInfo['key'],
                    ];
                }
            }
        }
    }
}

/*
 * ----------------------------------------------------------------------------
 * 4. Report.
 * ----------------------------------------------------------------------------
 */
$rel = function (string $path) use ($root): string {
    return str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : $path;
};

if ($unresolved !== []) {
    fwrite(STDERR, count($unresolved) . " User::factory(...) call site(s) forward a non-literal argument (keys live at the caller — not checked here):\n");
    foreach ($unresolved as $u) {
        fwrite(STDERR, "  {$rel($u['file'])}:{$u['line']}\n");
    }
    fwrite(STDERR, "\n");
}

if ($problems === []) {
    fwrite(STDERR, 'OK: every User::factory(...) attribute key maps to a real `users` column.' . "\n");
    exit(0);
}

usort($problems, fn ($a, $b) => [$a['file'], $a['line'], $a['key']] <=> [$b['file'], $b['line'], $b['key']]);

fwrite(STDERR, 'Dead-column keys forwarded to User::factory(...) (' . count($problems) . "):\n\n");
foreach ($problems as $p) {
    fwrite(STDERR, "  {$rel($p['file'])}:{$p['line']}\n    '{$p['key']}' is not a real `users` column\n");
}
fwrite(STDERR, "\nEach key above is forced into the users INSERT by the factory but is not a\n");
fwrite(STDERR, "real column, so it will fail at run time with `column \"...\" does not exist`.\n");
fwrite(STDERR, "Fix options:\n");
fwrite(STDERR, "  - Remove the key from the factory call (it was likely cosmetic), or\n");
fwrite(STDERR, "  - Use the correct current column name, or\n");
fwrite(STDERR, "  - If it is a legitimately-dropped legacy key that must be tolerated, add it to\n");
fwrite(STDERR, "    Database\\Factories\\UserDatabaseFactory::DROPPED_LEGACY_ATTRIBUTES (which strips it).\n");

exit(1);

/*
 * ============================================================================
 * Token helpers.
 * ============================================================================
 */

/**
 * Find every `User::factory(...)` fluent chain in the source and, for each,
 * return the attribute-setting method calls in the chain along with the token
 * span of their argument list.
 *
 * @return array<int,array{calls:array<int,array{method:string,line:int,tokens:array<int,mixed>}>}>
 */
function findFactoryChains(string $src): array
{
    $tokens = token_get_all($src);
    $n = count($tokens);

    // Methods on a factory chain that set model attributes. Only these carry
    // the attribute arrays whose keys must be real columns.
    $attributeSetters = [
        'create', 'createone', 'createmany', 'createquietly',
        'make', 'makeone', 'makemany',
        'state', 'raw',
    ];
    // createMany/makeMany take an ARRAY OF attribute-arrays, so the column keys
    // live one array level deeper than for create/make.
    $manySetters = ['createmany', 'makemany'];

    $chains = [];

    for ($i = 0; $i < $n; $i++) {
        // Match the `User :: factory` token triple (ignoring the namespace
        // prefix — tests reference the imported short name `User`).
        if (! isStringToken($tokens[$i], 'User')) {
            continue;
        }
        // token_get_all yields `::` as T_DOUBLE_COLON.
        $j = nextMeaningful($tokens, $i + 1);
        if ($j === null || ! (is_array($tokens[$j]) && $tokens[$j][0] === T_DOUBLE_COLON)) {
            continue;
        }
        $k = nextMeaningful($tokens, $j + 1);
        if ($k === null || ! isStringToken($tokens[$k], 'factory')) {
            continue;
        }

        // Consume the factory(...) argument parens.
        $p = nextMeaningful($tokens, $k + 1);
        if ($p === null || ! isCharToken($tokens[$p], '(')) {
            continue;
        }
        $close = matchParen($tokens, $p);
        if ($close === null) {
            continue;
        }

        // Walk the fluent chain: repeated `-> method ( ... )`.
        $calls = [];
        $cursor = $close + 1;
        while (true) {
            $arrow = nextMeaningful($tokens, $cursor);
            if ($arrow === null || ! (is_array($tokens[$arrow]) && $tokens[$arrow][0] === T_OBJECT_OPERATOR)) {
                break;
            }
            $m = nextMeaningful($tokens, $arrow + 1);
            if ($m === null || ! (is_array($tokens[$m]) && $tokens[$m][0] === T_STRING)) {
                break;
            }
            $method = strtolower($tokens[$m][1]);
            $paren = nextMeaningful($tokens, $m + 1);
            if ($paren === null || ! isCharToken($tokens[$paren], '(')) {
                // Property access or malformed chain — stop following.
                break;
            }
            $argClose = matchParen($tokens, $paren);
            if ($argClose === null) {
                break;
            }

            if (in_array($method, $attributeSetters, true)) {
                $calls[] = [
                    'method' => in_array($method, $manySetters, true) ? 'many' : 'one',
                    'line'   => tokenLine($tokens, $m),
                    'tokens' => array_slice($tokens, $paren + 1, $argClose - $paren - 1),
                ];
            }

            $cursor = $argClose + 1;
        }

        if ($calls !== []) {
            $chains[] = ['calls' => $calls];
        }

        // Continue scanning after this chain.
        $i = $close;
    }

    return $chains;
}

/**
 * Extract the string array keys forwarded by an attribute-setter call.
 *
 * For `create`/`make`/`state`/etc. ($mode = 'one') the column keys are the keys
 * of the top-level array(s) — i.e. keys at array-bracket depth 1 inside the
 * argument list. `array_merge([...], [...])` keeps both arrays at depth 1 (a
 * function call adds paren depth, not bracket depth); nested value-arrays like
 * `'settings' => ['x' => 1]` put `x` at depth 2 and are correctly ignored.
 *
 * For `createMany`/`makeMany` ($mode = 'many') the argument is an array OF
 * attribute-arrays, so the column keys sit one level deeper (depth 2).
 *
 * A non-array, non-empty argument (e.g. a `$variable` or a spread) is reported
 * as unresolved so the call site is surfaced but does not fail the build.
 *
 * @param array<int,mixed> $tokens argument-list tokens (between the call parens)
 * @return array{keys:array<int,array{key:string,line:int}>, unresolved:bool}
 */
function extractArrayKeys(array $tokens, string $mode): array
{
    $targetDepth = $mode === 'many' ? 2 : 1;

    $keys = [];
    $bracketDepth = 0;
    $sawArray = false;
    $sawNonArray = false;

    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $tok = $tokens[$i];

        if (isCharToken($tok, '[')) {
            $bracketDepth++;
            $sawArray = true;
            continue;
        }
        if (isCharToken($tok, ']')) {
            $bracketDepth--;
            continue;
        }

        // `array(...)` long-form array syntax opens/closes with parens; treat
        // its opening `array` + `(` as a bracket level so keys are found.
        if (is_array($tok) && $tok[0] === T_ARRAY) {
            $sawArray = true;
        }

        if ($bracketDepth === 0) {
            // Between top-level arguments. Anything meaningful that is not the
            // start of an array (variable, string literal passed directly, fn,
            // etc.) means at least one argument's keys are not statically known.
            if (isMeaningful($tok) && ! isCharToken($tok, ',') && ! (is_array($tok) && $tok[0] === T_ARRAY)) {
                $sawNonArray = true;
            }
            continue;
        }

        // A string key is `'name' =>` at exactly the target bracket depth.
        if ($bracketDepth === $targetDepth
            && is_array($tok)
            && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
            $next = nextMeaningfulFromLocal($tokens, $i + 1);
            if ($next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_DOUBLE_ARROW) {
                $keys[] = [
                    'key'  => unquote($tok[1]),
                    'line' => $tok[2] ?? 0,
                ];
            }
        }
    }

    // Only flag "unresolved" when no analysable array was present at all — a
    // pure `->create($attrs)` / `->create()` — otherwise the array keys we found
    // are authoritative for this call site.
    $unresolved = $sawNonArray && ! $sawArray;

    return ['keys' => $keys, 'unresolved' => $unresolved];
}

/** True if the token is a T_STRING equal (case-sensitive) to $value. */
function isStringToken($token, string $value): bool
{
    return is_array($token) && $token[0] === T_STRING && $token[1] === $value;
}

/** True if the token is the single-char token $char (e.g. '(', '[', ','). */
function isCharToken($token, string $char): bool
{
    return is_string($token) && $token === $char;
}

/** True for anything that is not pure whitespace or a comment. */
function isMeaningful($token): bool
{
    if (is_string($token)) {
        return trim($token) !== '';
    }
    return ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

/** Index of the next meaningful token at or after $from, or null. */
function nextMeaningful(array $tokens, int $from): ?int
{
    $n = count($tokens);
    for ($i = $from; $i < $n; $i++) {
        if (isMeaningful($tokens[$i])) {
            return $i;
        }
    }
    return null;
}

/** Same as nextMeaningful but scoped to a local token slice. */
function nextMeaningfulFromLocal(array $tokens, int $from): ?int
{
    return nextMeaningful($tokens, $from);
}

/**
 * Given the index of an opening '(' token, return the index of its matching
 * ')', or null if unbalanced.
 */
function matchParen(array $tokens, int $open): ?int
{
    $n = count($tokens);
    $depth = 0;
    for ($i = $open; $i < $n; $i++) {
        if (isCharToken($tokens[$i], '(')) {
            $depth++;
        } elseif (isCharToken($tokens[$i], ')')) {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }
    return null;
}

/** Best-effort source line for the token at $index. */
function tokenLine(array $tokens, int $index): int
{
    if (is_array($tokens[$index]) && isset($tokens[$index][2])) {
        return $tokens[$index][2];
    }
    // Walk backwards for the nearest token carrying a line number.
    for ($i = $index - 1; $i >= 0; $i--) {
        if (is_array($tokens[$i]) && isset($tokens[$i][2])) {
            return $tokens[$i][2];
        }
    }
    return 0;
}

/** Strip the surrounding quotes from a quoted string literal token. */
function unquote(string $literal): string
{
    $out = $literal;
    if (strlen($out) >= 2) {
        $q = $out[0];
        if (($q === "'" || $q === '"') && $out[strlen($out) - 1] === $q) {
            $out = substr($out, 1, -1);
        }
    }
    // Unescape the two things that matter for a single/double-quoted key.
    return str_replace(['\\\'', '\\"', '\\\\'], ['\'', '"', '\\'], $out);
}
