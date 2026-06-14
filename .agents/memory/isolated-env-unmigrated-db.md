---
name: Isolated env starts with an un-migrated 1inme dev DB
description: Fresh isolated task environments often have the 1inme dev DB dozens of migrations behind, so the app 500s globally until you migrate.
---

In an isolated task environment the 1inme Laravel dev DB (distant RDS) can arrive
many migrations behind `main` — `users`/`migrations` exist but newer tables (e.g.
`app_settings`) do not. Every page then 500s with `relation "..." does not exist`,
because middleware (maintenance/marketing) calls `AppSetting::get()` on every request.

**Why:** the un-migrated state looks like a code bug or a DB outage, but it is just
pending migrations. Diagnose by checking `information_schema.tables` for the table
named in the 500, not by editing code.

**How to apply:** run `php artisan migrate --force` to fix it, but the distant RDS is
slow (~250ms/query) so a full catch-up can take 20+ minutes. Run it in the background
(`nohup ... &`) and poll — do NOT let a foreground 120s bash timeout kill it, because
an interrupted migrate leaves orphaned applied-but-unrecorded migrations (see
laravel-migrate-orphans.md). artisan's migrate output only flushes at completion, so
poll progress by querying the `migrations` table directly instead of tailing the log.
The testing DB (`1inme_testing`) is now auto-provisioned (empty) by
`scripts/post-merge.sh` after each merge, so RefreshDatabase feature tests CAN run
locally — but two gotchas remain. (1) The task env exports real `DB_*` process
vars pointing at the distant RDS (`DB_DATABASE=postgres`, `DB_HOST=...rds...`).
Those WIN over phpunit.xml's forced `DB_DATABASE=1inme_testing` (PHPUnit `<env>`
doesn't override existing process env), so a bare `php artisan test` would target
the RDS `postgres` DB and RefreshDatabase would wipe it. Always run the suite with
explicit overrides to the LOCAL helium PG: `DB_HOST=helium DB_PORT=5432
DB_DATABASE=1inme_testing DB_USERNAME=postgres DB_PASSWORD=password DB_SSLMODE=disable
DB_URL= php artisan test --filter=...`. (2) `.env` already points at helium (the
local Replit PG, same as the `PG*` vars); that is the safe test target.
