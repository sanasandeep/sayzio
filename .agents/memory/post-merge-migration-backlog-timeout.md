---
name: Post-merge "river CANCEL"/timeout from migration backlog
description: Why 1inme post-merge setup intermittently fails after rapid merge bursts, and how to recover.
---

Post-merge setup failing with "Error in river, code: CANCEL" or "Post-merge setup timed out" after a burst of merges is almost never a script bug — `scripts/post-merge.sh` is idempotent and fine.

**Cause:** Rapid back-to-back merges cancel each other's post-merge runs. The Laravel `migrate --force` step then never applies new migrations, so a backlog accumulates. Applying many migrations over the cross-region RDS (~250ms/query) eventually exceeds the post-merge timeout, which cancels the next run — a self-reinforcing loop. A normal run with "Nothing to migrate" is ~70s; a 34-migration + 20-orphan backlog took ~352s and blew the old 300s timeout.

**How to recover:**
1. Run `runPostMergeSetup()` manually (code_execution) to drain the backlog. Confirm the stdout log ends with `Done — N applied, M reconciled, 0 pending.`
2. If it timed out, raise the timeout: `setPostMergeConfig({ timeoutMs: ... })`. It was bumped 300000 → 600000 to give headroom for future bursts.
3. Re-run `runPostMergeSetup()` to confirm `success: true` (a drained backlog is strong evidence the condition changed, so re-running is justified).

**Why:** the migrate step is inherently slow over the distant RDS; a one-time backlog can dwarf a normal run, so the timeout must accommodate the worst realistic burst, not the steady-state ~70s.
