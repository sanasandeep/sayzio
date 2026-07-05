---
name: 1inme dev workflow name
description: The actual name of the 1inme Laravel dev-server workflow, for restarting it correctly after code changes.
---

The 1inme Laravel app's PHP dev server is registered as the workflow named
`artifacts/1inme: web` (built from its `artifact.toml` service), not `1inme`,
`1INME`, or any other guessed variant.

**Why:** `restart_workflow`/`restartWorkflow` calls with a guessed name (e.g. `1inme`)
fail with `RUN_COMMAND_NOT_FOUND` even though the app is clearly running under some
workflow; grepping `.replit` for `1inme` mostly surfaces validation-gate workflows,
not the actual dev server, which is misleading.

**How to apply:** When unsure of a workflow's exact registered name, call
`listWorkflows()` via `code_execution` first to get the authoritative list (names,
commands, state) rather than guessing from `.replit` or `ps aux`. Then restart via
`restartWorkflow({ workflowName })` using the exact name returned.
