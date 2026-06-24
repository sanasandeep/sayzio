---
name: PHP 8.4 deprecation guard
description: How the implicit-nullable / compile-time deprecation guard works and why it's a separate PHP 8.4 CI job.
---

# PHP 8.4 deprecation guard

`composer lint:deprecations` (artifacts/1inme) runs `scripts/check-php-deprecations.php`,
which `php -l`-lints every `.php` under `app/` + `tests/` and fails (exit 1) on any
emitted `Deprecated:` / `Parse error` / `Fatal error` line. Replit validation step:
`php-deprecations`.

**Why `php -l`, not a hand-rolled parser:** PHP itself authoritatively reports the
deprecation, so zero false positives/negatives. The implicit-nullable warning
(`Type $x = null` missing `?`) is emitted at **compile time**, so plain `php -l` triggers
it — but lint exits **0** regardless, so you MUST scan the lint OUTPUT for `Deprecated:`,
not the exit code. `opcache_compile_file()` suppresses it (don't use it).

**Why a dedicated CI job (`php84-deprecations` in `.github/workflows/laravel-tests.yml`):**
the main `phpunit-postgres` job pins PHP **8.3**, which never raises this deprecation. The
guard job pins PHP **8.4** and needs no Composer install and no DB (pure lint).

**Perf:** CPU-bound on process startup (~80s on 2 cores for ~1040 files; ~16s in the
validation runner). Pool size via `DEPRECATION_SCAN_JOBS` (default 8) — more cores help,
job count past core count does not. Don't run the full composer wrapper through the 120s
bash tool; use `startValidationRun` instead. Script takes **directories** as args, not files.
