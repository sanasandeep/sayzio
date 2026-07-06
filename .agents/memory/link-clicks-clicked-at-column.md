---
name: link_clicks timestamp column is clicked_at, never created_at
description: Why queries on link_clicks.created_at 500 only on a fresh/CI schema and where the wrong-column pattern hides.
---

# link_clicks canonical timestamp is `clicked_at`, not `created_at`

The `link_clicks` table (and the `Link::clicks()` relation that targets it) has
**`clicked_at`** as its event timestamp and has **no `created_at` column**.

Any query filtering `created_at` on this table 500s (`SQLSTATE 42703 undefined
column`) — but ONLY on a schema built fresh from migrations (i.e. CI and the
ephemeral local runner). On a long-lived/drifted DB that happened to have a
stray `created_at` it can pass silently, which is exactly how such bugs reach
main undetected.

**Why:** the drift-free local/CI DB is what exposes these; a "no drift" test-DB
deliverable will surface them as genuine app bugs, not DB problems.

**How to apply:** when writing/reviewing click analytics, always use
`clicked_at`. Two shapes hide the wrong column:
- raw `DB::table('link_clicks')->where('link_clicks.created_at', ...)`
- relation `$link->clicks()->where('created_at', ...)` (a bare `created_at`
  greps differently from `link_clicks.created_at`, so sweep for BOTH).
Correct references already exist in LinkController analytics and
SnapshotLinkPerformance (`whereBetween('clicked_at', ...)`) — copy those.
