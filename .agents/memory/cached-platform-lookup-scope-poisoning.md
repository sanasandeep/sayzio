---
name: Cached platform lookups poisoned by workspace global scope
description: Cache::remember over a workspace-scoped model caches an EMPTY platform-row set when first hit inside an authenticated request.
---

# Cached platform lookups vs BelongsToWorkspace

Rule: any `Cache::remember` whose closure queries a model using the `BelongsToWorkspace` (or any user-bound) global scope for PLATFORM-GLOBAL rows (`user_id IS NULL`, admin-global domains, etc.) must call `->withoutGlobalScopes()`.

**Why:** the first call inside an authenticated request has `current_workspace` bound, so the scope filters the platform rows to NOTHING — and that empty result is cached (e.g. 600s), breaking the feature platform-wide even for later unauthenticated calls. Symptom in tests: direct unit assertions pass (no auth → scope no-op) while the HTTP-request path mysteriously fails; dumping the cached map shows `[]`.

**How to apply:** when adding cached helpers on workspace-scoped models (Domain::platformHostMap / platformDomainIds are the fixed examples), add `withoutGlobalScopes()`; when debugging "works in unit context, fails via HTTP" on cached data, suspect scope-poisoned cache first.

Also from the same work: per-domain alias namespaces — NULL domain_id ≡ default platform domain (sayzio.app) bucket everywhere; `AliasNamespace::{normalizeDomainId,scope,isTaken}` is the single source for alias uniqueness/resolution scoping across links.alias + link_aliases.alias.
