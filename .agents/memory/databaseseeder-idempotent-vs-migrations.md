---
name: DatabaseSeeder idempotency vs provisioning migrations
description: Why 1inme DatabaseSeeder must use firstOrCreate for roles/permissions/admin/domains
---
Rule: everything DatabaseSeeder inserts with a unique key (role slugs, permission slugs, admin email, global domains) must use firstOrCreate, never create().

**Why:** data migrations (e.g. the primary super-admin provisioning migration) create the `super-admin` role during `migrate`, so a fresh `migrate` + `db:seed` crashed with 23505 on Role::create for years. Fixed July 2026 (page-template purge task).

**How to apply:** when adding new reference rows to DatabaseSeeder, key on the unique column with firstOrCreate; verify with migrate + db:seed run twice on a throwaway PG (use the managed validation runner — plain bash reaps long/background  seed processes).
