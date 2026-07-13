---
name: Post-merge blade-guard signal crashes are transient
description: How to read an exit-134/137/139 failure of a post-merge tsx guard (blade-comment-echo etc.) — it is OOM, not a real violation.
---

The post-merge script (`scripts/post-merge.sh`) runs three fatal tsx static
guards before the RDS schema sync: `check:alpine-line-comments`,
`check:blade-json-in-attr`, `check:blade-comment-echo`.

**Diagnostic shortcut:** if one of these fails post-merge with a **signal-level
exit code (>=128, e.g. 134 SIGABRT / 137 SIGKILL / 139 SIGSEGV)**, that is a
transient crash — a Node/tsx process killed under memory pressure when several
merges land back-to-back (each post-merge spawns `pnpm install` + tsx guards +
RDS sync concurrently). It is **NOT** a blade/Alpine violation. Running the
guard directly (`pnpm --filter @workspace/scripts run check:blade-comment-echo`)
will pass. Do not hunt for a nonexistent offending `.blade.php`.

A **genuine** violation exits with code **1** and prints the offending file —
that one is real, fix the blade file.

**Why:** `run_guard()` in post-merge.sh retries each guard once on exit >=128
to absorb the transient crash, while a real exit-1 violation is never retried
away (fails fast, trips `set -e`). Note the `|| code=$?` capture pattern is
required so `set -e` doesn't abort the function before the exit status is read.
