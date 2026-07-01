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
this Laravel 12 app has NO `server.php` router (only `public/index.php`), so
`php -S ... -t public server.php` fatals ("Failed opening required 'server.php'")
yet STILL returns HTTP 200 serving the PHP error page — a 200 alone is a false
pass; always check the byte size / grep for real content (a 300-430 byte body is
the error/redirect page, a real rendered page is tens of KB). Write a tiny router
to `/tmp` with an ABSOLUTE app path (a relative `__DIR__` resolves to `/tmp`, not
the app): `$uri=urldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)); if($uri!=='/' && file_exists('<ABS>/public'.$uri)) return false; require '<ABS>/public/index.php';`
then `nohup php -S 127.0.0.1:5060 /tmp/router.php &` and curl it. **Why `php -S`
over `php artisan serve`:** `php -S` inherits the current shell env directly, so
DB_* creds pass through — `artisan serve` spawns a child `php -S` that strips all
env except `ServeCommand::$passthroughVariables`, giving "no password supplied".
Clear `storage/framework/views/*.php` first if you edited a blade — stale compiled
views serve the OLD markup. A real (tens-of-KB) 200 on the target page that greps
for your new markup proves the module renders.

**Verifying controller/service logic WITHOUT HTTP:** over a distant RDS the dev
`php -S` frequently crashes on DB-heavy authed routes (even `/api/v1/profile`).
When you just need to exercise controller/service code, write a standalone
bootstrap PHP script that boots the framework kernel and calls the method
directly — do NOT use the `tinker` REPL, which mangles multi-statement input.
