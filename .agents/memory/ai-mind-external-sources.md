---
name: AI Mind external sources (webhook + connector)
description: Durable design decisions for the inbound-webhook and outbound-connector Knowledge-Base source types.
---

# AI Mind external sync sources — design decisions

Knowledge Bases (AI Minds) sync from outside systems via two source types beyond
text/faq/document/link/feature: an inbound **webhook** (push) and an outbound
**API connector** (scheduled pull).

- **No schema migration for new source types.** Per-type config + secrets live in
  the existing `meta` JSON column; `url`/`refresh_minutes` columns and a string
  `type` already exist. **Why:** keeps additive changes safe on the shared RDS and
  avoids migration backlog risk. **How to apply:** add future source variants the
  same way — extend `meta`, don't alter columns.

- **Webhook tokens are one-time reveal, not persistently displayed.** Store only
  the `Crypt`-encrypted token; show the plaintext exactly once via a flashed
  session value right after create/rotate, masked thereafter, with a Regenerate
  action. **Why:** a code review REJECTED an earlier version that re-decrypted and
  rendered the token on every edit-page load (screen-share / shoulder-surf leak).
  **How to apply:** any "secret a user must copy" follows reveal-once + rotate,
  never re-render the stored secret. Connector credentials are write-only the same
  way (blank on save = keep existing).

- **Push vs scheduled.** Webhooks never schedule and dispatch no ingest on create
  (nothing to ingest until first delivery); connectors dispatch ingest on create
  and are included in the scheduled link-refresh sweep. Token auth uses
  `hash_equals`; the inbound route is a CSRF-exempt public web route (NOT an
  `/api/v1` Sanctum surface — so document it in knowledge-base.md, not api.md).

- **SSRF guard is mandatory on any user-supplied fetch target.** Both connector
  setup and outbound fetch refuse private/local hosts (shared with the link
  crawler). **How to apply:** never fetch a user-supplied URL without the
  private-host check.

- **Verify without DB over distant RDS.** Authed HTTP/tinker are unreliable here;
  boot via `bootstrap/app.php` + the console Kernel in a standalone PHP script and
  exercise model accessors / ingestor methods directly (encryption round-trips,
  SSRF guard, JSON→text flattening, credential preservation).
