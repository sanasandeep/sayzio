---
name: New marketing pages need a prod site_pages row after publish
description: Seeder-driven marketing pages 404 in production until their site_pages row is inserted into the prod RDS
---

New seeder-backed marketing pages (SitePagesSeeder / SitePagesContent) return 404 on BOTH prod surfaces (sayzio.app EC2 and sayzio.link Replit deploy — they share the prod RDS) until the `site_pages` row exists there.

**Why:** the route renders from the DB row; seeders/heal migrations that already ran on prod won't re-run to add new slugs.

**How to apply:** after publishing a release that adds a marketing page slug, insert just that row into prod via tinker with `config(['database.connections.pgsql.host' => trim(getenv('PROD_DATABASE_URL'))]) ; DB::purge('pgsql')`. Notes:
- `PROD_DATABASE_URL` is the bare HOSTNAME only (not a URL); all other creds come from the shared DB_* env.
- Prod `site_pages` schema lags dev (e.g. no `is_published`); list prod columns first and insert only matching ones (`show_toc` is NOT NULL — pass false).
- Tinker over cross-region RDS is slow: run backgrounded with a log + sleep, or the sandbox kills the call with no output.
