---
name: Laravel facade partialMock null app
description: Facade::partialMock() on manager-style facades (Cache, etc.) builds a fresh instance with a null $app and crashes; use a proxy partial + swap.
---

# Facade::partialMock() on manager facades

Rule: never use `Cache::partialMock()` (or partialMock on any manager-style facade like Cache/Queue/Storage) when you need un-mocked calls to keep working.

**Why:** `Facade::partialMock()` constructs a NEW instance of the facade root class without constructor args, so `CacheManager::$app` is null — the first un-mocked call that resolves a store dies with "Call to a member function bound() on null".

**How to apply:** build a Mockery proxy partial around the real, bound instance and swap it in:

```php
$mock = \Mockery::mock(Cache::getFacadeRoot())->makePartial();
$mock->shouldReceive('remember')->withArgs(fn ($k) => $k === KEY)->andThrow(...);
$mock->shouldReceive('remember')->andReturnUsing(fn ($k, $ttl, $cb) => $cb()); // catch-all passthrough
Cache::swap($mock);
```

Mockery picks the FIRST matching expectation, so put the keyed throw before the catch-all. A proxy partial forwards methods without expectations to the real object, but once a method has ANY expectation, all calls to it must match one — hence the catch-all.
