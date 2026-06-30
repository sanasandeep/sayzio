---
name: AI one-click apply safety
description: Safety rules for "AI suggests → user one-click applies" flows that perform real state changes.
---

When an AI feature emits suggestions a user applies with one click/tap that
create or mutate real owned objects (links, blocks, pixels, posts), two rules
are non-negotiable and were enforced by code review:

1. **Confirm before executing.** Every impactful apply must be confirmed.
   - Web: a `confirm()` (or modal) gate before the apply request.
   - API: require an explicit `confirm` flag; without it return HTTP 409
     `confirmation_required` plus a preview of the action so the client can
     show its own confirm dialog. Mirrors how destructive actions gate.

2. **Never silently fall back to "the first / most-likely object".** When the
   AI payload is missing a target identifier (e.g. which pixel, which link),
   FAIL FAST with a clear message — do not `->first()` / `orderByDesc(...)`
   pick a default. A one-click apply that auto-picks the wrong link/pixel is a
   real, hard-to-notice mutation. Also reject ambiguous matches (e.g. two
   pixels with the same name) instead of guessing.

**Why:** automation amplifies a wrong guess into an unintended account change
the user never reviewed.

**How to apply:** applies live in a per-type applier (match on suggestion
type). Add the confirm gate at the controller layer (web + API both), and the
strict identifier validation inside each `apply<Type>()` method.
