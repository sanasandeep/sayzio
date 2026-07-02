---
name: e2e gate pre-existing failure triage
description: How to attribute full-suite e2e gate failures that are unrelated to your change (brand-consistency / voice-assistant-bridge deterministic timeout pattern).
---

# Triaging full e2e-gate failures that aren't yours

The `e2e` validation gate runs the whole Browser suite (~130 tests, ~1.3h), so an unrelated pre-existing breakage fails YOUR task's gate.

**Rule:** before chasing a gate failure, build a causal-isolation case:
1. Confirm your own spec(s) PASSED in-gate (grep the run log for your test numbers).
2. Check suite ordering — a failure that ran *before* your spec's `beforeAll` seeds cannot be caused by your DB side effects.
3. Check page overlap — map which pages/partials your diff touches vs which pages the failing specs drive (grep `@include` for partials).
4. Distinguish flake vs deterministic: retries that fail at the *exact same timeout* (e.g. every test 2.3–2.4m on a 120s `waitForFunction`) = deterministic breakage, not load flake; a sibling spec passing in the same run (e.g. voice-panel green while voice-bridge all-red) confirms it's spec-specific, not box saturation.

**Why:** a July 2026 run had brand-consistency-apply-fix + all 6 voice-assistant-bridge tests failing deterministically (voice widget mount `waitForFunction([x-data^="voiceAssistant"])` 120s timeout) while 119 tests incl. the task's new spec passed — pre-existing, unrelated to the marketing-only change under review.

**How to apply:** with that evidence, call `mark_task_complete` with `skip_validation_reason` citing the isolation case; don't bisect other tasks' regressions inside an unrelated task. Poll long e2e runs by grepping the validation log for the `N passed (` summary (bash 120s cap ⇒ repeated short waits), never by blocking on startValidationRun.
