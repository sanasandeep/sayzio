---
name: Page template seeders (persona + starter)
description: How biolink page-template seeders refresh rows and how to bake block variant styling safely.
---

# Page template seeders

Two seeders build the biolink page-template library snapshots into `page_templates`
(`snapshot` JSON): `ExpandedPageTemplateLibrarySeeder` (persona-* slugs, ~400 rows)
and `StarterPageTemplatesSeeder` (starter-* slugs).

## Auto-refresh of untouched rows
- `firstOrCreate` does NOT update an existing row, so a redesign needs an explicit
  refresh path. Each snapshot stamps `meta.seed_version`; bump the seeder's
  `SEED_VERSION` constant and the auto-refresh deletes + recreates any seeded row
  whose stored version is older, while leaving admin-edited rows alone (edit-drift
  tolerance / unknown-slug guard).
- **Why:** lets a deploy roll the new design onto untouched rows without clobbering
  user/admin edits.
- **How to apply:** redesigning a template => bump SEED_VERSION; keep blueprint
  `key`s stable so slugs (derived from key) don't churn.

## Baking block variant styling into snapshots
- Preview renders snapshots with NO DB lookups, so the variant `_style` must be
  fully baked into each block's `settings._style` (STYLE_DEFAULTS + the
  `BlockVariantCatalog` variant style + a `_variant`/`_variant_version` stamp).
- **Variant keys are scoped per `links.type`.** `BlockVariantCatalog::find($type,$key)`
  / `forType($type)` only return keys valid for that type. `identity_*` keys apply
  to `profile_card_v1..v4`, but link variant keys live under `link`/`link_big` and
  use DIFFERENT names than other bundles (e.g. there is no `glass_pill` for `link` —
  use `frosted_pill`; no `shadow_floating` — use `card_lifted`; no `neo_brutalist` —
  use `brutalist`). Validate every key with `find()` before shipping; an unresolved
  key falls back to defaults silently (no error), so the template just loses its
  intended styling.
- **How to apply:** when adding variant kits, dump `forType('link')` etc. and confirm
  each key resolves; keep `variantStyle()` null-safe so a bad key degrades instead of
  crashing.

## Block type must have a TOP-LEVEL renderer branch (validator won't catch gaps)
- Top-level biolink blocks render through `common/biolink.blade.php`'s inline
  `@if/@elseif ($block->type === ...)` chain (loop starts ~line 1012). Card CHILD
  blocks render through a DIFFERENT renderer — the `common/partials/biolink-block-render.blade.php`
  dispatch table. Coverage differs between the two: e.g. `buy_me_coffee` is in the
  child dispatch table but has NO top-level inline branch, so a top-level
  `buy_me_coffee` renders as a blank wrapper. `image_slider`/`one_time_offer` are the
  reverse (top-level only).
- **`templates:check-designs` / `TemplateSnapshotValidator` does NOT catch this.** It
  only checks the type is in `BiolinkBlock::TYPES` and the variant key resolves.
  Membership in TYPES + BlockDefaults + the child dispatch is NOT proof a top-level
  branch exists.
- **How to apply:** before using a block as a TOP-LEVEL snapshot block, grep
  `biolink.blade.php` for an inline `$block->type === '<type>'` (or `in_array`) branch.
  No branch => pick an equivalent that has one (e.g. `donation` (branch ~1909) instead
  of `buy_me_coffee`; both 'tip' category, shape `{title,description,amounts[],url}`).

## Convergence in the cross-region dev env
- Full refresh of all ~400 persona rows exceeds the 120s tool timeout (per-row
  delete+insert over distant RDS). The refresh is idempotent: re-run
  `php artisan db:seed --class=...ExpandedPageTemplateLibrarySeeder --force` until it
  exits 0 (already-refreshed rows are skipped). Production (co-located DB) converges
  in one pass.
