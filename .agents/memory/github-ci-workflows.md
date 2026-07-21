---
name: GitHub CI workflows for sayzio repo
description: What the Laravel migrations/tests GitHub Actions checks need to stay green, and known pre-existing failures.
---

# GitHub CI (sayzio repo)

- **PHP pin**: composer.lock requires PHP >= 8.4; all workflow `php-version` pins and cache keys must say 8.4 (8.3 fails composer install).
- **PostgreSQL-only**: the MySQL matrix leg was removed from `laravel-migrations.yml` — prod is PG-only and 11+ migrations use PG-specific raw SQL (JSON defaults, prefix indexes); MySQL can never go green.
- **Vite manifest**: `public/build` is gitignored, so `laravel-tests.yml` builds assets (pnpm setup + `pnpm install --filter ./artifacts/1inme` + build) before PHPUnit, or every page-rendering test throws ViteManifestNotFoundException (~340 failures).
- **Laravel tests check still red**: the remaining ~200 failures/217 errors are pre-existing broken tests that reproduce locally (see sharded-test-runner.md) — not a CI infra issue.
- **Pushing**: use the API-squash scripts in `.local/gh-sync/` (blob/tree/commit, parent=remote tip); never raw git history.

**Why:** these three infra fixes (PHP pin, MySQL leg, Vite build) each masked the next; without notes, a future red CI looks like one mystery instead of layered causes.
