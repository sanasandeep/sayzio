---
name: Laravel Cache::remember null = MISS over distant RDS
description: A cached null is treated as a cache miss and re-queries every request; deadly over cross-region RDS
---

- `Cache::remember($k, $ttl, fn)` treats a cached `null` as a MISS, so any key whose value is legitimately null/absent re-runs the callback on EVERY request — the value is never actually cached.
- In 1inme this made `AppSetting::get()` re-query ~15 unset settings from cross-region AWS RDS on every request → ~16s boot for even a trivial endpoint. Fix: cache a non-null sentinel (private const string) and map it back to the caller's default on read. Boot 16s → 0.06s; warm home render ~6.3s → ~1.8s.
- **Why:** correctness-shaped perf bug — looks like working cache code, silently degrades to per-request DB hammering only when the value is null AND the DB is far away.
- **How to apply:** audit any hot-path `Cache::remember()` / config reader that can return null or "absent" over the distant RDS; cache a sentinel (or a `{found, value}` shape), never raw null. Likely siblings: other `AppSetting`/`PlatformServiceSettings`/`config()`-style readers and negative lookups (e.g. host→branding "no match"), which should cache a `false`/sentinel for the miss too.
