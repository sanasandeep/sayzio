<?php

/**
 * Regression guard for the destructive-database safety net.
 *
 * Tests\TestCase::guardAgainstNonTestDatabase() is the fail-closed check that
 * aborts the whole PHPUnit run before any migration when the resolved database
 * is not a recognized test database. It is the only thing standing between a
 * stray `DB_DATABASE=postgres` and a wiped shared dev/live database — and this
 * actually happened once (see the comments in tests/TestCase.php).
 *
 * Nothing else verifies that guard still works, so a future refactor, a renamed
 * method, or a tweaked allowlist could silently disable it. This script invokes
 * the REAL guard (via reflection on Tests\TestCase, never a copy of its logic)
 * in subprocesses across the key scenarios and fails the build if any scenario
 * no longer behaves as expected.
 *
 * Each scenario runs in its own subprocess so it gets a fresh process
 * environment and a fresh static guard flag, and so the guard's own `exit(1)`
 * is observed as a real process exit code rather than killing this script.
 *
 * Usage:
 *   php scripts/check-database-guard.php            # run all scenarios
 *   php scripts/check-database-guard.php --worker   # internal: run the guard once
 *
 * Exit codes:
 *   0  every scenario behaved as expected (guard is intact)
 *   1  at least one scenario regressed (guard is broken / disabled)
 */

$root = dirname(__DIR__);

/*
 * ----------------------------------------------------------------------------
 * Worker mode: invoke the real guard once and let its behavior speak for
 * itself (a clean return prints GUARD_PASSED; a rejection runs the guard's own
 * exit(1) after printing its ABORTING banner). This deliberately reflects on
 * the production class so the check stays in lockstep with the real guard.
 * ----------------------------------------------------------------------------
 */
if (in_array('--worker', $argv, true)) {
    chdir($root);
    require $root.'/vendor/autoload.php';

    // newInstanceWithoutConstructor cannot target an abstract class, so define
    // a throwaway concrete subclass of the real TestCase to invoke against.
    // newInstanceWithoutConstructor sidesteps PHPUnit's constructor entirely.
    final class GuardProbeTestCase extends \Tests\TestCase {}

    $instance = (new ReflectionClass(GuardProbeTestCase::class))->newInstanceWithoutConstructor();

    // Reset the once-per-process flag in case anything touched it during boot,
    // so the guard actually evaluates in this subprocess.
    $flag = new ReflectionProperty(\Tests\TestCase::class, 'databaseGuardEvaluated');
    $flag->setAccessible(true);
    $flag->setValue(null, false);

    $guard = new ReflectionMethod(\Tests\TestCase::class, 'guardAgainstNonTestDatabase');
    $guard->setAccessible(true);
    $guard->invoke($instance);

    // Reaching here means the guard accepted the database (it did not exit).
    echo "GUARD_PASSED\n";
    exit(0);
}

/*
 * ----------------------------------------------------------------------------
 * Orchestrator mode.
 * ----------------------------------------------------------------------------
 */

/**
 * Scenarios cover the behavioral contract of the guard:
 *  - obvious shared databases must abort (postgres, the dev DB name 1inme),
 *  - the canonical test DB and any *_testing name must pass locally,
 *  - and CI must bypass the local restriction (it exports its own DB names and
 *    has no developer DB to clobber).
 */
$scenarios = [
    [
        'name' => 'DB_DATABASE=postgres aborts locally',
        'database' => 'postgres',
        'ci' => false,
        'expect' => 'abort',
    ],
    [
        'name' => 'DB_DATABASE=1inme (shared dev DB) aborts locally',
        'database' => '1inme',
        'ci' => false,
        'expect' => 'abort',
    ],
    [
        'name' => 'DB_DATABASE=1inme_testing passes locally',
        'database' => '1inme_testing',
        'ci' => false,
        'expect' => 'pass',
    ],
    [
        'name' => 'DB_DATABASE=acme_testing (any *_testing) passes locally',
        'database' => 'acme_testing',
        'ci' => false,
        'expect' => 'pass',
    ],
    [
        'name' => 'CI=true bypasses the local restriction',
        'database' => 'postgres',
        'ci' => true,
        'expect' => 'pass',
    ],
];

$php = PHP_BINARY;
$worker = __FILE__;

$failures = [];

fwrite(STDERR, "Verifying the dev-database safety net (Tests\\TestCase guard)...\n\n");

foreach ($scenarios as $scenario) {
    $result = runScenario($php, $worker, $scenario['database'], $scenario['ci']);

    $ok = $result === $scenario['expect'];
    $marker = $ok ? 'PASS' : 'FAIL';

    fwrite(STDERR, sprintf(
        "  [%s] %s (expected %s, got %s)\n",
        $marker,
        $scenario['name'],
        $scenario['expect'],
        $result
    ));

    if (!$ok) {
        $failures[] = $scenario['name'];
    }
}

if ($failures === []) {
    fwrite(STDERR, "\nOK: the destructive-database guard behaved correctly in every scenario.\n");
    exit(0);
}

fwrite(STDERR, "\nThe destructive-database guard REGRESSED in " . count($failures) . " scenario(s):\n");
foreach ($failures as $name) {
    fwrite(STDERR, "  - {$name}\n");
}
fwrite(STDERR, "\nThis guard is the only thing stopping a stray DB_DATABASE from wiping the\n");
fwrite(STDERR, "shared dev/live database. Restore Tests\\TestCase::guardAgainstNonTestDatabase\n");
fwrite(STDERR, "(and its *_testing / CI allowlist) before merging.\n");

exit(1);

/**
 * Run a single scenario in a subprocess and classify the outcome.
 *
 * @return string one of 'pass', 'abort', or 'error'
 */
function runScenario(string $php, string $worker, string $database, bool $ci): string
{
    // Start from the current environment so the app can still boot (APP_KEY,
    // DB_CONNECTION, etc.), then take full control of the variables the guard
    // reads. CI / GITHUB_ACTIONS are explicitly removed unless the scenario
    // opts in — otherwise running this very check inside GitHub Actions would
    // make every database look "recognized" and silently pass.
    $env = getenv();
    unset($env['CI'], $env['GITHUB_ACTIONS']);
    $env['DB_DATABASE'] = $database;
    if ($ci) {
        $env['CI'] = 'true';
    }

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open([$php, $worker, '--worker'], $descriptors, $pipes, null, $env);
    if (!is_resource($proc)) {
        fwrite(STDERR, "  (could not launch worker subprocess)\n");
        return 'error';
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    $combined = $stdout . $stderr;

    $passed = $exitCode === 0 && str_contains($stdout, 'GUARD_PASSED');
    $aborted = $exitCode === 1 && str_contains($combined, 'ABORTING TEST RUN');

    if ($passed) {
        return 'pass';
    }

    if ($aborted) {
        return 'abort';
    }

    // Anything else (boot failure, unexpected exit code, missing markers) is an
    // error we surface rather than silently treat as a pass.
    fwrite(STDERR, "    unexpected worker output (exit {$exitCode}):\n");
    foreach (preg_split('/\r?\n/', trim($combined)) as $line) {
        if (trim($line) !== '') {
            fwrite(STDERR, "      {$line}\n");
        }
    }

    return 'error';
}
