---
name: 1inme production error fallback
description: Why the bulletproof prod error page is wired at the Blade view level, not via a bootstrap render callback.
---

The rich branded error views (`errors/{403,404,405,419,429,500,503}.blade.php`)
all delegate to `errors/_site-error`, which `@extends('public.layouts.site')` —
so they depend on the database (SitePage lookups) AND the Vite asset manifest.
Those are exactly the things that fail during the outages that trigger the error
in the first place, so the rich page itself throws and Laravel falls back to its
bare/raw screen.

**Decision:** resilience lives at the VIEW level. Each `errors/{code}.blade.php`
is a thin `@include('errors._render', ...)`. `errors/_render.blade.php` renders
the rich `_site-error` and, **only in production**, wraps it in a try/catch that
falls back to `errors/_fallback.blade.php` (fully self-contained: inline CSS, no
DB, no `@vite`, no site layout). In development it lets the rich render throw so
the real cause still surfaces via the debug screen.

**Why not a broad `bootstrap/app.php` `$exceptions->render(\Throwable …)`
callback:** a catch-all web throwable handler runs BEFORE Laravel's
`prepareException` mapping, so it would have to re-derive status codes and would
intercept ValidationException / AuthenticationException / AuthorizationException
/ redirects — risking regressions in those flows. The view-level wrap is precise:
Laravel's pipeline is untouched, rich pages stay the primary path, and the
fallback only appears when the rich page can't render.

**How to apply:** new error code → add `errors/{code}.blade.php` as an
`@include('errors._render', ['statusCode'=>X,'slug'=>'error-X'])`; never make the
fallback depend on DB/Vite/site layout. `response()->view()` renders eagerly
(Illuminate Response::setContent calls Renderable::render), so a throwing error
view IS caught inside `renderHttpException`'s try.

**Non-prod guarantee (July 2026):** outside production there is no view-level
net, so every DB-touching layout partial must individually tolerate missing
tables. Locked in by `tests/Feature/ErrorPagesResilienceTest.php` — swaps the
default connection to an EMPTY in-memory SQLite DB (no RefreshDatabase) and
renders each error status. Gotchas found that way: (1) throwaway test routes
must be TWO-segment paths or the `/{alias}` catch-all swallows them and you get
the non-prod "Setup required" 503 from the missing-table QueryException
renderer in `bootstrap/app.php`; (2) cache-fallback patterns like
`catch { return $query(); }` (Domain::platformDomainIds) re-throw on a missing
table — the direct-query retry needs its own `DatabaseErrors::isMissingTable`
guard, since alias/brand-domain resolution runs on EVERY request including
while rendering the error page itself.
