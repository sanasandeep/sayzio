---
name: post-merge "river CANCEL" failures
description: what river CANCEL means for the shared-RDS post-merge job and why it isn't a script bug
---

`Post-merge setup failed: Error in river, code: CANCEL` is the platform merge
orchestrator cancelling the post-merge job **externally** — it is NOT the
`scripts/post-merge.sh` erroring internally (every risky command in that script
is guarded with `|| echo` / `|| true`, and `set -e` would surface a different,
named failure). It clusters during bursts of rapid back-to-back merges.

**Why it's effectively self-healing:** the script is idempotent (additive
`migrate --force` → `db:reconcile-migrations` → best-effort seeders). After every
observed CANCEL the *next* merge's post-merge runs clean and reconciles anything
the cancelled run missed. A cancelled run causes no data harm.

**The only lever you control = foreground/critical-path runtime.** A shorter
gating run shrinks the window in which a cancel can land. Concretely: keep ALL
best-effort, idempotent, not-required-to-serve seeders (CardTemplateSeeder + the
plan/addon + onboarding page-template seeders) detached in the single background
`nohup` block, never on the foreground path. This also keeps their stack traces
(e.g. CardTemplateSeeder when a concurrent merge has the schema mid-apply) OUT of
the gating stdout, where they previously looked like failures.

**What does NOT help:** trying to make the script "catch" the cancel (it's an
external SIGTERM/context-cancel, not catchable); and you can't drop the drizzle
`push` / `migrate` from the critical path — those are the actual schema sync.

**How to apply:** if you see one or two `river CANCEL`s interspersed with clean
runs, do nothing — it's transient and self-heals. Only act if the gating run is
doing avoidable foreground work that can be safely backgrounded.
