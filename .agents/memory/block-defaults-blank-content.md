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

**Renderer caveat:** public renderers use `?? 'Sample'` fallbacks which correctly skip on explicit `''`; but `?: 'Sample'` fallbacks would re-inject sample text on blanks — sweep for `?:` when adding blankable fields (block-picker preview was the one offender).

**How to apply:** any new surface reading admin block defaults must go through `contentForType()`/`seededSettings()`; when adding content keys decide if they are structural and update `STRUCTURAL_CONTENT_KEYS`.
