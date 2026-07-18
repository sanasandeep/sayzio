---
name: storage-url-resolve guard
description: Static guard forcing payload builders to route storage-backed image columns through PublicStorageUrl::resolve()
---

Rule: any payload builder under `artifacts/1inme/app/{Services,Modules/{Api,Common,User,Admin}}` emitting a storage-backed image column (avatar, image, cover_image, logo, favicon, og_image, banner, photo, thumbnail) must wrap it in `\App\Support\PublicStorageUrl::resolve()`. Blade templates are covered too: a raw `{{ $x->avatar }}` / `{!! … !!}` echo of a storage column anywhere under `resources/views` is flagged (`scanBladeSource`); `old(...)` form-repopulation echoes and non-echo `@if(...)` reads are auto-exempt, round-trip form inputs go in the shared ALLOWLIST.

**Why:** raw `/storage/...` values go through the slow 302 bridge route instead of the CloudFront CDN; the regression renders fine and is invisible to QA — only slow.

**How to apply:** the guard `scripts/src/check-storage-url-resolve.ts` (`check:storage-url-resolve` script, `storage-url-resolve` validation workflow) scans line-by-line. Intentional raw emissions (external OAuth avatar URLs, editor round-trips like SplashPage::toArray) go in its ALLOWLIST with a reason — never weaken the matcher. `_url`-suffixed properties and method calls are auto-exempt (they store full URLs / own their resolution). Stale allowlist needles fail the run. Companion vitest suite pins matcher behavior and asserts the live tree is clean.
