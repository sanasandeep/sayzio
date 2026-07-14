---
name: Prod/dev database split via run-command DB_HOST override
description: How the published app uses the oneinme-prod RDS while dev keeps oneinme; why env-var routes failed.
---

The published app talks to a separate production RDS host while the dev workspace keeps the original one.

**Rule:** the split is implemented in each artifact's `[services.production.run]` shell prelude — if the global secret `PROD_DATABASE_URL` is set, it exports `DB_HOST` (parsing out the hostname if a full `postgresql://` URL is stored). Both the Laravel artifact and api-server artifact carry the prelude; deploy-time `migrate --force` therefore targets the PROD database.

**Why:** Replit secrets are global (not env-scoped), and `setEnvVars` refuses to set a production-scoped env var whose key already exists as a secret (`DB_HOST` conflict) and refuses runtime-managed keys (`PGHOST`). A run-command `export` is the only deterministic override; Laravel's Dotenv is immutable so process env beats the committed `.env`.

**How to apply:** any new deployable artifact that reads the DB must copy the same prelude, or it silently keeps using the dev host in production. Both servers share identical credentials/db name — only the host differs. `DB_URL` must stay unset in secrets or it would bypass `DB_HOST` in Laravel.
