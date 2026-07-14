---
name: withToken() leaks into later web requests in one test
description: Laravel test withToken() sets a DEFAULT header that persists onto subsequent session-based requests in the same test method.
---
In a Laravel feature test, `$this->withToken($t)` installs Authorization as a default header for ALL later requests in that test — including plain `->actingAs($admin,'admin')->post(...)` web calls. The stray Bearer token re-authenticates the web request as the token's user (global token-resolution middleware), so the admin action silently redirects without flashing anything (302, no `success`/`error` in session).

**How to apply:** when a test mixes API bearer calls and web/session calls, call `$this->flushHeaders()` after the API section (or pass the token per-request via headers array).
