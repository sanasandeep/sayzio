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
carry the real per-type cap key, mirroring the web map. `usePlanFeatures` now has
`MODULE_KEY_BY_TYPE` / `CAP_KEY_BY_TYPE` override maps (keyed by mobile apiType)
for the non-uniform types; add an entry there rather than assuming the convention.
Currently: `event` (calendar) -> `module_calendar`/`max_calendars`, `brand_kit`
cap -> `max_brand_kit_pages`. The pairing test asserts each override equals the
web gating-map key, so a drift fails CI. A `max_* = 0` regression on the
reachability path is also caught by
`artifacts/1inme-mobile/scripts/test-pairing-create-open.mjs`.

**Which-types-are-gated drift** is a separate risk from the cap-key derivation:
mobile's `GATED_LINK_TYPES` set is a hand-maintained subset, so a newly
web-gated, mobile-creatable type (e.g. `store_menu`, added later alongside
`restaurant_menu`) can be forgotten and only bounce at submit. `store_menu` uses
the uniform `max_store_menu` cap so it just needs to be in the set. The same
pairing test now enforces a forward+reverse parity: every web-gated type mobile
can create must be in `GATED_LINK_TYPES`, and vice-versa. `calendar` (mobile
apiType `event`) used to be whitelisted as `MOBILE_GATE_UNEXPRESSIBLE` because its
`module_calendar`/`max_calendars` keys mismatch `module_event`/`max_event`; it is
now expressed via the override maps above, so `MOBILE_GATE_UNEXPRESSIBLE` is empty
and the reverse-parity loop maps apiType->webType (via inverted kind->apiType)
before the gating-map lookup.
