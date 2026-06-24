---
name: Verifying your own 1inme work in an isolated env
description: How to apply just your task's migrations and smoke-test the Laravel app when the dev server isn't running and the migration backlog is blocked.
---

Isolated task envs for 1inme start dozens-to-hundreds of migrations behind on the
shared RDS, and that backlog frequently contains PRE-EXISTING buggy migrations
unrelated to your task (e.g. a `DROP INDEX` that should be `DROP CONSTRAINT`, or an
unguarded `Schema::table` orphan). `db:reconcile-migrations` drains orphans but
STOPS on a genuinely-broken non-orphan migration — so you cannot always fully drain
the queue, and fixing someone else's migration is out of your scope.

**Apply ONLY your own task migrations, past the blocked backlog:**
`php artisan migrate --path="database/migrations/<file>.php" --force` runs a single
migration file in isolation, ignoring the pending ones before it. Safe because your
migrations are additive + `hasColumn`/`hasTable`-guarded. Apply them in dependency
order. Your pending migrations being un-applied in the isolated env is expected —
the full backlog drains during post-merge; you just need them applied locally to
verify.

**Smoke-test the app when no dev server is running and `restart_workflow` can't
start it** (the `web` service is platform-managed; `restart_workflow web`/`<slug>`
returns RUN_COMMAND_NOT_FOUND, and nothing is behind the `:80` proxy → 502):
`nohup php -S 127.0.0.1:5050 -t public server.php >/tmp/smoke.log 2>&1 &`
then curl it. **Why `php -S` over `php artisan serve`:** `php -S` inherits the
current shell env directly, so DB_* creds pass through — `artisan serve` spawns a
child `php -S` that strips all env except `ServeCommand::$passthroughVariables`,
giving "no password supplied". `public/index.php` exists; `server.php` is Laravel's
built-in router. A 200 on `/admin/login` (not just `/`, which the DevStartupProbe
shields) proves the module actually renders. Kill the bg proc when done.
