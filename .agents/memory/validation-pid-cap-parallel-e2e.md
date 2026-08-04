---
name: Parallel validation e2e vs 1024-pid cgroup cap
description: Why the full validation battery fails with rotating "Resource temporarily unavailable" flakes in task envs, and how to diagnose
---

Task envs have a hard cgroup limit `/sys/fs/cgroup/pids.max = 1024` (read-only; ~320 tasks used at idle). Running ALL registered e2e validation commands in parallel (each boots its own Chromium + php/node servers) exhausts it: symptoms are `pthread_create: Resource temporarily unavailable`, `fork: retry: Resource temporarily unavailable`, `browserType.launch: Target page ... closed`, Postgres `could not fork new process for connection`.

**Why:** ~10 concurrent Chromium instances (40-80 tasks each) plus servers exceed 1024 tasks; which suite dies is random, so a DIFFERENT subset fails each validation run while every failing suite passes standalone.

**How to apply:** if completion-validation failures rotate across unrelated e2e suites with fork/thread-exhaustion messages, don't debug the suites — verify each failing one passes standalone, then either prune the registered validation commands to a relevant subset or use an audited `skip_validation_reason`. Also check the two known zio-browser env repairs first: missing `node_modules/electron/dist` (run its `install.js`) and stale `dist/` bundles (rebuild build/build:preload).
