---
name: PricingResolver admin-guard TypeError on public pages
description: Active admin-guard session makes the default guard return Admin on public pages, crashing ?User typehints like PricingResolver::currencyForUser.
---

# PricingResolver admin-guard TypeError

With an active `admin`-guard session, `$request->user()` on a public route returns an `Admin` model. Any call passing that into a `?App\Modules\User\Models\User` typehint (notably `PricingResolver::currencyForUser()` / `currencySourceForUser()`) throws a TypeError → 500.

**Rule:** public marketing controllers must resolve the visitor explicitly via `$request->user('web')`, never the default guard.

**Fixed surfaces:** `/` (HomeController) and `/pricing` (PricingPagesController). Pinned by `tests/Feature/PublicPagesAdminGuardTest.php` (renders both while `actingAs($admin, 'admin')`).

**How to apply:** when adding a public page that feeds the current user into PricingResolver or anything User-typed, use `user('web')`. Other public controllers using `$request->user()` only for id comparisons/viewer checks are unaffected (no typed boundary).
