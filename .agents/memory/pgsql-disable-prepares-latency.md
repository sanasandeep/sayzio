---
name: pdo_pgsql named prepares double cross-region query latency
description: Why every simple query cost ~750ms against the distant RDS and the safe one-round-trip fix.
---

pdo_pgsql's default named prepares cost TWO network round trips per query (PQprepare + PQexecPrepared). Over the cross-region RDS (~250-370ms RTT) that means ~750ms per trivial select.

**Fix:** `PDO::PGSQL_ATTR_DISABLE_PREPARES => true` in the pgsql connection options (env-gated `DB_DISABLE_PREPARES`, default on). Uses PQexecParams — still server-side parameter binding, ONE round trip. Measured 746ms → 260ms per query.

**Why not ATTR_EMULATE_PREPARES:** emulation is also 1 round trip (285ms) but does client-side quoting — PHP `false` bound as PARAM_STR becomes `''` and breaks Postgres boolean columns. DISABLE_PREPARES is behavior-identical to native prepares (verified: same errors/results for PARAM_STR/PARAM_BOOL false bindings).

**How to apply:** any per-query latency ~2× the network RTT on pgsql is this. Also: AppSetting::get() bulk-primes all settings on the first per-key miss of a process, so cold pages no longer fan out serial single-key lookups.
