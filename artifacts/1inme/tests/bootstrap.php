<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;

/*
|--------------------------------------------------------------------------
| PHPUnit bootstrap
|--------------------------------------------------------------------------
|
| Replaces the bare `vendor/autoload.php` bootstrap so the sharded test
| runner (scripts/run-sharded-tests.php) can bound peak memory by splitting
| the suite across several short-lived phpunit processes instead of running
| every test in one long-lived process.
|
| When the runner launches a shard with SHARDED_TEST_SKIP_MIGRATION=1, we set
| RefreshDatabaseState::$migrated = true up-front. That makes Laravel's
| RefreshDatabase trait skip its `migrate:fresh` and only wrap each test in a
| transaction, reusing the schema the FIRST shard already built on the shared
| `1inme_testing` database. The result is one migration for the whole run
| instead of one per shard, which is the whole point of "reuse one testing DB
| sequentially" — the slow part (224 migrations against remote Postgres) is
| paid exactly once.
|
| With the flag unset (a normal `artisan test` / `phpunit` invocation) this
| file behaves identically to the previous `vendor/autoload.php` bootstrap, so
| nothing changes for non-sharded runs.
|
*/

require __DIR__.'/../vendor/autoload.php';

if (getenv('SHARDED_TEST_SKIP_MIGRATION') === '1') {
    RefreshDatabaseState::$migrated = true;
}
