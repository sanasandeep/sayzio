#!/usr/bin/env php
<?php

/*
|--------------------------------------------------------------------------
| Sharded test runner
|--------------------------------------------------------------------------
|
| Runs the PHPUnit suite as several short-lived phpunit processes ("shards")
| instead of one long-lived process. Each shard is a fresh PHP process, so the
| per-test memory growth that accumulates across a single full run (Laravel app
| boots, route registration, cyclic object graphs) is bounded by the largest
| shard rather than the whole suite. This keeps the run comfortably under the
| 512M ceiling in phpunit.xml no matter how many tests are added.
|
| Migration cost is paid ONCE, not once per shard:
|   - The first shard runs normally; its RefreshDatabase trait performs the
|     single `migrate:fresh` on the shared `1inme_testing` database.
|   - Every later shard is launched with SHARDED_TEST_SKIP_MIGRATION=1, which
|     tests/bootstrap.php uses to set RefreshDatabaseState::$migrated = true so
|     those shards skip migrating and only wrap each test in a transaction,
|     reusing the schema the first shard built.
|   Because shards run sequentially against the one database, there is no
|   per-worker database creation/migration cost (the reason paratest was not
|   viable here).
|
| Test files are distributed across shards with a longest-processing-time
| (largest-first) greedy heuristic using file size as a duration proxy, which
| keeps the shards roughly balanced.
|
| Exit code is 0 only if every shard passed; otherwise it is non-zero and the
| failing shard numbers are printed.
|
| Usage (from artifacts/1inme):
|   composer test:sharded                 # default 4 shards
|   php scripts/run-sharded-tests.php --shards=6
|   php scripts/run-sharded-tests.php --shards=4 --filter=PlanGateTest
|   php scripts/run-sharded-tests.php --dry-run   # print shard plan, run nothing
|
| Any argument that is not --shards=N or --dry-run is forwarded verbatim to each
| phpunit process (e.g. --filter=..., --stop-on-failure).
|
| Note on --filter: the first shard performs the single migrate:fresh, so if a
| filter excludes every test in shard 1, later shards have no schema to reuse.
| Filtering is best used for quick local iteration on a single class; for that,
| `php artisan test --filter=...` is simpler. The sharded runner is for the full
| suite.
|
*/

$root = dirname(__DIR__);
chdir($root);

$shardCount = 4;
$dryRun = false;
$passthrough = [];

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--shards=(\d+)$/', $arg, $m)) {
        $shardCount = max(1, (int) $m[1]);

        continue;
    }
    if ($arg === '--dry-run') {
        $dryRun = true;

        continue;
    }
    $passthrough[] = $arg;
}

$files = discoverTestFiles(['tests/Unit', 'tests/Feature']);

if ($files === []) {
    fwrite(STDERR, "No test files found under tests/Unit or tests/Feature.\n");
    exit(1);
}

$shardCount = min($shardCount, count($files));
$shards = distribute($files, $shardCount);

if ($dryRun) {
    fwrite(STDOUT, sprintf("Shard plan for %d test files across %d shards:\n", count($files), count($shards)));
    foreach ($shards as $index => $shardFiles) {
        $bytes = array_sum(array_map(static fn (string $f): int => (int) filesize($f), $shardFiles));
        fwrite(STDOUT, sprintf(
            "\n  Shard %d — %d files, %.0f KB%s\n",
            $index + 1,
            count($shardFiles),
            $bytes / 1024,
            $index === 0 ? ' (runs migrate:fresh)' : ' (reuses schema)'
        ));
        foreach ($shardFiles as $f) {
            fwrite(STDOUT, '    '.$f."\n");
        }
    }
    exit(0);
}

$phpunit = $root.'/vendor/bin/phpunit';
if (! is_file($phpunit)) {
    fwrite(STDERR, "vendor/bin/phpunit not found — run `composer install` first.\n");
    exit(1);
}

$totalStart = microtime(true);
$failedShards = [];

foreach ($shards as $index => $shardFiles) {
    if ($shardFiles === []) {
        continue;
    }

    $human = $index + 1;
    // Only the first shard migrates; the rest reuse its schema.
    $skipMigration = $index === 0 ? '0' : '1';

    fwrite(STDOUT, sprintf(
        "\n\033[1;35m=== Shard %d/%d — %d files (skip_migration=%s) ===\033[0m\n",
        $human,
        count($shards),
        count($shardFiles),
        $skipMigration
    ));

    $command = 'SHARDED_TEST_SKIP_MIGRATION='.$skipMigration.' '
        .escapeshellarg(PHP_BINARY).' '
        .escapeshellarg($phpunit).' --colors=always '
        .implode(' ', array_map('escapeshellarg', $passthrough))
        .($passthrough === [] ? '' : ' ')
        .implode(' ', array_map('escapeshellarg', $shardFiles));

    $shardStart = microtime(true);
    passthru($command, $exitCode);
    $elapsed = microtime(true) - $shardStart;

    fwrite(STDOUT, sprintf(
        "\033[1;35m--- Shard %d finished in %.1fs (exit %d) ---\033[0m\n",
        $human,
        $elapsed,
        $exitCode
    ));

    if ($exitCode !== 0) {
        $failedShards[] = $human;
    }
}

$totalElapsed = microtime(true) - $totalStart;

if ($failedShards !== []) {
    fwrite(STDERR, sprintf(
        "\n\033[1;31mFAILED — shard(s) %s reported failures. Total time %.1fs.\033[0m\n",
        implode(', ', $failedShards),
        $totalElapsed
    ));
    exit(1);
}

fwrite(STDOUT, sprintf(
    "\n\033[1;32mAll %d shards passed in %.1fs.\033[0m\n",
    count($shards),
    $totalElapsed
));
exit(0);

/**
 * Recursively collect *Test.php files (abstract base classes such as
 * AnalyticsTestCase.php end in "Case.php" and are skipped).
 *
 * @param  list<string>  $dirs
 * @return list<string>
 */
function discoverTestFiles(array $dirs): array
{
    $files = [];

    foreach ($dirs as $dir) {
        if (! is_dir($dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return $files;
}

/**
 * Distribute files into $count shards using a largest-first greedy heuristic
 * (file size as a duration proxy) so shards stay roughly balanced.
 *
 * @param  list<string>  $files
 * @return list<list<string>>
 */
function distribute(array $files, int $count): array
{
    usort($files, static fn (string $a, string $b): int => filesize($b) <=> filesize($a));

    $shards = array_fill(0, $count, []);
    $loads = array_fill(0, $count, 0);

    foreach ($files as $file) {
        $target = 0;
        foreach ($loads as $i => $load) {
            if ($load < $loads[$target]) {
                $target = $i;
            }
        }

        $shards[$target][] = $file;
        $loads[$target] += max(1, (int) filesize($file));
    }

    // Sort each shard's files for stable, readable output.
    foreach ($shards as &$shard) {
        sort($shard);
    }
    unset($shard);

    return $shards;
}
