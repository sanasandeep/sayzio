---
name: Distant-DB dev preview performance
description: Why the 1inme dev preview times out when the DB is in a far region, and the three levers that fix it.
---

# Distant-DB dev preview performance (1inme Laravel)

When the Postgres DB is in a far AWS region (e.g. ap-south-2 from a US/EU dev
container), each query costs ~250-750ms round-trip and a fresh SSL connect costs
~3.4s. Pages that run many queries take 30s-2min, so the Replit preview proxy
times out and shows "Hmm... We couldn't reach this app" — which looks like a DB
connection failure but is pure latency. The DB connection itself is fine.

Three independent levers (apply all three for dev):

1. **Local cache store, NOT database.** This is the biggest win. If
   `CACHE_STORE=database`, every cache get/put/GC-delete is a remote round-trip.
   The 1inme homepage does ~45 cache ops per render (app_settings etc.), so a
   DB cache adds ~30s/page. Set `CACHE_STORE=file` in the dev `.env`
   (gitignored). Keep `database` for production (it's near the DB there).
   **Why:** caching against a distant DB is slower than no cache.

2. **Persistent PDO connections.** `config/database.php` pgsql `'options' =>
   [PDO::ATTR_PERSISTENT => true]`, gated on `env('DB_PERSISTENT', true)`.
   Amortizes the ~3.4s SSL handshake across requests per worker. Default is on;
   production sets `DB_PERSISTENT=false` in artifact.toml prod env so the change
   is scoped to dev (and to avoid connection accumulation if prod ever scales).

3. **Multi-worker dev server.** `php artisan serve` is single-process, so one
   slow request (a DB page, or the polling `track/heartbeat` endpoint) blocks
   ALL traffic incl. static assets — the page looks unreachable. Set
   `PHP_CLI_SERVER_WORKERS=N` in the dev run command. **Gotcha:** Laravel's
   ServeCommand IGNORES this var unless you also pass `--no-reload` (it warns and
   uses 1 worker otherwise); it then injects the var into the child `php -S`
   itself, so it does NOT need to be in `ServeCommand::$passthroughVariables`.
   `--no-reload` only disables the .env file watcher; PHP code still re-evaluates
   per request, but you must restart the workflow manually after env/config
   changes.

**How to apply:** edit the artifact dev run command + dev `.env`, run
`php artisan config:clear`, restart the `artifacts/1inme: web` workflow. Result
measured: homepage >120s (timeout) → ~7.3s; static-during-slow-page 20s → 0.17s.

**Real fix beyond dev:** deploy near the DB region (latency vanishes) or move the
RDS closer to Replit.

## Detached artisan migrate gets reaped
Even `setsid ... & disown` php artisan migrate processes get silently killed in this env after minutes (log just stops, migration left Pending, 0 rows written). For slow data-backfill migrations against the distant RDS: run the idempotent backfill helper in FOREGROUND tinker passes (`timeout 108 php artisan tinker --execute=...`, repeat until remaining=0), then `php artisan migrate --force` records the migration in seconds.
