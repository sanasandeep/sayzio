---
name: Per-card DB queries in Blade loops
description: Any relation query inside a large Blade card loop multiplies distant-RDS latency into minutes; hoist invariant queries above the loop.
---

The rule: never call a query (`->exists()`, `->count()`, relation loads) inside a Blade `@foreach` over a large collection when the result doesn't depend on the loop item. With ~400 template cards and ~250-775ms per query to the distant dev RDS, one in-loop `$link->biolinkBlocks()->exists()` made the templates picker take minutes and time out e2e `page.goto` (120s).

**Why:** dev env talks to a distant AWS RDS; each query is ~0.8s flat, so N-per-row patterns that are invisible on local Postgres become page-breaking.

**How to apply:** hoist invariant queries into the top `@php` block (or the controller). When profiling a slow Blade page, count queries with `DB::listen` — a raw `php -r` render harness needs `view()->share('errors', new ViewErrorBag)` and `$req->setLaravelSession(app('session.store'))` or layout partials throw. Remaining ~25s warm render on such pages is mostly flat per-query dev latency (~30 layout queries × 0.8s), not a product bug.

Related e2e gotcha: a form POST whose redirect chain exceeds 30s breaks Playwright `click()` auto-wait — use `click({ noWaitAfter: true })` + `page.waitForURL(..., { waitUntil: 'commit', timeout: large })`.
