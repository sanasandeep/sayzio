<?php

namespace QueryColumnsGuard;

/**
 * Regression guard: queries against nonexistent database columns.
 *
 * Background: the biolink link-picker endpoint shipped querying
 * `links.meta_title` — a column that does not exist (the real column is
 * `seo_title`) — so the picker 500'd in the live editor until an e2e test
 * caught it. The feature tests that should have caught it used a nonexistent
 * `Link::factory()` and could never run. Nothing static stopped that class of
 * bug from shipping.
 *
 * This guard statically sweeps literal column names in Eloquent/query-builder
 * calls under the app's controllers/services and validates them against the
 * expected schema derived by replaying the migration files
 * ({@see \App\Modules\Common\Support\SchemaManifest} — DB-free, no live
 * connection required), exactly like `check-factory-columns.php` does for
 * factory attribute keys.
 *
 * Three tiers of precision (most precise wins; a literal is only ever checked
 * once per call site):
 *
 *  1. ROOTED chains — a fluent chain starting `<Model>::where(...)` /
 *     `<Model>::query()` / any static query method, where <Model> is a
 *     discovered Eloquent model short name. Every literal column forwarded to
 *     a column-taking method in the chain must be a real column of that
 *     model's table. This is the tier that catches the historical
 *     `Link::...->select([... 'meta_title' ...])` bug precisely.
 *     Chain-following stops at the first join/from/newQuery-style method,
 *     because after a join the builder legitimately sees other tables'
 *     columns.
 *
 *  2. QUALIFIED literals — any `table.column` string literal in a query-method
 *     argument position, on any receiver: if `table` is a real table in the
 *     manifest, `column` must be one of its columns. (An unknown prefix is
 *     treated as a query alias and skipped.)
 *
 *  3. UNION check — unqualified literals in UNROOTED chains (`$query->…`,
 *     relation builders, etc.) are checked against the union of ALL tables'
 *     columns, and only for methods that do NOT exist on
 *     `Illuminate\Support\Collection` (so `->where('key', …)` on a collection
 *     of payload arrays can never false-positive). This still catches
 *     out-and-out typos and renamed-away columns that exist on no table.
 *
 * What is SAFE (never flagged):
 *   - Raw expressions (`selectRaw`, `whereRaw`, `DB::raw`), variables,
 *     concatenations, spreads — only pure single string literals are checked.
 *   - `*`, `table.*`, anything containing spaces/parens after `as`-stripping.
 *   - JSON paths (`settings->foo`) — only the base column is validated.
 *   - Aggregate/withCount aliases: `*_count`, and `_{sum,avg,min,max}_`
 *     infixed names (`links_sum_clicks`).
 *   - `pivot_`-prefixed names and the literal `aggregate`.
 *   - Names in DYNAMIC_COLUMNS (global) or ALLOWLIST (file + needle) below.
 *
 * Adding a legit exception: append to ALLOWLIST with a reason — never weaken
 * the matcher.
 *
 * Usage:
 *   php scripts/check-query-columns.php [dir ...]   # default: app/Modules app/Services
 *
 * Exit codes:
 *   0  every checked literal maps to a real column
 *   1  at least one dead column literal found (or the schema/models could not be derived)
 */

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

/*
 * ----------------------------------------------------------------------------
 * 0. Configuration.
 * ----------------------------------------------------------------------------
 */

/**
 * Methods that take column names, and WHERE the columns live in the args.
 * Shapes:
 *   first    — arg0 when it is a string literal; when arg0 is an array
 *              literal, its depth-1 string KEYS ('col' => …).
 *   all      — every top-level string-literal arg, plus depth-1 string
 *              ELEMENTS of array-literal args (select-style lists).
 *   pluck    — args 0 and 1 when string literals (value column, key column).
 *   columns  — every string-literal arg that does not look like an operator
 *              (whereColumn('a', '=', 'b')).
 */
