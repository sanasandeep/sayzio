---
name: Managed validation runner for 1inme Browser e2e
description: How to actually execute 1inme Playwright Browser specs when bash backgrounding is being reaped, and the post-name-gate redirect gotcha.
---

# Running 1inme Browser e2e when bash backgrounding is reaped

Sometimes the platform reaps ANY bash call that spawns a server+browser process
tree (even `nohup`/`setsid` detached, even short foreground runs): the call
returns exit 143 with *no output* (buffered stdout lost on SIGTERM). When that
happens the established backgrounded `run-validation.sh` recipe is dead for the
session.

**Reliable path:** register the spec as a validation command and run it through
the platform-managed runner, which owns the process lifecycle (no reaper):

```js
await setValidationCommand({
  name: "e2e-otp-signup-name",
  command: "bash artifacts/1inme/tests/Browser/run-validation.sh otp-signup-name-flow.spec.ts",
});
await startValidationRun({ commandIds: ["e2e-otp-signup-name"] });
```

**Why:** `run-validation.sh` boots its own dedicated `:5050` server + warms it,
so a full single-spec run takes ~5–10 min in this slow distant-RDS env. The
`code_execution` wrapper hard-times-out at 600s, but **the managed run keeps
going in the background**. Recover it via `getValidationRuns()` /
`getValidationRun({runId})`, and tail the live log at
`.local/state/workflow-logs/<runId>/validation.shell.exec.0`. On failure,
Playwright writes `error-context.md` + `trace.zip` under
`artifacts/1inme/test-results/<test-dir>/` — read `error-context.md` for the
exact hung step and the "navigated to ..." line.

**How to apply:** this is also how deliverable "runs against the real e2e suite"
is satisfied — a per-spec `e2e-*` validation command IS the CI integration
(dozens already exist alongside the giant aggregate `e2e` workflow).

## Post-name-gate redirect: onboarding, not dashboard

A brand-new OTP/social signup that just cleared the mandatory-name gate
(`auth_needs_name`) is redirected to **`/user/onboarding`**, NOT
`/user/dashboard`: once the name gate clears, the onboarding gate takes over
(new account has no `onboarded_at`). `saveCompleteName` returns
`redirect()->intended(dashboard)` but the browser follows the 302 chain into
onboarding.

**How to apply:** e2e for signup→name must wait for
`/\/user\/(onboarding|dashboard)/` and assert `not /user/complete-profile`, never
`**/user/dashboard**` alone — that hangs the full budget. The RequiresName gate
test proves its point by asserting a protected route is no longer bounced to
complete-profile (it may be caught by the onboarding gate instead).

Also make the FINAL complete-profile submit `click({ noWaitAfter: true })` and
let a sibling `waitForURL` own the navigation, or a plain `click()` blocks 30s on
"waiting for scheduled navigations to finish" against the slow authenticated
re-render.

Additional gotchas (July 2026):
- startValidationRun with a `workingDirectory` param is ignored — put `cd artifacts/1inme &&` inside the command itself (exit 127 otherwise).
- If a blocking startValidationRun outlives the code_execution notebook, console output comes back EMPTY and the notebook silently dies; just re-call startValidationRun after a notebook restart (it returns the finished/new run) — do NOT restart_workflow the validation workflow (the tool's timeout SIGTERMs it mid-suite).
- Budget e2e specs that visit several heavy editor pages in one test at ~600s: each sub-page cold render over the distant RDS is 10-40s, so seven sequential gotos blow a 180s test budget (surfaces as goto ERR_ABORTED at teardown).
