---
name: Premium-features catalogue single source
description: How plan-feature copy stays synced across web, API and mobile, and the guard that enforces it.
---

`PremiumFeatures` (artifacts/1inme/app/Modules/Common/Support) is the single
source of truth for plan-gated feature copy.

- `catalogue()` documents every gating key (group/name/description, plus `unit`
  for numerics). It must cover every key in `PlanFormCatalogue`
  quantityLimits()+featureFlags()+aiSuite().
- `resolveCell($plan,$entry)` is the one resolver for how a key displays for a
  plan: `{kind: number|analytics|bool, on, unlimited, text}`. Reuse it — never
  re-implement number/Unlimited/included logic. `kindOf()` classifies an entry
  (analytics key, has-unit → number, else bool).
- `planHighlights($plan)` builds ordered plain-English bullets from the same
  catalogue (used by the mobile upgrade/plans cards via the API).
- `unlocksByFeature()` lists which plan slugs unlock each key.

Consumers (keep in lockstep, but they all read the helpers so no copy is
duplicated):
- Web grid: `public/pricing/features.blade.php` (PricingPagesController@features,
  route `site.premium-features` = /premium-features) renders a sticky
  comparison grid reusing the `.feat-*` matrix CSS from `plans.blade.php`.
- API: `RevenueCatBillingController::plans()` emits `premium_features`
  (catalogue+unlocked_by) and per-plan `feature_highlights`. Raw `features`
  (array_values of gating JSON) is kept only for back-compat — render
  `feature_highlights` instead.
- Mobile: `premium-features.tsx` auto-groups `premium_features`;
  `upgrade.tsx`/`plans.tsx` render `feature_highlights`.
- Marketing (1inme-com, REMOVED July 2026): was gateway only — linked the canonical
  page via `FEATURES_URL` in `src/config.ts`; does NOT duplicate the catalogue.

**Guard:** `tests/Unit/Support/PremiumFeaturesCatalogueDriftTest.php` (plain
PHPUnit, no DB) fails if a PlanFormCatalogue key is added without catalogue
copy. Run: `vendor/bin/phpunit tests/Unit/Support/PremiumFeaturesCatalogueDriftTest.php`.
