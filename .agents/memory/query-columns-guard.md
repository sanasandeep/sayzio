---
name: Query-columns static guard
description: How the check-query-columns guard works, its precision tiers, and the real bugs it caught on first run.
---

`artifacts/1inme/scripts/check-query-columns.php` (composer `check:query-columns`, validation workflow `query-columns`) statically validates literal column names in Eloquent/query-builder calls against the SchemaManifest-derived schema (DB-free).

Three precision tiers:
1. **Rooted** `<Model>::…` fluent chains — checked precisely against the model's table; chain-following stops at join/from CHAIN_BREAKERS. This is the only tier that can catch a column that exists on a *different* table (e.g. `links.meta_title` — `meta_title` is real on `blogs`).
2. **Qualified** `table.column` literals — checked anywhere; unknown table prefix = alias, skipped.
3. **Union** — unrooted chains checked against all columns, only for methods NOT on `Illuminate\Support\Collection` (avoids `collect()->where('payload_key')` false positives).

**Alias learning (key to zero false positives):** per-file, the guard collects SQL aliases from ALL string fragments — `… as x` (including interpolated fragments that START with `" as x"` after a `$var`, and after `'` quotes like `settings->>'k' as x`) plus `selectSub(...,'alias')` last-string-arg. Aliases are exempt file-wide. `*_count`, `_{sum,avg,min,max}_`, `pivot_*` are always exempt.

**Why:** first run found 4 real dead-column bugs shipped in production paths: `workspaces` has `owner_user_id` (NOT `owner_id`/`user_id`), `follows` has `creator_id` (NOT `user_id`), `vault_credentials` has `created_by_user_id` (NOT `user_id`). Column-name guesses on these tables are a recurring bug class — check the migration before writing `where('user_id')` on them.

**How to apply:** new false positive → prefer teaching the alias learner or DYNAMIC_COLUMNS over per-site ALLOWLIST entries (which fail loudly when stale). Meta-test: `tests/Unit/Support/CheckQueryColumnsGuardTest.php`.

Also: a pre-existing parse fatal (duplicate `testGitHub()` in admin IntegrationsController) was only exposed because model discovery autoloads app classes — `php -l` sweeps don't run in CI for every file; class-loading guards double as syntax canaries.

**Pre-existing failure (July 2026):** the guard flags `where('mobile', ...)` on `users` in 6 auth controllers (AccountMergeController, OtpController, SiteAssistantController, ViewerAuthController x2, AuthController). `users.mobile` DOES exist in the live DB (`Schema::hasColumn` true) but the migration-replay-derived schema lacks it — likely applied-migration edit drift. Unrelated tasks will see query-columns FAILED; check the flagged files against your diff before blaming your change.
