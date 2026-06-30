---
name: Test-DB wipe guard
description: Why phpunit.xml DB_DATABASE isn't force="true" and how the TestCase guard prevents wiping the dev DB.
---

# Test runs must never wipe the dev/live DB

`phpunit.xml` sets `DB_DATABASE=1inme_testing` but a PHPUnit `<env>` does NOT
override a value already present in the process environment unless
`force="true"`. Isolated task envs commonly have `DB_DATABASE=postgres` exported
(the shared dev/live RDS), so a bare `php artisan test` resolves to `postgres`
and the first `RefreshDatabase` test runs `migrate:fresh` → DROPS EVERY TABLE on
the dev DB. This actually happened once.

**Why not just add `force="true"`?**
CI (`.github/workflows/laravel-tests.yml`) deliberately exports its own
`DB_DATABASE` (`onein_me_ci`, paratest workers `onein_me_ci_test_N`) and relies
on it winning over `phpunit.xml`. `force="true"` would clobber CI's database. So
`<env>` cannot both protect local runs and stay out of CI's way.

**The real protection** is a fail-closed guard in `tests/TestCase::setUp()`
(`guardAgainstNonTestDatabase`, runs once per process via a static flag, before
`parent::setUp()` so it precedes any migration). It boots a throwaway app to
resolve the database the framework will actually use, then `exit(1)` with a loud
message unless the name is recognized as a test DB:
- exact `1inme_testing` or any name containing `_testing` (covers paratest
  `*_testing_test_N`), OR
- running in CI (`CI` truthy or `GITHUB_ACTIONS` set) — CI sets DB explicitly and
  has no dev DB to clobber.

**How to apply:** any new base test class must chain to `Tests\TestCase` (all
current ones do, incl. `AnalyticsTestCase`). Do NOT "fix" the missing
`force="true"` — it re-breaks CI. If you see `ABORTING TEST RUN: refusing to run
against a non-test database`, unset the stray `DB_DATABASE` or point it at
`1inme_testing`.
