<?php

/**
 * PHP 8.4 deprecation guard.
 *
 * Lints every PHP file under the given directories (default: app, tests) with
 * full error reporting and fails if PHP emits any compile-time deprecation —
 * most importantly the "Implicitly marking parameter $x as nullable is
 * deprecated" warning that PHP 8.4 raises for every `Type $x = null` signature
 * missing the explicit `?`. These deprecations become hard errors in a future
 * PHP version, so this keeps the codebase warning-free and future-proof.
 *
 * It runs `php -l` (lint only — never executes the code) per file across a small
 * pool of parallel child processes, so it needs no database, no app boot, and no
 * test fixtures.
 *
 * Usage:
 *   php scripts/check-php-deprecations.php [dir ...]
 *
 * Exit codes:
 *   0  no deprecations (and no syntax errors) found
 *   1  one or more deprecations / syntax errors found
 */

$root = dirname(__DIR__);
$dirs = array_slice($argv, 1);
if ($dirs === []) {
    $dirs = ['app', 'tests'];
}

$files = [];
foreach ($dirs as $dir) {
    $path = $dir;
    if (!preg_match('#^/#', $path)) {
        $path = $root . '/' . ltrim($dir, '/');
    }
    if (!is_dir($path)) {
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

$total = count($files);
fwrite(STDERR, "Scanning {$total} PHP file(s) for PHP 8.4 deprecations...\n");

$php = PHP_BINARY;
$lintArgs = [
    '-d', 'error_reporting=E_ALL',
    '-d', 'display_errors=stderr',
    '-d', 'opcache.enable_cli=0',
    '-l',
];

$maxParallel = max(1, (int) (getenv('DEPRECATION_SCAN_JOBS') ?: 8));

$problems = [];
$running = [];

$descriptorSpec = [
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$launch = function (string $file) use ($php, $lintArgs, $descriptorSpec, &$running): void {
    $cmd = array_merge([$php], $lintArgs, [$file]);
    $proc = proc_open($cmd, $descriptorSpec, $pipes);
    if (!is_resource($proc)) {
        fwrite(STDERR, "Failed to launch lint for {$file}\n");
        return;
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $running[] = [
        'proc' => $proc,
        'file' => $file,
        'pipes' => $pipes,
        'out' => '',
        'err' => '',
    ];
};

$reap = function (array &$job) use (&$problems): void {
    foreach ([1 => 'out', 2 => 'err'] as $idx => $key) {
        $job[$key] .= stream_get_contents($job['pipes'][$idx]);
        fclose($job['pipes'][$idx]);
    }
    proc_close($job['proc']);

    $combined = $job['out'] . $job['err'];
    foreach (preg_split('/\r?\n/', $combined) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (stripos($line, 'Deprecated:') !== false
            || stripos($line, 'Parse error') !== false
            || stripos($line, 'Fatal error') !== false
            || stripos($line, 'Errors parsing') !== false) {
            $problems[] = ['file' => $job['file'], 'message' => $line];
        }
    }
};

$queue = $files;
while ($queue !== [] || $running !== []) {
    while ($queue !== [] && count($running) < $maxParallel) {
        $launch(array_shift($queue));
    }

    foreach ($running as $i => &$job) {
        $job['out'] .= stream_get_contents($job['pipes'][1]);
        $job['err'] .= stream_get_contents($job['pipes'][2]);
        $status = proc_get_status($job['proc']);
        if (!$status['running']) {
            $reap($job);
            unset($running[$i]);
        }
    }
    unset($job);
    $running = array_values($running);

    if ($running !== [] && $queue !== []) {
        usleep(2000);
    } elseif ($running !== []) {
        usleep(2000);
    }
}

if ($problems === []) {
    fwrite(STDERR, "OK: no PHP 8.4 deprecations or syntax errors found in {$total} file(s).\n");
    exit(0);
}

usort($problems, fn ($a, $b) => [$a['file'], $a['message']] <=> [$b['file'], $b['message']]);

fwrite(STDERR, "\nPHP 8.4 deprecation/syntax problems found (" . count($problems) . "):\n\n");
foreach ($problems as $problem) {
    $rel = str_starts_with($problem['file'], $root . '/')
        ? substr($problem['file'], strlen($root) + 1)
        : $problem['file'];
    fwrite(STDERR, "  {$rel}\n    {$problem['message']}\n");
}
fwrite(STDERR, "\nFix the signatures above (e.g. `string \$x = null` -> `?string \$x = null`).\n");

exit(1);
