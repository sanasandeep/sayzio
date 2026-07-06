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

## Guard memoization across multiple in-process requests

When a single feature test fires **more than one** bearer-token request in the
same process (e.g. mint a token → request → mutate state → mint a new token →
request again), the auth manager memoizes the first resolved user. The second
request silently re-authenticates the **previous** user and reads *its* state,
even though you passed a fresh token — so assertions "stick" at the first
request's values (the classic symptom: post-mutation reads look unchanged, and
the payload exactly matches the earlier user's state).

**Fix (now centralized):** `Tests\TestCase::call()` is overridden to call
`$this->app['auth']->forgetGuards();` before dispatching ANY request whose
`HTTP_AUTHORIZATION` server var is a `Bearer ...` token (populated by
`withToken()` / `withHeaders()`). So new bearer-token API tests need NO manual
call — every bearer request re-resolves its token cleanly, mirroring production
(each real HTTP request is a fresh process with an empty guard cache). The
override is deliberately scoped to bearer requests only: session/`actingAs`
tests carry no Authorization header and set the user directly on the guard, so
forgetting it would drop the acting user and break them. A couple of older tests
still call `forgetGuards()` manually — harmless (idempotent), not required.

**Why not blanket-forget before every request:** `actingAs()`/`be()` sets the
user on the guard instance; a blanket `forgetGuards()` in `call()` would discard
it and every session test would 401/redirect. The `Bearer`-header gate avoids
that.

**Debugging tip:** if a request's `$u->fresh()->settings` reads stale/null while
a direct `$model->fresh()` in the test process sees the write, suspect this
(wrong user resolved) before suspecting DB transaction/connection isolation —
`DB_PERSISTENT` and read/write splitting are red herrings here.

## Globally-unique phone identifiers

`LinkedIdentifier` phone rows are globally unique — reusing one phone string
across the several users a suite creates trips a unique-constraint violation on
the second insert. Derive a distinct number per user (e.g. from `$user->id`).
