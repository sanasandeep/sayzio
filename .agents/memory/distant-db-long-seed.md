---
name: Long DB seeds over distant RDS
description: How to run slow, large Laravel seeders against the distant RDS when background processes get reaped.
---

Running a large/idempotent seeder (e.g. ExpandedPageTemplateLibrarySeeder, which inserts hundreds of rows one-by-one) against the distant RDS is slow — roughly one row per second or worse due to per-query latency — so a single `db:seed` run exceeds the bash tool's max ~120s timeout and gets killed mid-run.

**Why background detaching does not help:** `nohup`/`setsid`/`disown` processes are reaped at the bash tool-call boundary in this environment. A heartbeat test confirmed no detached process survives between tool calls. Do NOT rely on backgrounding a long seed.

**How to apply:** run the seeder foreground in bounded chunks — `timeout 115 php artisan db:seed --class=Foo --force` — and re-invoke repeatedly. This works ONLY because these seeders are idempotent with per-row autocommit (firstOrCreate / exists-check + create), so each killed run leaves its created rows committed and the next run resumes. Exit code 124 = still more to do; exit 0 = fully done. Do NOT wrap the run in a single transaction to "speed it up": a kill would roll back the whole chunk and destroy resumability. Verify completion by the seeder's own target invariant (e.g. every persona has >= N rows), not just a row count.
