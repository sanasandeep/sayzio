---
name: Migrations need a working down() for the CI migrate cycle
description: Why "additive-only, no destructive down" migrations still must implement down()
---

The GitHub Actions "Laravel migrations" workflow runs a migrate → rollback → migrate cycle on a fresh Postgres. A migration with an intentionally empty `down()` (justified as "additive-only against the shared RDS") leaves its tables/FKs behind on rollback, and an OLDER migration's `down()` then fails with SQLSTATE 2BP01 (dependent objects still exist) — e.g. a later-created pivot table blocking `drop table service_booking_services`.

**Why:** the additive-only shared-RDS policy governs what we RUN against prod, not what code exists; `down()` never runs on the shared DB, and SchemaManifest replay only records `up()`, so implementing `down()` is drift-safe.

**How to apply:** every new migration must implement a guarded (`hasTable`/`hasColumn`, `dropConstrainedForeignId`) `down()` that unwinds exactly what its `up()` created. Editing the `down()` of an already-applied migration is safe (unlike editing `up()`).
