<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Whether the destructive-database guard has already run for this PHP
     * process. The check only needs to happen once per process (the resolved
     * database name cannot change mid-run), and booting a throwaway app to
     * resolve it on every single test would needlessly double the per-test
     * boot cost.
     */
    private static bool $databaseGuardEvaluated = false;

    /**
     * Fail-closed guard against wiping a non-test database.
     *
     * The Feature suite uses RefreshDatabase, which runs `migrate:fresh` and
     * therefore DROPS EVERY TABLE on the active connection. phpunit.xml sets
     * DB_DATABASE=1inme_testing, but a PHPUnit `<env>` does NOT override a
     * value already present in the process environment (only force="true"
     * does). So if a shell/session has exported DB_DATABASE=postgres (the
     * shared dev/live RDS), `php artisan test` connects there and the first
     * RefreshDatabase test wipes the developer database — this actually
     * happened once and erased the shared dev DB.
     *
     * We deliberately do NOT add force="true" to phpunit.xml: the CI workflow
     * (.github/workflows/laravel-tests.yml) relies on its own DB_DATABASE env
     * vars (onein_me_ci / paratest's onein_me_ci_test_N) winning over the XML,
     * and force="true" would clobber them. Since `<env>` cannot both protect
     * local runs and stay out of CI's way, this runtime guard does the job:
     * it aborts the whole run before any migration if the resolved database is
     * not a recognized test database.
     */
    protected function setUp(): void
    {
        $this->guardAgainstNonTestDatabase();

        parent::setUp();
    }

    private function guardAgainstNonTestDatabase(): void
    {
        if (self::$databaseGuardEvaluated) {
            return;
        }

        self::$databaseGuardEvaluated = true;

        // Resolve the database exactly as the framework will at runtime —
        // including the "process env wins" precedence that causes the hazard
        // in the first place. Booting a throwaway app only reads/merges config
        // and opens no database connection, and thanks to the static flag this
        // happens at most once per process.
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(ConsoleKernel::class)->bootstrap();

        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($this->isRecognizedTestDatabase($database)) {
            return;
        }

        fwrite(STDERR, PHP_EOL.str_repeat('!', 78).PHP_EOL);
        fwrite(STDERR, 'ABORTING TEST RUN: refusing to run against a non-test database.'.PHP_EOL.PHP_EOL);
        fwrite(STDERR, "  Resolved connection : {$connection}".PHP_EOL);
        fwrite(STDERR, '  Resolved database   : '.($database === '' ? '(empty)' : $database).PHP_EOL.PHP_EOL);
        fwrite(STDERR, 'The suite uses RefreshDatabase, which DROPS EVERY TABLE on this'.PHP_EOL);
        fwrite(STDERR, 'connection. The most common cause is an exported DB_DATABASE in your'.PHP_EOL);
        fwrite(STDERR, 'shell (e.g. DB_DATABASE=postgres) overriding phpunit.xml. Unset it, or'.PHP_EOL);
        fwrite(STDERR, 'point DB_DATABASE at a database named "1inme_testing" (or any *_testing'.PHP_EOL);
        fwrite(STDERR, 'database). See artifacts/1inme/CONTRIBUTING.md for setup.'.PHP_EOL);
        fwrite(STDERR, str_repeat('!', 78).PHP_EOL.PHP_EOL);

        exit(1);
    }

    private function isRecognizedTestDatabase(string $database): bool
    {
        // An empty/undeterminable name is treated as unsafe (fail closed).
        if ($database === '') {
            return false;
        }

        // CI deliberately exports its own DB_DATABASE (onein_me_ci, plus
        // paratest workers onein_me_ci_test_N) and has no developer dev DB to
        // clobber, so trust the explicitly-configured database there.
        if (filter_var(getenv('CI'), FILTER_VALIDATE_BOOLEAN) || getenv('GITHUB_ACTIONS') !== false) {
            return true;
        }

        // Local runs must target a clearly test-scoped database. The default
        // is 1inme_testing; paratest appends _test_N. Any *_testing name is
        // accepted so custom local test databases still work.
        return $database === '1inme_testing'
            || str_contains($database, '_testing');
    }

    /**
     * Clear resolved auth guards before any bearer-token test request.
     *
     * Sanctum's guard memoizes the FIRST user it resolves within a PHP process.
     * When a single test method fires two or more bearer-token requests (e.g.
     * mint a token → request → mutate state or switch users → request again),
     * every later request silently re-authenticates the *previous* request's
     * user and reads ITS state, even though a fresh token was passed. The
     * classic symptom is assertions that "stick" at an earlier request's
     * values, which can make a test pass — or fail — for the wrong reason and
     * hide real regressions in authenticated /api/v1 behavior.
     *
     * Every real HTTP request is a fresh process with an empty guard cache, so
     * we mirror that here: before dispatching a request that carries a bearer
     * token, forget the resolved guards so the token is re-resolved cleanly.
     *
     * This is intentionally scoped to requests with an `Authorization: Bearer`
     * header (populated by `withToken()` / `withHeaders()` and surfaced as the
     * `HTTP_AUTHORIZATION` server var). Session-based tests that authenticate
     * via `actingAs()`/`be()` carry no such header and set the user directly on
     * the guard, so they are left untouched — forgetting their guard would drop
     * the acting user and break them.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $authorization = $server['HTTP_AUTHORIZATION'] ?? null;

        if (is_string($authorization) && str_starts_with(strtolower(ltrim($authorization)), 'bearer ')) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * The Feature suite runs every test in one long-lived PHP process, and
     * each test boots a fresh Laravel app (~1.4k lines of routes). That builds
     * up large cyclic object graphs (container bindings, the HTTP request /
     * response, Eloquent models). PHP only runs its cycle collector once the
     * root buffer fills, so the peak climbs between automatic collections and
     * eventually exhausts the limit as the app grows.
     *
     * Forcing a collection after each test reclaims that garbage immediately,
     * which lowers both the resident baseline and the per-test growth slope
     * (measured ~50% reduction in peak growth on the boot+request lifecycle),
     * keeping the single-process run comfortably under the configured ceiling.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }
}
