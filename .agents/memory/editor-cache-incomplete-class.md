---
name: File-cache + multi-worker artisan serve yields __PHP_Incomplete_Class
description: Caching Eloquent-derived structures over Laravel's file cache under php artisan serve workers can deserialize as __PHP_Incomplete_Class and 500 a count()/iteration
---

Under `php artisan serve` with `PHP_CLI_SERVER_WORKERS=N` (the 1inme dev/e2e
setup) and Laravel's **file** cache over the distant RDS, a value put into the
cache via `Cache::remember(...)` whose serialized graph contains a custom class
can deserialize in some worker as a `__PHP_Incomplete_Class`. On such a value
`empty()` returns false but `count()` / `@foreach` throw
`TypeError: count(): Argument #1 ($value) must be of type Countable|array,
__PHP_Incomplete_Class given` → a 500. Because requests round-robin across
workers, this 500 is **intermittent** (only some workers/cache states hit it),
which masquerades as a flaky test "timeout" — the symptom is a blank/never-armed
page, not a real wait problem. Always open `test-results/*/test-failed-1.png` to
see the actual error page before assuming a timeout-length issue.

**Why:** the editor (`BiolinkBlockController`) caches `userForms` / `userBuzz` /
`userCompanions` for the special-panel pickers; consumed in
`editor-special-panel.blade.php` via `empty() || count() === 0` + `@foreach`.

**How to apply — two layers, both cheap and prod-safe:**
1. Cache **plain arrays**, not Collections/models — end the cached closure with
   `->values()->all()` (or `->toArray()`) so the serialized blob has zero custom
   objects and can never deserialize as an incomplete class.
2. Guard Blade consumers with `!is_countable($x)` before `count($x)` (e.g.
   `@if(empty($x) || !is_countable($x) || count($x) === 0)`) so any stale/
   malformed cache value degrades to the empty state instead of 500ing.
This pattern likely surfaces in production only as a transient under FPM worker
churn; it is reliably reproducible under the multi-worker `artisan serve` gate.
