---
name: Plan-lineup migration & email-CTA principles
description: Durable rules for one-time plan-lineup data migrations and email one-click action links in the 1inme Laravel app.
---

## Recurring overlay seeder vs one-time lineup migration
The overlay seeder (PlansAndAddonsSeeder) deliberately never overwrites an existing plan `name`/fields — it preserves curator edits. **So a one-time lineup flip that repurposes an existing slug in place (e.g. `free` "Free" → "Starter", `business` "Premium" → "Business") MUST force the canonical name/sort_order in the data migration itself.** If you rely on the seeder, /admin/plans keeps showing the old labels on any DB that already had rows.

**Why:** overlay idempotency (don't clobber curator edits) directly conflicts with a deliberate rename; only the migration can resolve it.

## prices live only in the `prices` table
PricingResolver::lookupMinor reads ONLY the polymorphic `prices` table — there is NO fallback to plan->monthly_price columns. New/renamed plans render $0/₹0 unless their `prices` rows exist. Any plan-seeding path must seed prices, not just the plan row.

## Migration cost over distant RDS
A data migration that needs the new plan ids (to remap subscribers) should seed plans only inside the migration; defer the slower addon-catalog convergence to the backgrounded post-merge seeder. The full seeder is too slow to hold inside a migration/tool timeout over the cross-region RDS (background procs also get reaped — see distant-db-long-seed). Verify plans/prices render; trust prod post-merge for addons.

## Email one-click action links must be signed GET
An email CTA is a **GET with no guaranteed session** — it cannot use a POST/auth-guarded route (gives 405) nor rely on `Auth::user()`. Use `URL::temporarySignedRoute(...)` + the `signed` middleware, resolve the target user from the route param, and redirect to a sensible page afterward.
**Gotcha:** in this app the user login route is named `user.login` (inside the `Route::prefix('user')->name('user.')` group), NOT `login`; `route('login')` throws RouteNotFoundException. Keep a separate POST/auth route for the in-app banner.
