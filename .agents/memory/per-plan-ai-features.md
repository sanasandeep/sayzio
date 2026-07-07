---
name: Per-plan AI features
description: How the 8 first-class AI features are gated per-plan and which surfaces must stay in lockstep.
---

`App\Services\AI\AiPlanAccess` is the single source of truth for per-plan AI gating. Two shapes:
- **Quantity caps** (`max_minds`/`max_personas`/`max_companions`, -1=Unlimited): `quantityCap`/`underQuantityCap`/`quantityUpgradePlan`/`quantityLimitMessage`. Falls back to the legacy GLOBAL admin caps (AiMindSettings/PersonaSettings/CompanionSettings) when the plan row predates the key.
- **Availability bools** (`ai_widget`/`ai_voice_assistant`/`ask_coach`/`card_scan`/`ai_resume_tools`): `featureAllowed`/`featureUpgradePlan`. Reads the per-plan flag when present; otherwise legacy fallback — ask_coach/voice via AiEngineSettings allow-lists, the rest always-on.

**Why:** additive rollout over a shared RDS — un-backfilled plans must never regress, so absence of a key ≠ locked.

**How to apply:** enforce at creation chokepoints. Quantity caps go through `CheckPlanLimit` cases (`ai_minds`/`ai_personas`/`ai_companions`) on the create+store routes AND inline in the controllers. Seed values live ONLY in `PlansAndAddonsSeeder::aiFeatureLimits()`; the backfill migration reads that same method so they can't drift.

Surfaces that must stay in lockstep when adding/renaming an AI feature key:
1. `AiPlanAccess` (QUANTITY_KEYS / legacy fallbacks)
2. `PlansAndAddonsSeeder::aiFeatureLimits()` + the additive backfill migration
3. `PremiumFeatures::catalogue()` (AI suite group) — this ONE shared class drives both the web /premium-features page AND the mobile premium-features screen + `unlocked_by` resolution, so mobile needs NO code change (usePlanFeatures + plans/upgrade screens are data-driven off `/api/v1/billing/plans` features_map + premium_features).
4. Pricing matrix (`public/pricing/plans.blade.php` $matrixGroups 'AI features') + `user/upgrade/show.blade.php` number/bool label maps
5. Marketing surface removed (the standalone `1inme-com` site was deleted in July 2026)
6. Index blade counters (`user/{minds,ai-personas,ai-companions}/index.blade.php`) must treat cap===-1 as ∞/Unlimited and always allow the create button.

**Voice drift:** pricing/catalogue DISPLAY `ai_voice_assistant` per-plan, but the runtime voice gate intentionally still uses `AiEngineSettings::voiceAllowedFor()` (allow-list) to avoid regressing existing access — displayed flag and runtime gate can disagree until reconciled.
