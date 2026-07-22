---
name: Block defaults blank content semantics
description: How admin block-defaults handle explicit blanks, the start_blank flag, and the JSON-textarea-as-single-source editor pattern.
---

# Block defaults blank content

**Rules:**
- Explicit empty-string/empty-array keys in an admin content override are REAL blanks: `contentForType()` merges via `array_replace`, so a keyed `''` survives; only a fully-missing key falls back to the system default. Never "clean" empties out of the override before saving.
- Per-type `start_blank` flag blanks the seeded sample baseline (strings→`''`, arrays→`[]`) but preserves structural keys (`BlockDefaults::structuralContentKeys()`: layout/size/color/toggle-type keys) plus all bool/number values. Content overrides then apply on top.
- `_placeholder` is recomputed after merging: it is only kept when actual sample content remains, so intentionally-blank defaults don't show the placeholder banner and don't seed `_placeholder_seed`.

**Editor pattern (admin edit page):** the JSON textarea (`content_json`) is the ONLY submitted content field. Friendly per-key inputs have no `name` attrs — they mutate an Alpine `contentData` object and re-serialize into the textarea (empty object → empty string), with a `syncing` flag breaking the watch loop when reading back. Reuse this pattern rather than adding parallel form fields (duplicate names risk the hidden-legacy-fields clobber).

**Why:** admins need to ship blank-by-default block types; the earlier merge treated empties like missing and always re-flagged `_placeholder`.

**Runtime coverage:** web public-page blank-vs-missing semantics are pinned by `tests/Feature/PublicPageBlankContentRenderTest.php` (CTA + tip jar + blanked-default→seed→render pipeline; part of the `block-defaults-blank-test` validation); mobile by `scripts/test-blank-content-render.mjs`.

**Renderer caveat:** public renderers use `?? 'Sample'` fallbacks which correctly skip on explicit `''`; but `?: 'Sample'` fallbacks would re-inject sample text on blanks. Guarded by the `blank-content-fallbacks` validation (`scripts/src/check-blank-content-fallbacks.ts`): flags `?:` on `$s[...]`/`$it[...]` reads in blade block surfaces, and mobile `pickStr()` + non-empty `?? "..."` fallback on content keys. Mobile's public renderer must use blank-aware `pickContentStr()` for content text (its `pickStr` collapses `''` to null, which was the mobile offender). Real-data fallbacks (e.g. label ?: address) go in the guard ALLOWLIST.

**How to apply:** any new surface reading admin block defaults must go through `contentForType()`/`seededSettings()`; when adding content keys decide if they are structural and update `STRUCTURAL_CONTENT_KEYS`.
