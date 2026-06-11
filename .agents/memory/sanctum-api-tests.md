---
name: Sanctum API feature tests
description: Why authenticated 1INME REST API feature tests must use a real token instead of Sanctum::actingAs
---

# Authenticate 1INME API feature tests with a real Sanctum token

For feature tests hitting the `/api/v1` routes (the routes wrapped in the
`auth:sanctum` + `TouchSessionToken` middleware group), authenticate with a
real personal access token, not `Sanctum::actingAs`:

```php
$plain = $user->createToken('test')->plainTextToken;
$this->withToken($plain)->getJson('/api/v1/...');
```

**Why:** `Sanctum::actingAs($user, ['*'])` injects a *Mockery mock* as the
current access token. The `TouchSessionToken` middleware (runs after
`auth:sanctum`) calls `$token->forceFill($changes)->save()` to stamp last
IP/UA/country. On the mock, `forceFill()` returns `false`, so `->save()` blows
up with "Call to a member function save() on false" → every authenticated
request 500s. This is purely a test-harness incompatibility — in production
`currentAccessToken()` is a real `PersonalAccessToken` model and the path works
fine. Using a real token also exercises the genuine auth + token-touch path.

**How to apply:** Any new authenticated `/api/v1` feature test. Note several
pre-existing API tests (e.g. `PendingThanksApiTest`) still use
`Sanctum::actingAs` and 500 under this middleware — they are not a reliable
baseline.
