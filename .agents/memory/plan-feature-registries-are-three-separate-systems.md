---
name: Plan-gating has several separate registries + call-site shapes
description: Heuristic for auditing "is this plan-gated key registered?" in 1inme without false gaps.
---

`PlanFormCatalogue::quantityLimits()+featureFlags()+aiSuite()` is only ONE of
several legitimate registries, and gated keys are read via many call-site
shapes. Before declaring a key an unregistered "gap", check all of them.

**Why:** an audit converged only after multiple grep sweeps — several keys
that looked missing were actually documented in a *different* catalogue
method, and several enforcement reads used a syntax the first scan didn't
match.

**How to apply:**
- Other canonical `PlanFormCatalogue` registries beyond the three above:
  `modules()`, `moduleKeys()` (e.g. teams/workspace/integration caps live
  ONLY here), `aiCoinMultipliers()` (deliberately excluded from `aiSuite()`),
  `aliasLinkTypes()`.
- Enforcement call-site shapes to scan for: accessor family
  (`getPlanFeature`/`planFeatureEnabled`/…), direct `features['key']` read in
  ALL forms (`$features[...]`, `->features[...]`, `?->features[...]`),
  `AiPlanAccess` literal AND `ClassName::FEATURE`-constant form, quantity/coin
  short-alias maps (real key differs from alias), and runtime-concatenated
  keys like `'ai_staff_' . $domain` (reconstruct from the enum).
- AI-credit metering keys (`AiFeatureCatalog`/`AiEngineSettings` FEATURES) are
  a DIFFERENT registry (coin cost, not plan availability) — never conflate.
