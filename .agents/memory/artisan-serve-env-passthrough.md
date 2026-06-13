---
name: artisan serve env passthrough
description: Why DB creds (or any env/secret) vanish in `php artisan serve` and how to fix the "no password supplied" 500s.
---

# `php artisan serve` strips most env vars from its child process

`artisan serve` spawns a child `php -S` dev server. Laravel's `ServeCommand`
only forwards the variables listed in `ServeCommand::$passthroughVariables`;
**every other `$_ENV` var is explicitly set to `false` in the child's
environment.** So secrets that exist in the parent shell (e.g. `DB_PASSWORD`)
are *absent* in the request-handling child, producing PDO errors like
`SQLSTATE[08006] fe_sendauth: no password supplied` even though `php artisan
db:show` / tinker (which run in the parent) work fine.

**Fix:** append the needed keys to the passthrough list early in boot, e.g. in
`AppServiceProvider::boot()`:

```php
ServeCommand::$passthroughVariables = array_merge(
    ServeCommand::$passthroughVariables,
    ['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_CONNECTION','DB_SSLMODE'],
);
```

**Why:** the dev DB password is a Replit secret, not in `.env`; the proxy/dev
server runs via the artisan-serve workflow, so the child must inherit it.

**How to apply / verify:** after restarting the workflow, confirm the child has
it: `for p in $(pgrep -f "php -S"); do tr '\0' '\n' < /proc/$p/environ | grep -c '^DB_PASSWORD='; done` should print `1`. Parent-only checks (tinker, db:show)
will pass regardless and will mislead you — always check the child process.
