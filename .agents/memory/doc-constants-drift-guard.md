---
name: doc-constants drift guard (docs -> source)
description: What the check-doc-constants validation guards and how it differs from mobile-docs-parity
---

The `doc-constants` validation (`scripts/src/check-doc-constants.ts`,
`check:doc-constants`) cross-references a CURATED list of high-stakes
constants that the 5 docs (`artifacts/1inme/docs/`: chatbot-training,
claude-training, knowledge-base, features, api) name verbatim in
`backticks`, against constant sets it PARSES LIVE from PHP source
(AiFeatureCatalog::FEATURES, Link::TYPE_*/LinkTypeCategories,
BiolinkBlock::TYPES/BlockTypeRegistry::NEW_TYPES, PremiumFeatures catalogue).

FAILS on: `missing-from-source` (documented constant no longer in code —
the drift class) or `missing-from-docs` (curated entry the docs stopped
naming — prune it). Empty parsed set ⇒ exit 2 (never silently no-op).

**Why:** stale doc constants make Ask Zio / the API reference hand users
slugs/keys that no longer exist.

**Direction matters:** this is the REVERSE of `mobile-docs-parity` (which
ensures new web types get a doc mention). They do not overlap — keep both.

**How to apply:** when you rename/remove an AI feature key, links.type
slug, block-type id, or plan feature key, either grep the 5 docs and update
the name, or drop the curated entry. Curated list = only load-bearing
identifiers, not every enum value.
