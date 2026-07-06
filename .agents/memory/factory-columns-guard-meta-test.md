---
name: Factory-columns CI guard meta-test
description: How the check-factory-columns guard is proven still-catching (not false-green), and the subprocess-cost gotcha when meta-testing app-booting guards.
---

# Meta-testing the factory-columns guard

`scripts/check-factory-columns.php` scans `Model::factory()->create([...])` call
sites for dead-column keys (keys forced into the INSERT that are not real table
columns). It is proven honest by a PHPUnit meta-test that runs the guard as a
subprocess against throwaway fixtures.

**Rule:** any CI guard whose whole job is "fail when X regresses" needs a test
that both a clean fixture (exit 0) AND a poisoned fixture (exit 1) drive through
it — otherwise the guard can silently become false-green and nobody notices.

## Key facts (non-obvious)

- **Cost:** each guard subprocess boots the full Laravel app + replays all
  migrations to build the SchemaManifest — ~4s standalone but ~18s inside a
  phpunit run. Keep the number of guard invocations minimal (the meta-test uses
  exactly 2: one clean exit-0 run, one poisoned exit-1 run) or the file blows the
  120s tool timeout.
- **Generalization seam:** discovery must not be hard-coded to `User`. The guard
  reads extra factory dirs from env `CHECK_FACTORY_COLUMNS_EXTRA_FACTORY_DIRS`
  (`:`/`,`-separated) so a test can drop a fixture factory for a *different* real
  model (e.g. Link) and prove both that it's discovered ("N model factory/
  factories" banner) and that the dead-column scan applies to it too.
- **Fixture factory shape:** `namespace Database\Factories;`, extend `Factory`,
  `protected $model = Real\Model::class;`, and MUST implement
  `definition(): array { return []; }` (else fatal — abstract parent). The guard
  only reflects the factory, never instantiates it.
- **Banner regex gotcha:** the banner reads "...forwarded **to** N model
  factory/factories", not "for N". Match `/\bN\s+model\s+factor(?:y|ies)/i`.
- The test is a plain `PHPUnit\Framework\TestCase` (no DB, no `Tests\TestCase`),
  writes fixtures under unique `sys_get_temp_dir()` dirs, cleans up in tearDown,
  and lives in `tests/Unit` so it runs with `composer test` / `artisan test`.
