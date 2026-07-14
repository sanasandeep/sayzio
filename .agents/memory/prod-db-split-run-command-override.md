---
name: Prod/dev database split via run-command DB_HOST override
description: How the published app uses the oneinme-prod RDS while dev keeps oneinme; why env-var routes failed.
---

The published app talks to a separate production RDS host while the dev workspace keeps the original one.

**Rule:** the split is implemented in each artifact's `[services.production.run]` shell prelude — if the global secret `PROD_DATABASE_URL` is set, it exports `DB_HOST` (parsing out the hostname if a full `postgresql://` URL is stored). Both the Laravel artifact and api-server artifact carry the prelude; deploy-time `migrate --force` therefore targets the PROD database.

**Why:** Replit secrets are global (not env-scoped), and `setEnvVars` refuses to set a production-scoped env var whose key already exists as a secret (`DB_HOST` conflict) and refuses runtime-managed keys (`PGHOST`). A run-command `export` is the only deterministic override; Laravel's Dotenv is immutable so process env beats the committed `.env`.

**How to apply:** any new deployable artifact that reads the DB must copy the same prelude, or it silently keeps using the dev host in production. Both servers share identical credentials/db name — only the host differs. `DB_URL` must stay unset in secrets or it would bypass `DB_HOST` in Laravel.

## Prod timeout postmortem (July 2026): single-worker php -S, not the DB
After the DB split, sayzio.app fully timed out. Both RDS hosts benchmark identical
(~2.1s connect+SSL from the container, queries fine) — the new DB was NOT slower.
Root cause: production run.env had no PHP_CLI_SERVER_WORKERS (single-worker raw
`php -S`) plus DB_PERSISTENT=false, so every request paid a fresh ~2s cross-region
SSL handshake and all traffic (incl. health checks) serialized behind one worker.
**Fix:** production run.env sets DB_PERSISTENT="true" + PHP_CLI_SERVER_WORKERS="10"
(raw php -S honors the env var directly; no --no-reload dance needed — that's only
for artisan serve). **How to apply:** any raw-php -S production Laravel service on a
distant DB needs BOTH levers or one slow request wedges the whole site.
