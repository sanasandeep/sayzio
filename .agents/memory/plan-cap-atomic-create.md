---
name: Plan-cap atomic create
description: Per-plan quantity caps need an atomic count-and-create; the codebase pattern is a pg advisory xact lock, plus how to test it.
---

# Plan-cap atomic create

Rule: any "count rows, then insert if under the plan cap" path must serialise the critical section — completion code review REJECTS naive check-then-insert as a cap-bypass race.

**Why:** two concurrent requests at the last free slot both observe the same below-cap count and both insert, exceeding a paid entitlement.

**How to apply:** wrap in `DB::transaction`, take `select pg_advisory_xact_lock(<unique class int>, user_id)` FIRST, then recount and insert inside the lock (see MarketingPlanCalculatorController::store). Advisory locks work regardless of row visibility, so they also behave under RefreshDatabase's wrapping test transaction (where `lockForUpdate` on an uncommitted user row is invisible to other connections).

**Deterministic concurrency test pattern:** register a `Model::creating` hook (fires between the in-lock recount and the INSERT); inside it, open a raw PDO connection with `SET lock_timeout='250ms'` and try the same advisory lock — assert it times out (SQLSTATE 55P03). Note: under RefreshDatabase the xact lock stays held until the test's outer transaction ends, but same-session re-acquire is fine, so later sequential stores in the same test still work.
