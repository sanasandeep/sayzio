---
name: Seeding heavy demo content on deploy (shared distant RDS)
description: How to run a slow, heavy seeder against the 1inme app when dev and prod share one far-away RDS and the dev sandbox can't finish long jobs.
---

# Seeding heavy demo content on deploy

**Situation:** 1inme dev and prod share the SAME distant AWS RDS (~251ms/query). A full demo seeder (DemoContentSeeder: demo-account links, team workspaces, task boards, multi-creator discover feed) issues thousands of writes → 15–25+ min from the dev workspace, where it reliably gets killed (foreground >120s tool cap; detached bash reaped; workflow slots full). So it can only finish where it runs co-located/uninterrupted = the production deploy server.

**Chosen mechanism:** a one-time Laravel migration (`database/migrations/*_seed_demo_content_on_deploy.php`) that the deploy's `production.run` step runs via `php artisan migrate --force` (APP_ENV=production is set there). It:
- guards `if (! app()->environment('production')) return;` so dev / post-merge `migrate` no-op instead of hanging,
- takes `pg_try_advisory_lock` so concurrent instance boots don't spawn two seeders,
- spawns the seeder in the BACKGROUND (`exec('nohup php artisan db:seed --class=... --force >> log 2>&1 &')`) and returns immediately,
- has NO synchronous fallback (blocking the deploy is worse than skipping the seed).

**Why background, not blocking:** `production.run` runs `migrate --force` BEFORE `exec php -S` starts the server, and the startup health probe (`/up`) can't answer until the server is up. A blocking multi-minute seed would push server startup past the promote timeout and FAIL the deploy. Background spawn lets migrate finish, server boot, health pass, seed run concurrently.

**Why a migration and NOT editing `production.run` in artifact.toml:** the run command is a large, nested-quote `sh -c '...'`. Fumbling it breaks the deploy entirely (catastrophic). A migration's worst case is benign (demo content just doesn't populate; the live app is unharmed). Optimize for not breaking the user's deploy.

**Known limitation — the shared-DB "consumption trap":** a migration is once-only per DB. If any NON-production `migrate` hits the shared RDS first, `up()` no-ops (env guard) but Laravel still records it Ran → the prod deploy then SKIPS it and the seed never runs. Mitigation: the dev serve workflow never runs `migrate`; only post-merge or a manual `migrate` would. So keep the migration Pending — don't run `migrate` in dev, and Republish before any task-agent merge. If it ever gets consumed, ship a fresh follow-up migration.

**Seeder facts that make this safe:** seeders are in composer MAIN `autoload` (psr-4 `Database\Seeders\`), so present under `composer install --no-dev`. DemoContentSeeder is idempotent (wipePreviousDemoContent → rebuild) and uses `demo@1inme.com` via `firstOrCreate` (won't overwrite an existing account's password, so a re-seed won't reset a secured demo password).

**Verify without consuming:** `php -l` the file and `php artisan migrate:status | tail` (read-only) to confirm it shows Pending. Never `php artisan migrate` in dev to "test" it.