const METHOD_SHAPES = [
    // first-arg column methods
    'where' => 'first', 'orwhere' => 'first', 'wherenot' => 'first', 'orwherenot' => 'first',
    'firstwhere' => 'first',
    'wherein' => 'first', 'wherenotin' => 'first', 'orwherein' => 'first', 'orwherenotin' => 'first',
    'wherenull' => 'first', 'wherenotnull' => 'first', 'orwherenull' => 'first', 'orwherenotnull' => 'first',
    'wherebetween' => 'first', 'wherenotbetween' => 'first',
    'wheredate' => 'first', 'whereday' => 'first', 'wheremonth' => 'first', 'whereyear' => 'first', 'wheretime' => 'first',
    'wherelike' => 'first', 'orwherelike' => 'first',
    'orderby' => 'first', 'orderbydesc' => 'first', 'latest' => 'first', 'oldest' => 'first',
    'value' => 'first', 'min' => 'first', 'max' => 'first', 'sum' => 'first', 'avg' => 'first', 'average' => 'first',
    'increment' => 'first', 'decrement' => 'first',
    // list methods
    'select' => 'all', 'addselect' => 'all', 'groupby' => 'all',
    // special shapes
    'pluck' => 'pluck',
    'wherecolumn' => 'columns', 'orwherecolumn' => 'columns',
];

/**
 * Methods safe to check on an UNROOTED receiver against the all-tables union.
 * Deliberately excludes every method that also exists on
 * `Illuminate\Support\Collection` (where, whereIn, whereNull, whereBetween,
 * pluck, groupBy, select, value, min/max/sum/avg, firstWhere, sortBy…) so a
 * collection call on payload-array keys can never false-positive.
 */
const UNION_SAFE_METHODS = [
    'addselect', 'orderby', 'orderbydesc', 'latest', 'oldest',
    'wheredate', 'whereday', 'wheremonth', 'whereyear', 'wheretime',
    'wherecolumn', 'orwherecolumn', 'wherelike', 'orwherelike',
    'orwherenull', 'orwherenotnull', 'increment', 'decrement',
];

/**
 * After one of these, the builder legitimately sees another table's columns —
 * stop attributing subsequent literals in a rooted chain to the model's table
 * (they degrade to the qualified/union tiers on their own occurrences? No —
 * chain scanning simply stops; the qualified tier still applies globally).
 */
const CHAIN_BREAKERS = [
    'join', 'leftjoin', 'rightjoin', 'crossjoin', 'joinsub', 'leftjoinsub', 'rightjoinsub',
    'from', 'fromsub', 'newquery', 'tobase', 'getquery',
];

/** Column names that are always dynamic/virtual — never validated. */
const DYNAMIC_COLUMNS = [
    'aggregate',
];

/** An intentional exception: file (project-relative), line substring, reason. */
const ALLOWLIST = [
    // ['file' => 'app/Modules/...', 'needle' => "->where('...'", 'reason' => '...'],
];

/*
 * ----------------------------------------------------------------------------
 * 1. Boot the app and derive the expected schema (table => columns), DB-free.
 * ----------------------------------------------------------------------------
 */
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$manifest = \App\Modules\Common\Support\SchemaManifest::build();
if (! ($manifest['available'] ?? false) || empty($manifest['tables'])) {
    fwrite(STDERR, 'Could not derive the expected schema from the migration files'
        .(isset($manifest['error']) ? ' ('.$manifest['error'].')' : '').".\n"
        ."This guard cannot verify query columns without it.\n");
    exit(1);
}

/** @var array<string,array<int,string>> table => columns */
$schemaTables = $manifest['tables'];

/** @var array<string,array<string,true>> table => column set */
$tableColumnSets = [];
/** @var array<string,true> union of every column name across all tables */
$unionColumns = [];
foreach ($schemaTables as $table => $columns) {
    $set = array_fill_keys($columns, true);
    $tableColumnSets[$table] = $set;
    $unionColumns += $set;
}

/*
 * ----------------------------------------------------------------------------
 * 2. Discover every Eloquent model and map short name => table.
 * ----------------------------------------------------------------------------
 */
$models = discoverModels($root, $tableColumnSets);
if ($models === []) {
    fwrite(STDERR, "No Eloquent models discovered under app/ — the guard has nothing to verify.\n");
    exit(1);
}

