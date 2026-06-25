---
name: Block allowlist gating vs editor aliases
description: Why plan block-type gating must NOT use BlockTypeRegistry::canonical(); two separate synonym maps.
---

Plan `block_types_allowed` gating (`User::userCanUseBlockType`) and the AI
block catalog (`AiBiolinkBuilderService::blockCatalog`) match the **raw**
block-type string with `in_array(..., true)`.

**Trap:** several slugs are registered BOTH as real `BiolinkBlock::TYPES`
keys AND as `BlockTypeRegistry::ALIASES` entries that collapse onto another
type — `cta_button`→link, `link_big`→link, `paragraph`→paragraph_rich,
`markdown`→paragraph_rich. `ALIASES` exists for editor mode-prefill /
consolidation, NOT for gating. So calling `BlockTypeRegistry::canonical()`
in the gate would merge cta_button into link and let it through when only
`link` is allowed — breaking `BiolinkAiPlanBlockTypesTest` (which asserts
cta_button DENIED while link ALLOWED).

**Rule:** gating uses a separate `ALLOWLIST_ALIASES` map that only resolves
the 4 friendly *non-type* synonyms that never appear in TYPES
(`link_button`→link, `social_icons`→socials, `tiktok`→tiktok_video+
tiktok_profile, `twitter`→twitter_profile+twitter_tweet+twitter_video).
`canonicalizeAllowlist()` expands those and passes every real type through
UNCHANGED; the requested slug is matched raw. Pricing display also calls
`canonicalizeAllowlist()` then labels from the full `TYPES` map (incl.
`_alias` entries like paragraph→"Text") so seeded plans need no humanize
fallback.

**Why:** the AI builder emits `paragraph` (not paragraph_rich) while the
editor POSTs `paragraph_rich`; they are gated as distinct units on purpose.
Don't "fix" this by collapsing — it regresses AI builds. (Real unification
is a separate follow-up.)
