---
name: Dialer universal search scaling
description: How DialerSearch stays fast at scale — trigram indexes + batched visibility, and the batch-path invariant in canViewLink.
---

# Dialer universal finder scaling

`DialerSearch::universal()` runs many per-keystroke `ILIKE '%term%'` scans across
contacts / links / users / link_aliases plus per-link visibility gating.

## Trigram indexes back the ILIKE scans
- `pg_trgm` extension is enabled; GIN `gin_trgm_ops` indexes exist on the hot
  substring-search columns (contacts display/given/family/organization, links
  alias/title/seo_title/verified_name, link_aliases.alias, users name/handle).
- Migration is pgsql-only, best-effort (try/catch around `CREATE EXTENSION` so a
  locked-down role can't break it), and `CONCURRENTLY` + `$withinTransaction=false`
  so it never blocks writes on the shared RDS.
- A btree index CANNOT serve `ILIKE '%x%'`; only trigram GIN can. On tiny tables
  the planner still picks a seq scan (correct) — the index only matters at scale.

## canViewLink batch-path invariant (subtle)
`canViewLink($viewer, $link, $subscribedCreatorIds)` has two modes:
- `$subscribedCreatorIds === null` → legacy per-link `exists()` queries (an N+1).
- non-null → **batch path**, only called from `followedLinkItems()` where the
  viewer already follows every link's creator.
  **Why:** because every creator is followed, `followers` visibility ALWAYS
  passes in this path (returns true without a query); only `subscribers` needs a
  check, and that set is pre-fetched once via `subscribedCreatorIds()`.
  **How to apply:** never call the batch path for links whose creators the viewer
  does NOT follow — the `followers` short-circuit would wrongly grant access.

## Environment note
Cross-region RDS makes each query ~250ms, so `universal()` runs several seconds
in this workspace regardless of indexes. That is network latency, not the search
logic; don't chase it as a bug. Indexes prevent seq-scan blowup as row counts
grow. Frontend already debounces (200/220ms) and GROUP_LIMIT caps payload at 12.
