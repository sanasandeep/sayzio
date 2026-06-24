---
name: Per-plan stats history retention
description: How analytics retention clamping + the global prune command treat unconfigured plans differently (and why).
---

Per-plan `stats_retention_days` plan feature: `-1` = unlimited/forever, else a day count with a 30-day floor enforced in `PlanWriter::collectFeatures()` (any positive value < 30 → 30). Floor is server-side only; the admin form's catalogue entry must NOT set HTML `min`, or the `∞`(-1) button breaks.

**Two consumers default the missing key oppositely — on purpose:**
- VIEW clamp (`User::statsRetentionDays()`, used in `LinkController::resolveAnalyticsRange`): missing key → 30 (restrictive). Non-destructive, so a conservative floor is fine. Bypass permission (`user.plan_limits.bypass`) returns -1 (no clamp).
- PRUNE (`stats:prune-history` command, `largestRetentionDays()`): missing key OR explicit -1 on ANY active plan → return -1 = **no-op**. This is the destructive path, so an un-seeded/un-saved plan must never trigger mass deletion.

**Why:** the seeder converges the new key onto existing plan rows via overlay (adds missing keys), but the daily scheduled prune could run before convergence. Defaulting the destructive path to "keep forever" prevents accidental deletion of click/session history older than 30 days on a shared RDS.

**How to apply:** pruning is GLOBAL-max across plans (tables aren't plan-partitioned), so it only deletes rows older than the most generous plan's window. With the seeded lineup (free=365, all paid=-1) the command is a permanent no-op — that's correct, matching marketing "Forever on paid plans; 12 months on Free." It activates only if an admin sets explicit finite windows on every active plan. Prune is chunked (select ids → whereIn delete) because Postgres has no `DELETE ... LIMIT`.
