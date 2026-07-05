---
name: Social connection "searchable" sync fan-out
description: How a per-connection is_searchable flag reaches caller-ID, dialer finder, and a "where it's synced" summary without drifting across surfaces.
---

`SocialAccountConnection::syncSummary()` (biolink_count, on_caller_id, in_public_search, in_dialer_finder, label) is the single source of truth for "where is this connection surfaced" — every UI (web toggle row, mobile switch row, API response) reads this instead of recomputing which surfaces respect the flag.

Caller-ID (`DialerIdentity::mergeSearchableConnections`) and the dialer universal finder's Social group (`DialerSearch::socialItems`) both gate on the same `scopeSearchable()` query scope AND the same reachability set. `reachableUserIds()` was extracted out of `peopleItems()` into a shared private helper specifically so the People and Social finder groups can't silently diverge on who counts as "reachable" (self + followed + contact-linked, minus suspended/blocking).

**Why:** a toggle that's supposed to control several independent surfaces (caller-ID card, public search, dialer finder) will drift the moment one surface computes its own "is this connection visible" logic instead of sharing scope/helper code.

**How to apply:** any new surface that should honor the per-connection searchable toggle must call `SocialAccountConnection::scopeSearchable()` and, if it involves cross-user visibility, the shared `reachableUserIds()`/reachability gate — never re-derive visibility from `is_searchable` alone without the reachability check.
