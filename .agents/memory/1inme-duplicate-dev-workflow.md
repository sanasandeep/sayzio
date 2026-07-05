---
name: 1inme duplicate dev workflow port collision
description: Why the 1inme artifact intermittently "failed to run" on port 5000
---

The 1inme Laravel dev server is defined ONCE canonically in
`artifacts/1inme/.replit-artifact/artifact.toml` `[services.development].run`, and
the artifact system runs it as the workflow named `artifacts/1inme: web` (matching
every sibling: `artifacts/1inme-com: web`, etc.). A hand-added `[[workflows.workflow]]`
named `1INME` in `.replit` (also wired into the `Project` run-button task list) once
duplicated that EXACT command — so the app was started twice on port 5000. Whichever
started second hit `Address already in use` and the artifact workflow showed FAILED,
even though the app was actually up.

**Why:** the run command's own `pkill/fuser -k 5000/tcp` port-reclaim guard makes the
two duplicates fight over 5000 instead of one cleanly failing, producing an
intermittent/confusing "artifact failed to run".

**How to apply:** the canonical 1inme dev workflow is `artifacts/1inme: web` (artifact.toml
driven). Do NOT create a second manual workflow that reruns the same serve command.
Fix a collision by `removeWorkflow({name:"1INME"})` (also strips it from the Project
run button) then restarting `artifacts/1inme: web`.
