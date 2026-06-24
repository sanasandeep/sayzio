---
name: Internal (admin-only) plans
description: How "internal" plans are hidden from self-serve surfaces while staying admin-assignable.
---

Plans carry an `is_internal` boolean. The canonical filter is the Eloquent
scope `Plan::scopePublic()` (`where is_internal = false`), chained after
`active()`/status filters on every self-serve surface.

**Rule:** any NEW user-facing surface that lists/suggests a plan for
self-serve purchase or upgrade MUST add `->public()`. Admin listings and
the assign-plan picker MUST NOT (internal plans stay visible/assignable there).

**Why:** internal plans (comp/unlimited/private) must never appear in public
pricing, in-app upgrade, the smart-upgrade recommender, or any "Upgrade to
<plan>" banner — but admins/staff still assign them to users.

**How to apply:** surfaces filtered with `->public()` today: PricingPagesController
(plans+features), UpgradeController, HomeController landing, SitePageController
feature-unlock, AiEngineSettings ask-coach/voice upgrade suggesters,
User::planThatUnlocks(), and a defensive reject inside PlanRecommender::for().
Admin duplicate() deep-copies features + polymorphic price rows + addons and
forces the copy to internal + inactive so it can never accidentally go live.
