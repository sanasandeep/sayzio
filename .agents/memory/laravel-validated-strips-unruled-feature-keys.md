---
name: Laravel validated() strips un-ruled nested array keys (plan features)
description: Why plan saves silently wiped module_* toggles, and the raw-input pattern PlanWriter now uses.
---

# Laravel validated() strips un-ruled nested array keys

**Rule:** when an attribute has an `array` rule plus nested `attr.*` rules, `$request->validate()` returns ONLY the nested keys that have explicit rules — everything else in the array is silently dropped (excludeUnvalidatedArrayKeys default since Laravel 9).

**Why:** the plan form's `features[...]` blob includes `module_*` toggles and other keys with no `features.module_*` rules. PlanWriter used `$validated['features']`, so `collectFeatures()` saw them absent and coerced every module to `false` on EVERY save (single editor and bulk save), and its module-off pass then reverted all gated edits — a silent, total wipe class of bug.

**How to apply:** validate first (enforcement still 422s bad values), then assemble/normalize from the RAW `$request->input('features', [])` — `collectFeatures()` does its own coercion/allowlisting, so raw input is safe there. Also: bulk-save's `buildSyntheticPayload` must only re-emit ARRAY provider pick-lists under `integration_providers_allowed` (kinds in "all" mode are stored as the string `'*'`, which fails the per-kind `array` rule and 422s the whole plan). Regression coverage: `tests/Feature/Admin/AdminPlanBulkSaveTest.php`.

Gotcha for tests: `collectFeatures` rebuilds per-kind maps in registry order — compare with `ksort`, not `assertSame` on key order. And gated quantity edits only apply when the owning `module_*` toggle is ON.
