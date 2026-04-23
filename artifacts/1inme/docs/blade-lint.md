# Blade template syntax check

## What it does

`tests/Feature/BladeViewsCompileTest.php` walks every
`resources/views/**/*.blade.php`, runs it through Blade's compiler, writes the
resulting PHP to a temp directory, and runs `php -l` (in parallel via `xargs
-P`) on each compiled file.

The test fails — and CI fails with it — if any view produces invalid PHP after
compilation.

This catches the kind of breakage that previously only surfaced at request
time, e.g.:

- `@json([...])` / `@foreach([...] as $row)` directives whose argument contains
  inline arrays. Blade's directive argument parser is comma-sensitive and
  silently truncates the call, leaving an unbalanced `(`.
- Inline `@endif` / `@endforeach` glued to a word character (e.g. `+d@endif`).
  Blade's directive regex requires `\B@`, so the `@endif` is treated as text
  and the surrounding `if` is never closed.
- Alpine.js event handlers like `@error="..."` being mistaken for the Blade
  `@error('field')` directive (use `x-on:error` or escape with `@@error`).

## Run it locally

From `artifacts/1inme/`:

```bash
# Just the view lint (~10–25s)
composer lint:views

# Or directly via PHPUnit
vendor/bin/phpunit --filter=BladeViewsCompileTest --no-coverage

# Or as part of the full suite
composer test
```

Tune parallelism with `BLADE_LINT_PARALLELISM` (default `8`):

```bash
BLADE_LINT_PARALLELISM=16 composer lint:views
```

## CI

The check is a regular Feature test, so any pipeline that already runs
`composer test` / `php artisan test` will pick it up automatically. To run
only the view lint as a fast pre-deploy gate, invoke `composer lint:views`.

## Output

On failure the test prints one line per broken view, with the temp path
rewritten to `<compiled:path/to/view.blade.php>` so the originating template
is obvious:

```
admin/foo/bar.blade.php: Parse error: syntax error, unexpected token ";",
expecting ")" in <compiled:admin/foo/bar.blade.php> on line 79
```
