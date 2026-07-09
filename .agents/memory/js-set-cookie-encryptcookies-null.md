---
name: JS-set plain cookies are nulled by EncryptCookies
description: Client-side document.cookie values (e.g. ap_type_{link_id}) need a prefix exclusion from cookie encryption or $request->cookie() always returns null.
---

# JS-set plain cookies are nulled by EncryptCookies

Any cookie written client-side via `document.cookie` (audience-prompt `ap_type_{link_id}` persona cookies) carries no Laravel encryption envelope, so the web-group `EncryptCookies` middleware fails to decrypt it and silently replaces the value with **null** — server-side `$request->cookie(...)` reads always come back empty even though the browser sends the cookie.

**Why:** the base middleware only supports exact-name `except:` exclusions; dynamic per-link names can't be listed. Fix in 1inme: `App\Modules\Common\Middleware\EncryptCookies` overrides `isDisabled($name)` (must be **public**, parent signature) with a `str_starts_with($name, 'ap_type_')` prefix check, swapped in via `$middleware->web(replace: [...])` in `bootstrap/app.php`.

**How to apply:** any new client-side-written cookie the PHP side must read needs its prefix added to that subclass (or it will read null in every real browser while possibly "working" in naive tests).

**Test gotchas:**
- `postJson()`/`json()` do NOT send test cookies unless `$this->withCredentials()` is set (`prepareCookiesForJsonRequest` returns [] otherwise) — a cookie test can fail for this reason, not the middleware.
- Use `withUnencryptedCookie()` to simulate the browser's plain cookie; plain `withCookie()` encrypts and hides the bug.
