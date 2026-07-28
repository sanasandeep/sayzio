---
name: Workflow-count bloat wedges environment rebuild
description: Too many registered workflows makes every restart_workflow time out "waiting for run environment to rebuild"
---
Every `restart_workflow` timed out for over an hour with "run command timed out waiting for run environment to rebuild", even with the machine idle and no merges holding a lock.

**Why:** the project had accumulated ~170 registered workflows (platform expects ≤10; each one-off test/check runner registered over time). That bloat wedges workflow reconciliation / the run-environment rebuild, so restarts never complete. Post-merge "Workflow reconciliation failed: CANCEL" is a matching symptom.

**How to apply:** when restarts hang on "waiting for run environment to rebuild" and merges aren't active, run `listWorkflows()` — if the count is large, prune everything except the real artifact dev servers via `removeWorkflow` (batch in code_execution). Restarts succeed immediately afterwards. Prefer bash/validation runs for one-off tests instead of creating workflows.
