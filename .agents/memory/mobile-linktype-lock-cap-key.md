---
name: Mobile per-type link lock cap-key derivation
description: mobile's proactive per-link-type plan gate assumes uniform cap keys; web's are not uniform, so it can silently fail-open
---

Mobile's proactive per-link-type plan gate (`isLinkTypeLocked` in
usePlanFeatures) derives the plan cap key **by convention** as `max_<apiType>`.
The web-authoritative gating map (LinkController's enforceLinkTypeQuota) does
**not** use uniform cap keys — a few types have bespoke keys (e.g. brand kit,
calendar) that don't match `max_<apiType>`.

**Why it matters:** where the derived key doesn't exist the cap reads
`undefined`, so the type is never proactively locked regardless of the real
allowance. It fails **open** (never a false paywall — so it's harmless while the
free plan grants ≥1 of every type), but on an exhausted paid allowance the user
gets no proactive lock and only hits a server bounce at submit.

**How to apply:** never assume `max_<type>` when tightening mobile gating —
carry the real per-type cap key, mirroring the web map. A `max_* = 0` regression
on the reachability path is caught by
`artifacts/1inme-mobile/scripts/test-pairing-create-open.mjs` (it checks the web
map's real cap keys).
