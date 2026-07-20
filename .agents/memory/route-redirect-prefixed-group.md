---
name: Route::redirect in prefixed groups
description: Redirect destinations inside a prefixed route group must be absolute (leading /) or browsers resolve them relative and double the prefix.
---

**Rule:** Any `Route::redirect($from, $to)` registered inside a `prefix('user')` (or other) group must use an absolute `$to` starting with `/` (or a route-generated URL).

**Why:** A relative destination like `'user/settings/creator'` emits a relative `Location:` header; the browser resolves it against the current path, producing `/user/user/settings/...` → 404. This silently broke ALL legacy Settings-hub redirects until curled.

**How to apply:** When adding legacy-path redirects, always lead with `/`. Verify with `curl -w "%{redirect_url}"` against the dev server.

**Related:** `SettingsHubLegacyRedirectsTest` guards the mapping — but note PHPUnit 12 requires `#[DataProvider('...')]` attributes; old `@dataProvider` annotations make tests fail with ArgumentCountError (0 assertions), which can mask real coverage as "never actually ran".