/*
 * ----------------------------------------------------------------------------
 * 3. Collect the files to scan.
 * ----------------------------------------------------------------------------
 */
$dirs = array_slice($argv, 1);
if ($dirs === []) {
    $dirs = ['app/Modules', 'app/Services'];
}

$files = [];
foreach ($dirs as $dir) {
    $path = preg_match('#^/#', $dir) ? $dir : $root.'/'.ltrim($dir, '/');
    if (! is_dir($path)) {
        fwrite(STDERR, "Skipping missing directory: {$path}\n");
        continue;
    }
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
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

fwrite(STDERR, 'Scanning '.count($files).' file(s) against '.count($schemaTables).' table(s) / '
    .count($unionColumns).' distinct column name(s); '.count($models)." model(s) rooted precisely.\n\n");

/*
 * ----------------------------------------------------------------------------
 * 4. Scan.
 * ----------------------------------------------------------------------------
 */
$problems = []; // ['file','line','column','table','context','text']

foreach ($files as $file) {
    $src = @file_get_contents($file);
    if ($src === false) {
        continue;
    }
    // Cheap pre-filter: skip files with no query-method call at all.
    if (! preg_match('/->\s*(where|select|orderBy|pluck|addSelect|latest|oldest|groupBy|increment|decrement)/i', $src)
        && strpos($src, '::') === false) {
        continue;
    }

    scanFile($file, $src, $models, $tableColumnSets, $unionColumns, $problems);
}

/*
 * ----------------------------------------------------------------------------
 * 5. Filter allowlisted, report.
 * ----------------------------------------------------------------------------
 */
$rel = fn (string $p): string => str_starts_with($p, $root.'/') ? substr($p, strlen($root) + 1) : $p;

$srcLines = [];
$problems = array_values(array_filter($problems, function ($p) use ($rel, &$srcLines) {
    $relFile = $rel($p['file']);
    foreach (ALLOWLIST as $a) {
        if ($a['file'] !== $relFile) {
            continue;
        }
        if (! isset($srcLines[$p['file']])) {
            $srcLines[$p['file']] = @file($p['file']) ?: [];
        }
        $line = $srcLines[$p['file']][$p['line'] - 1] ?? '';
        if (str_contains($line, $a['needle'])) {
            return false;
        }
    }

    return true;
}));

// Stale allowlist entries fail loudly so the list can never rot.
$stale = [];
foreach (ALLOWLIST as $a) {
    $path = $root.'/'.$a['file'];
    $contents = @file_get_contents($path);
    if ($contents === false || ! str_contains($contents, $a['needle'])) {
        $stale[] = $a;
    }
}
if ($stale !== []) {
    fwrite(STDERR, "STALE allowlist entries (needle no longer found):\n");
    foreach ($stale as $a) {
        fwrite(STDERR, "  {$a['file']}: \"{$a['needle']}\"\n");
    }
    fwrite(STDERR, "Remove or update them in scripts/check-query-columns.php.\n");
    exit(1);
}

if ($problems === []) {
    fwrite(STDERR, "OK: every literal column referenced in a query-builder call maps to a real column.\n");
    exit(0);
}

usort($problems, fn ($a, $b) => [$a['file'], $a['line'], $a['column']] <=> [$b['file'], $b['line'], $b['column']]);

fwrite(STDERR, 'Query-builder calls referencing nonexistent columns ('.count($problems)."):\n\n");
foreach ($problems as $p) {
    fwrite(STDERR, "  {$rel($p['file'])}:{$p['line']}\n"
        ."    '{$p['column']}' is not a column of {$p['table']} ({$p['context']})\n");
}
fwrite(STDERR, "\nEach literal above will fail at run time with `column \"...\" does not exist`.\n");
fwrite(STDERR, "Fix options:\n");
fwrite(STDERR, "  - Use the correct current column name (check the table's migration files), or\n");
fwrite(STDERR, "  - If the name is a legitimate dynamic alias/JSON key, add an ALLOWLIST entry\n");
fwrite(STDERR, "    (file + needle + reason) in scripts/check-query-columns.php.\n");

exit(1);

/*
 * ============================================================================
 * Model discovery.
 * ============================================================================
 */

/**
 * Discover concrete Eloquent models under app/ and map each SHORT class name
 * to its table's column set. A short name shared by two models with DIFFERENT
 * tables is dropped from the rooted tier (ambiguous — its call sites degrade
 * to the qualified/union tiers).
 *
 * @param array<string,array<string,true>> $tableColumnSets
 * @return array<string,array{table:string,columns:array<string,true>}>
 */
function discoverModels(string $root, array $tableColumnSets): array
{
    $models = [];
    $ambiguous = [];

    $appDir = $root.'/app';
    if (! is_dir($appDir)) {
        return [];
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($appDir, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        // Cheap namespace+class sniff — only files that plausibly hold a model.
        $src = @file_get_contents($file->getPathname());
        if ($src === false || ! str_contains($src, 'extends')) {
            continue;
        }
        if (! preg_match('/^\s*namespace\s+([^;]+);/m', $src, $ns)) {
            continue;
        }
        if (! preg_match('/^\s*(?:final\s+|abstract\s+)*class\s+(\w+)/m', $src, $cls)) {
            continue;
        }
        $class = trim($ns[1]).'\\'.$cls[1];

        try {
            if (! class_exists($class)) {
                continue;
            }
            $ref = new \ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }
            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = $ref->newInstanceWithoutConstructor();
            $table = $instance->getTable();
        } catch (\Throwable $e) {
            continue;
        }

        if (! isset($tableColumnSets[$table])) {
            // Table not in the manifest (view-backed / raw-DDL) — cannot be
            // verified precisely; leave its call sites to the union tier.
            continue;
        }

        $short = $cls[1];
        if (isset($models[$short]) && $models[$short]['table'] !== $table) {
            $ambiguous[$short] = true;
            continue;
        }

        $models[$short] = ['table' => $table, 'columns' => $tableColumnSets[$table]];
    }

    foreach (array_keys($ambiguous) as $short) {
        unset($models[$short]);
    }

    ksort($models);

    return $models;
}

/*
 * ============================================================================
 * Scanner.
 * ============================================================================
 */

/**
 * Scan one file: find rooted `<Model>::…` chains (tier 1) and every other
 * query-method call (tiers 2 + 3), appending offenders to $problems.
 *
 * @param array<string,array{table:string,columns:array<string,true>}> $models
 * @param array<string,array<string,true>> $tableColumnSets
 * @param array<string,true> $unionColumns
 * @param array<int,array{file:string,line:int,column:string,table:string,context:string}> $problems
 */
function scanFile(string $file, string $src, array $models, array $tableColumnSets, array $unionColumns, array &$problems): void
{
    $tokens = token_get_all($src);
    $n = count($tokens);

    // Learn SQL aliases declared anywhere in this file's string literals
    // (`selectRaw('count(*) as c')`, `DB::raw('... as total')`, heredoc SQL).
    // A later `->orderByDesc('c')` / `->pluck('total')` on those aliases is
    // legitimate, so alias names are exempt from checking in THIS file only.
    $fileAliases = collectSqlAliases($tokens);

    // Token indices of column-method calls that a rooted chain already
    // handled, so pass 2 does not double-check (or union-check) them.
    $consumed = [];

    /*
     * Pass 1 — rooted chains: `<Model> :: method ( … ) -> method ( … ) …`
     */
    for ($i = 0; $i < $n; $i++) {
        if (! (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && isset($models[$tokens[$i][1]]))) {
            continue;
        }
        // Must be a bare class reference: previous meaningful token must not
        // be `->`, `::`, `new`, or a namespace separator (a leading `\` alone
        // is fine and common; `Foo\Link` would misbind, but models are always
        // imported short in this codebase).
        $prev = prevMeaningful($tokens, $i - 1);
        if ($prev !== null && is_array($tokens[$prev])
            && in_array($tokens[$prev][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_NAMESPACE, T_NS_SEPARATOR, T_USE, T_CONST, T_FUNCTION], true)) {
            continue;
        }

        $j = nextMeaningful($tokens, $i + 1);
        if ($j === null || ! (is_array($tokens[$j]) && $tokens[$j][0] === T_DOUBLE_COLON)) {
            continue;
        }
        $k = nextMeaningful($tokens, $j + 1);
        if ($k === null || ! (is_array($tokens[$k]) && $tokens[$k][0] === T_STRING)) {
            continue;
        }
        $p = nextMeaningful($tokens, $k + 1);
        if ($p === null || ! isCharToken($tokens[$p], '(')) {
            continue; // constant / property access, not a call
        }
        $close = matchParen($tokens, $p);
        if ($close === null) {
            continue;
        }

        $model = $tokens[$i][1];
        $info = $models[$model];

        // The static call itself may be a column method (Link::where('x'…)).
        $broken = false;
        handleCall($file, strtolower($tokens[$k][1]), array_slice($tokens, $p + 1, $close - $p - 1),
            $info, $tableColumnSets, $unionColumns, $fileAliases, true, $problems, $broken);
        $consumed[$k] = true;

        // Follow the fluent chain.
        $cursor = $close + 1;
        while (! $broken) {
            $arrow = nextMeaningful($tokens, $cursor);
            if ($arrow === null || ! (is_array($tokens[$arrow]) && in_array($tokens[$arrow][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true))) {
                break;
            }
            $m = nextMeaningful($tokens, $arrow + 1);
            if ($m === null || ! (is_array($tokens[$m]) && $tokens[$m][0] === T_STRING)) {
                break;
            }
            $paren = nextMeaningful($tokens, $m + 1);
            if ($paren === null || ! isCharToken($tokens[$paren], '(')) {
                break; // property access — stop following
            }
            $argClose = matchParen($tokens, $paren);
            if ($argClose === null) {
                break;
            }

            $method = strtolower($tokens[$m][1]);
            if (in_array($method, CHAIN_BREAKERS, true)) {
                break; // builder now sees other tables — stop attributing
            }

            handleCall($file, $method, array_slice($tokens, $paren + 1, $argClose - $paren - 1),
                $info, $tableColumnSets, $unionColumns, $fileAliases, true, $problems, $broken);
            $consumed[$m] = true;

            $cursor = $argClose + 1;
        }

        $i = $close;
    }

    /*
     * Pass 2 — every other `-> method ( … )` call with a column method:
     * qualified-literal (tier 2) and union (tier 3) checks.
     */
    for ($i = 0; $i < $n; $i++) {
        if (! (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) || isset($consumed[$i])) {
            continue;
        }
        $method = strtolower($tokens[$i][1]);
        if (! isset(METHOD_SHAPES[$method])) {
            continue;
        }
        $prev = prevMeaningful($tokens, $i - 1);
        if ($prev === null || ! (is_array($tokens[$prev]) && in_array($tokens[$prev][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true))) {
            continue;
        }
        $paren = nextMeaningful($tokens, $i + 1);
        if ($paren === null || ! isCharToken($tokens[$paren], '(')) {
            continue;
        }
        $close = matchParen($tokens, $paren);
        if ($close === null) {
            continue;
        }

        $broken = false;
        handleCall($file, $method, array_slice($tokens, $paren + 1, $close - $paren - 1),
            null, $tableColumnSets, $unionColumns, $fileAliases, false, $problems, $broken);
    }
}

/**
 * Validate the column literals of one method call.
 *
 * @param array{table:string,columns:array<string,true>}|null $modelInfo non-null when the chain is rooted
 * @param array<string,array<string,true>> $tableColumnSets
 * @param array<string,true> $unionColumns
 * @param array<string,true> $fileAliases SQL aliases declared in this file's raw-SQL strings
 * @param array<int,array{file:string,line:int,column:string,table:string,context:string}> $problems
 */
function handleCall(string $file, string $method, array $argTokens, ?array $modelInfo, array $tableColumnSets, array $unionColumns, array $fileAliases, bool $rooted, array &$problems, bool &$broken): void
{
    if (! isset(METHOD_SHAPES[$method])) {
        return;
    }

    foreach (extractColumnLiterals($argTokens, METHOD_SHAPES[$method]) as $lit) {
        $parsed = parseColumnLiteral($lit['value']);
        if ($parsed === null) {
            continue;
        }

        [$table, $column] = $parsed;

        if (isDynamicName($column) || ($table === null && isset($fileAliases[$column]))) {
            continue;
        }

        if ($table !== null) {
            // Tier 2 — qualified `table.column`: precise on any receiver.
            if (isset($tableColumnSets[$table]) && ! isset($tableColumnSets[$table][$column])) {
                $problems[] = [
                    'file' => $file, 'line' => $lit['line'], 'column' => $column,
                    'table' => "`{$table}`", 'context' => "qualified literal in ->{$method}()",
                ];
            }
            continue;
        }

        if ($rooted && $modelInfo !== null) {
            // Tier 1 — rooted chain: precise against the model's table.
            if (! isset($modelInfo['columns'][$column])) {
                $problems[] = [
                    'file' => $file, 'line' => $lit['line'], 'column' => $column,
                    'table' => "`{$modelInfo['table']}`", 'context' => "->{$method}() on a rooted model chain",
                ];
            }
            continue;
        }

        // Tier 3 — unrooted: union check, collection-safe methods only.
        if (in_array($method, UNION_SAFE_METHODS, true) && ! isset($unionColumns[$column])) {
            $problems[] = [
                'file' => $file, 'line' => $lit['line'], 'column' => $column,
                'table' => 'ANY table', 'context' => "->{$method}() (union check)",
            ];
        }
    }
}

/**
 * Collect SQL aliases (`… as <name>`) declared inside any string literal of
 * the file — selectRaw / DB::raw / groupByRaw / heredoc SQL fragments.
 * References to these names in later builder calls (`orderByDesc('total')`,
 * `pluck('c', 'd')`) are legitimate, so they are exempt file-wide.
 *
 * Deliberately coarse: an occasional English "as" inside a string only ever
 * ADDS an exemption for a name that matched an identifier — a tolerable
 * precision loss versus enumerating every raw-SQL call shape.
 *
 * @param array<int,mixed> $tokens
 * @return array<string,true>
 */
function collectSqlAliases(array $tokens): array
{
    $aliases = [];
    $n = count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $tok = $tokens[$i];

        // 1. `… as <name>` inside any string fragment. `^` handles interpolated
        //    SQL where the fragment starts right after a variable
        //    (`"$dateExpr as bucket, …"`).
        if (is_array($tok) && in_array($tok[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            if (preg_match('/\bas\s/i', $tok[1])
                && preg_match_all('/(?:^|[)\w`"\'])\s+as\s+[`"]?([A-Za-z_][A-Za-z0-9_]*)[`"]?/i', $tok[1], $m)) {
                foreach ($m[1] as $name) {
                    $aliases[strtolower($name)] = true;
                }
            }
            continue;
        }

        // 2. `selectSub($q, 'alias')` / `addSelectSub(…, 'alias')` — the alias
        //    is the LAST top-level string-literal argument, no `as` involved.
        if (is_array($tok) && $tok[0] === T_STRING
            && in_array(strtolower($tok[1]), ['selectsub', 'addselectsub', 'fromsub', 'orderbysub'], true)) {
            $paren = nextMeaningful($tokens, $i + 1);
            if ($paren !== null && isCharToken($tokens[$paren], '(')
                && ($close = matchParen($tokens, $paren)) !== null) {
                $args = splitTopLevelArgs(array_slice($tokens, $paren + 1, $close - $paren - 1));
                $last = end($args);
                if (is_array($last)) {
                    $meaningful = array_values(array_filter($last, fn ($t) => isMeaningful($t)));
                    if (count($meaningful) === 1 && is_array($meaningful[0]) && $meaningful[0][0] === T_CONSTANT_ENCAPSED_STRING) {
                        $aliases[strtolower(unquote($meaningful[0][1]))] = true;
                    }
                }
            }
        }
    }

    return $aliases;
}

/**
 * Extract the candidate column string literals from a call's argument tokens
 * according to the method's shape (see METHOD_SHAPES).
 *
 * Only pure literals are returned: an argument that mixes a string with
 * concatenation, interpolation, or a closure body contributes nothing.
 * Closure bodies inside arguments are skipped entirely (their inner calls are
 * separate receivers handled by pass 2 on their own).
 *
 * @param array<int,mixed> $argTokens
 * @return array<int,array{value:string,line:int}>
 */
function extractColumnLiterals(array $argTokens, string $shape): array
{
    $args = splitTopLevelArgs($argTokens);
    $out = [];

    $literalOf = function (array $arg): ?array {
        $meaningful = array_values(array_filter($arg, fn ($t) => isMeaningful($t)));
        if (count($meaningful) === 1 && is_array($meaningful[0]) && $meaningful[0][0] === T_CONSTANT_ENCAPSED_STRING) {
            return ['value' => unquote($meaningful[0][1]), 'line' => $meaningful[0][2] ?? 0];
        }

        return null;
    };

    $isArrayArg = function (array $arg): bool {
        foreach ($arg as $t) {
            if (! isMeaningful($t)) {
                continue;
            }

            return isCharToken($t, '[') || (is_array($t) && $t[0] === T_ARRAY);
        }

        return false;
    };

    switch ($shape) {
        case 'first':
            if (! isset($args[0])) {
                break;
            }
            if (($lit = $literalOf($args[0])) !== null) {
                $out[] = $lit;
            } elseif ($isArrayArg($args[0])) {
                // where(['col' => v, …]) — depth-1 string KEYS
                $out = array_merge($out, arrayStrings($args[0], true));
            }
            break;

        case 'pluck':
            foreach ([0, 1] as $idx) {
                if (isset($args[$idx]) && ($lit = $literalOf($args[$idx])) !== null) {
                    $out[] = $lit;
                }
            }
            break;

        case 'columns':
            foreach ($args as $arg) {
                if (($lit = $literalOf($arg)) !== null && ! preg_match('/^(?:[=<>!~]{1,3}|i?like|not i?like)$/i', $lit['value'])) {
                    $out[] = $lit;
                }
            }
            break;

        case 'all':
            foreach ($args as $arg) {
                if (($lit = $literalOf($arg)) !== null) {
                    $out[] = $lit;
                } elseif ($isArrayArg($arg)) {
                    // select(['a', 'b']) — depth-1 string ELEMENTS (not keys)
                    $out = array_merge($out, arrayStrings($arg, false));
                }
            }
            break;
    }

    return $out;
}

/**
 * Depth-1 string literals of an array-literal argument.
 *
 * @param array<int,mixed> $arg tokens of one argument (an array literal)
 * @param bool $keysOnly true: only strings followed by `=>` (where-array keys);
 *                       false: only strings NOT followed by `=>` (select lists)
 * @return array<int,array{value:string,line:int}>
 */
function arrayStrings(array $arg, bool $keysOnly): array
{
    $out = [];
    $depth = 0;
    $count = count($arg);

    for ($i = 0; $i < $count; $i++) {
        $tok = $arg[$i];
        if (isCharToken($tok, '[') || (is_array($tok) && $tok[0] === T_ARRAY)) {
            if (isCharToken($tok, '[')) {
                $depth++;
            }
            continue;
        }
        if (isCharToken($tok, '(')) {
            $depth++; // array(…) long form or nested call — treat as deeper
            continue;
        }
        if (isCharToken($tok, ']') || isCharToken($tok, ')')) {
            $depth--;
            continue;
        }
        if ($depth !== 1 || ! is_array($tok) || $tok[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $next = nextMeaningful($arg, $i + 1);
        $isKey = $next !== null && is_array($arg[$next]) && $arg[$next][0] === T_DOUBLE_ARROW;
        // A value string right after `=>` is a VALUE, never a column.
        $prev = prevMeaningful($arg, $i - 1);
        $isValueOfKey = $prev !== null && is_array($arg[$prev]) && $arg[$prev][0] === T_DOUBLE_ARROW;

        if ($keysOnly ? ($isKey && ! $isValueOfKey) : (! $isKey && ! $isValueOfKey)) {
            $out[] = ['value' => unquote($tok[1]), 'line' => $tok[2] ?? 0];
        }
    }

    return $out;
}

/**
 * Normalise a raw string literal to [table|null, column], or null when it is
 * not a checkable plain column reference (raw SQL, alias, `*`, …).
 *
 * @return array{0:?string,1:string}|null
 */
function parseColumnLiteral(string $raw): ?array
{
    $s = trim($raw);
    if ($s === '') {
        return null;
    }

    // Strip an alias: `col as alias` → `col`.
    $s = preg_replace('/\s+as\s+.*$/i', '', $s);

    // Anything still containing SQL-ish characters is a raw expression.
    if ($s === '' || str_contains($s, '*') || str_contains($s, '(') || str_contains($s, ' ') || str_contains($s, ',')) {
        return null;
    }

    // JSON path: validate only the base column.
    if (($pos = strpos($s, '->')) !== false) {
        $s = substr($s, 0, $pos);
    }

    $table = null;
    if (str_contains($s, '.')) {
        $parts = explode('.', $s);
        if (count($parts) !== 2) {
            return null;
        }
        [$table, $s] = $parts;
    }

    // Only plain snake_case identifiers are checkable.
    if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $s)) {
        return null;
    }
    if ($table !== null && ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
        return null;
    }

    return [$table, $s];
}

/** Aggregate aliases, pivot columns, and globally allowlisted dynamic names. */
function isDynamicName(string $column): bool
{
    if (in_array($column, DYNAMIC_COLUMNS, true)) {
        return true;
    }
    if (str_ends_with($column, '_count')) {
        return true; // withCount('x') → x_count
    }
    if (preg_match('/_(?:sum|avg|min|max)_/', $column)) {
        return true; // withSum('x','y') → x_sum_y
    }
    if (str_starts_with($column, 'pivot_') || $column === 'pivot') {
        return true;
    }

    return false;
}

/**
 * Split a call's argument-list tokens into top-level arguments (comma at
 * paren/bracket/brace depth 0). Closure bodies stay inside their argument and
 * are naturally ignored by the single-literal extractor.
 *
 * @param array<int,mixed> $tokens
 * @return array<int,array<int,mixed>>
 */
function splitTopLevelArgs(array $tokens): array
{
    $args = [];
    $current = [];
    $depth = 0;

    foreach ($tokens as $tok) {
        if (isCharToken($tok, '(') || isCharToken($tok, '[') || isCharToken($tok, '{')
            || (is_array($tok) && in_array($tok[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
        } elseif (isCharToken($tok, ')') || isCharToken($tok, ']') || isCharToken($tok, '}')) {
            $depth--;
        } elseif ($depth === 0 && isCharToken($tok, ',')) {
            $args[] = $current;
            $current = [];
            continue;
        }
        $current[] = $tok;
    }
    if ($current !== []) {
        $args[] = $current;
    }

    return $args;
}

/*
 * ============================================================================
 * Token helpers (mirrors check-factory-columns.php, namespaced to avoid
 * collisions if both scripts are ever loaded in one process).
 * ============================================================================
 */

function isCharToken($token, string $char): bool
{
    return is_string($token) && $token === $char;
}

function isMeaningful($token): bool
{
    if (is_string($token)) {
        return trim($token) !== '';
    }

    return ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

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

function prevMeaningful(array $tokens, int $from): ?int
{
    for ($i = $from; $i >= 0; $i--) {
        if (isMeaningful($tokens[$i])) {
            return $i;
        }
    }

    return null;
}

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

function unquote(string $literal): string
{
    $out = $literal;
    if (strlen($out) >= 2) {
        $q = $out[0];
        if (($q === "'" || $q === '"') && $out[strlen($out) - 1] === $q) {
            $out = substr($out, 1, -1);
        }
    }

    return str_replace(['\\\'', '\\"', '\\\\'], ['\'', '"', '\\'], $out);
}
