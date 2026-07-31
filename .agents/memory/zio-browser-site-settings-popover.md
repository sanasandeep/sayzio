---
name: Zio Browser per-site settings gotchas
description: Durable rules for per-site setting storage/enforcement (default-as-null mapping, duplicated enforcement sites, TTL cache invalidation).
---
**Rule 1 — default-as-null must be one canonical mapping everywhere.** Per-site settings store the default value as NULL. UI, IPC sanitizer, and main-process fallback must all agree on which enum value IS the default; if the UI treats one value as "default→null" while enforcement falls back to a different value, that option silently becomes a no-op (this exact drift shipped once: pop-up "Block and notify" stored null while enforcement defaulted null→allow).
**How to apply:** when adding a per-site enum setting, pick the default once, and grep all three surfaces (popover handler, ipc sanitizer, tab-manager `?? fallback`) to confirm they agree; add a roundtrip test per enum value.

**Rule 2 — enforcement points are duplicated.** Tab-manager has TWO `setWindowOpenHandler` sites (main view + split view), and sessions are per-profile partitions (`persist:zio-profile-*`), so permission/display-media handlers and tracker hooks must be installed on the profile session, not just `defaultSession` — and on EVERY path that activates a partition (startup, profile switch, session pre-warm), not only at window creation. Session-level installs are idempotent, so install eagerly on all paths.

**Rule 3 — cached resolvers need explicit invalidation on write.** The site-override resolver runs on every network request, so reads go through a short-TTL cache; every settings write must invalidate it or changes lag.

**Why:** these are all silent failures — nothing crashes, the setting just doesn't take effect.
