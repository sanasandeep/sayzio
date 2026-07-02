---
name: Per-alias domain dropdown (aliases-card)
description: How domain selection is wired into link_aliases and the shared aliases-card partial used across edit/vcf/ics/biolink-appearance
---

`link_aliases.domain_id` (nullable FK) lets each additional alias bind to its own custom domain, independent of the parent link's `domain_id`. `Link::resolveByAlias` scopes additional-alias lookups by their own `domain_id` via a dedicated `$extraScope` closure, mirroring the existing primary-alias `$scope` logic (same platform-host / verified-domain / null rules).

`resources/views/user/links/partials/aliases-card.blade.php` is the single shared partial rendering the primary alias, every additional alias row, and the "add alias" form — it's reused by standard edit, vCard edit, ICS edit, and biolink settings/appearance. A domain `<select>` sits in front of each alias input; each row/form persists via its own Alpine scope (primary via `updateAlias`, extra rows via `PUT links/{link}/aliases/{alias}/domain`, add-alias via a plain `domain_id` field on create).

`LinkAliasController::promote()` swaps `domain_id` between the old and new primary alias so each alias keeps its bound domain across a promotion (not just the alias string).

**Testing gotcha:** raw `curl http://localhost:5000/<alias>` for a freshly-created test link can 404 with a generic Laravel "Page not found" page even when `Link::resolveByAlias($alias, 'localhost')` returns the correct link at the Eloquent/model level — this is a pre-existing routing/host quirk unrelated to alias-domain work (RedirectController/PlatformHosts untouched). Don't chase it during alias/domain feature verification; validate via direct model calls + the mutation endpoints' JSON responses + DB read-back instead of the public redirect route.
