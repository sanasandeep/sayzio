---
name: API public form submit delegates to web
description: Why POST /api/v1/forms/{id}/submit reuses the web FormController instead of reimplementing
---

The public REST form-submission endpoint (`POST /api/v1/forms/{id}/submit`,
Api\FormController::publicSubmit) delegates to the canonical web
`User\Controllers\FormController::publicSubmit($request, $slug)` rather than
reimplementing the submit pipeline.

**Why:** the web submit flow is ~300 lines spanning validation, captcha, spam
heuristics, pricing/payment, CRM fan-out, and owner notifications — including
the repeatable-group collect logic (`rep_{id}[idx][childId]` → `{_repeatable_group, copies}`).
Reimplementing that in the API layer (the pattern used by Api\FormController::store)
would risk money-path drift. Delegation guarantees byte-identical behaviour.

**How to apply:** resolve the form by id (active) in the Api controller, force
`Accept: application/json` on the request so the web method takes its JSON
branches (success `{ok,message,redirect}` / `payment_required` / captcha error),
then call the web controller via `app(...)->publicSubmit(...)`. Validation errors
throw ValidationException, which the global `api/*` handler in bootstrap/app.php
already wraps into `{error:{code:'validation_failed',details}}` (422). Contrast:
math (session) captcha can't be satisfied statelessly — those forms reject API
submits; honeypot + HTTP-verified captcha providers still work.
