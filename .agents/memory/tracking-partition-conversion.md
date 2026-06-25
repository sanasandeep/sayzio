---
name: Tracking table partition conversion
description: How link_clicks/page_sessions get converted to monthly RANGE partitioning and the Postgres key rules that conversion must obey.
---

# Converting link_clicks / page_sessions to monthly partitions

`PartitionManager::convertToPartitioned($table,$column,$monthsAhead)` does the
faithful "twin → copy → swap" conversion in one transaction; `tracking:setup-partitions <table> --execute`
calls it (only for tables under the ~100k gate — large tables print the manual runbook).

**Why a naive copy+swap is wrong — two hard Postgres rules:**
1. A partitioned table's PRIMARY KEY and every UNIQUE key MUST include the
   partition key column. So `link_clicks_pkey (id)` → `(id, clicked_at)`,
   `page_sessions_pkey (id)` → `(id, started_at)`.
2. Unique indexes on a partitioned table CANNOT be partial. The idempotency index
   `UNIQUE (event_id) WHERE event_id IS NOT NULL` → non-partial
   `UNIQUE (event_id, clicked_at)`. Still dedupes `insertOrIgnore` re-deliveries
   (same event_id+clicked_at) and NULL event_ids never collide in a unique index.

**How to apply:** the manager introspects the live table BEFORE renaming and
recreates PK + unique constraints + unique indexes (partition key injected,
partial predicate dropped) + secondary indexes (as-is via pg_get_indexdef) + FKs
+ the owning `id` sequence. It detaches owning sequences (`OWNED BY NONE`) before
the rename so moving/dropping the old table never cascade-drops them, then
reattaches + `setval`s afterward. Old indexes are renamed out of the way so the
new table can reuse the canonical names verbatim. Original rows kept as
`<table>_old_plain` for verification, then dropped.

**Verification gotchas:**
- `event_id` is a `uuid` column; `clicked_at` exists but link_clicks has NO
  created_at/updated_at columns.
- Historical rows whose month has no dedicated partition land in the catch-all
  `<table>_pdefault` partition (current code only provisions current+future
  months); `stats:prune-history` drops whole expired month partitions then
  chunk-deletes the DEFAULT remainder.
- To test the prune O(1) drop path safely, add an EMPTY expired-month partition
  (e.g. `_p2020_01`) and run `stats:prune-history --hard-max-days=30`; it drops
  that partition without touching current data. Under "retain forever" plan
  retention, prune no-ops unless a hard cap is set.
