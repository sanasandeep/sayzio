---
name: Plan-features raw-read guard
description: Static guard blocking new raw $plan->features reads that skip the plan-limits bypass
---
Rule: never read `$plan->features` / `plan?->features` directly in gating code. Numeric caps go through `User::getPlanFeature()`; boolean gates must short-circuit on `hasPermission('user.plan_limits.bypass')` first. A per-file ratchet guard (`composer check:plan-features-reads`, `scripts/check-plan-features-reads.php`) fails on any non-allowlisted read or count growth; baselines only shrink.

**Why:** a sweep once removed a whole class of gates that ignored the admin bypass; nothing static stopped regressions.

**How to apply:** if the guard fails, switch to the blessed accessors. Only display-only readers (pricing UI, recommenders), admin plan editors/writers, billing snapshot code, or bypass-aware infrastructure may be allowlisted — with a reason, count-capped.
