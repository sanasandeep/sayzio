---
name: Laravel memoizes controller per route in feature tests
description: Why re-binding a mock with app()->instance() mid-test doesn't affect later requests to the same route.
---

# Laravel memoizes the resolved controller per route within a feature test

Within one Laravel feature test, the Route object persists across multiple
`$this->postJson()` calls and caches its controller instance after the first
request. Constructor-injected dependencies are therefore frozen at first hit.

**Why:** `Route::getController()` memoizes. Re-binding a service mock via
`$this->app->instance()` *after* the first request to that route silently has
no effect — the old mock keeps receiving calls, surfacing as a confusing
Mockery InvalidCountException on the *original* mock at `Mockery::close()`.

**How to apply:** bind all mocks for a route BEFORE the first request in the
test and size `times(N)` for the whole test; never re-stub a constructor-injected
service between requests to the same route. (Method-injected services are
resolved per request and are fine to re-bind.)
