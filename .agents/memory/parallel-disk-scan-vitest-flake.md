---
name: Whole-tree disk-scan tests flake under vitest parallelism
description: Why the two theme guards' live-repo scans intermittently timed out, and the fix pattern for any whole-tree disk scan in tests.
---

# Whole-tree disk-scan tests flake under parallel vitest

Multiple scripts guards each walk + read the ENTIRE `artifacts/1inme/resources/views` blade tree (`listBladeFiles`). When two such test files run in vitest's default per-file parallelism, their whole-tree disk scans contend and a scan that finishes in <1s alone can blow the **5s default `testTimeout`** (observed ~5.1s) — a pure I/O false failure, no real regression. `--no-file-parallelism` or running one file alone always passes.

**Fix pattern (both applied):**
- Memoize the shared views read in `scripts/src/lib/blade-theme-scope.ts` (`readViewsFileMap()` caches a module-level `Map`). The views are a static build input during any single run, so the cache is always valid. This collapsed the pairing guard's per-target re-walks (it re-read the whole tree once per configured TARGET) to a single walk, and both `scanRepo()` (undefined-css-var) and pairing's `readViewsFileMap()` now share it.
- Belt-and-suspenders: give the specific whole-tree **live-repo** tests an explicit generous `testTimeout` (`it(..., fn, 30_000)`) so residual cross-file disk contention can't trip the 5s default.

**Why:** memoization removes redundant work + shrinks the contention window; the timeout removes the remaining sensitivity to parallel I/O jitter. Do not "fix" this by disabling file parallelism globally.

**How to apply:** any new scripts guard that walks the views tree should call the shared `readViewsFileMap()` (don't re-implement the walk), and any test asserting on a full live-repo scan should carry a generous explicit timeout.

**Node thread-exhaustion flavor (July 2026):** under the FULL parallel validation fan-out, Node itself can abort with "thread creation failed" → SIGABRT / exit 134, killing unrelated scripts gates (ci-passthrough-names, blade-comment-echo, multicol-transform) and meta-tests that spawn the gate as a subprocess (check-dialer-standalone-typecheck.test.ts sees 134-instead-of-0 on a clean tree + empty output on the poisoned run). Signature: exit 134 / SIGABRT / "thread creation" assertion in the log, and the SAME gate green in an adjacent run. It's environment resource exhaustion, not a regression — re-run or rely on the adjacent green run; don't chase the "failing" test.
