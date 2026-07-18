---
name: API validation error envelope in feature tests
description: /api/* routes reformat validation errors, so assertJsonValidationErrors fails
---

# API validation errors are wrapped in the unified envelope

`bootstrap/app.php` has a global `$exceptions->render()` for `api/*` that turns
every `ValidationException` into `{ error: { code: "validation_failed", details: {<field>: [...]} } }` (HTTP 422).

**Why it matters:** in feature tests hitting `/api/v1/*`, Laravel's
`assertJsonValidationErrors('field')` and `assertJsonPath('errors.field')` both
FAIL — the default `errors` key doesn't exist. Assert instead:
- `->assertStatus(422)`
- `->assertJsonPath('error.code', 'validation_failed')`
- `assertArrayHasKey('field', (array) $resp->json('error.details'))`

Web (non-api) routes keep Laravel's default `errors` shape, so
`assertJsonValidationErrors` is fine there.
