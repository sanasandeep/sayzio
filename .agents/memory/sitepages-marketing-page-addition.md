---
name: Adding a new SitePagesContent marketing page
description: The lockstep steps + backfill-migration pattern for adding a new richDefaults() marketing page (e.g. a new feature's dedicated landing page) to the 1inme Laravel app.
---

Adding a new marketing landing page (like `/ai-dashboard`, `/whatsapp-agent`, `/paid-page`) is a lockstep, multi-surface change:

1. `SitePagesContent::richDefaults()` — add the slug's title/meta/sections/cta.
2. Route: register `Route::get('/{slug}', ...)->name('site.{slug}')` **before** the `{handle}` catch-all, AND add the slug to the catch-all's negative-lookahead exclusion regex, or `/{slug}` gets swallowed as a user handle lookup instead.
3. If the page needs bespoke data (not just generic heading/body sections), add a dedicated branch in `SitePageController::show()` rather than forcing it through the generic renderer.
4. Home page teaser: add a `home/partials/{slug}.blade.php` section with `id="{slug}"`, `@include` it inside `#ai-zone` (if AI-related), add a matching `.ai-zone-chip` anchor link, and update `REQUIRED_SECTION_IDS`/`AI_ZONE_PARTIAL_MARKERS` in `tests/Browser/home-section-structure.spec.ts`.
5. Nav: add to header.blade.php's relevant nav group (e.g. `$navProductAi`) and footer.blade.php's Product column.
6. **Data migration required** — `SitePagesSeeder` generically loops `richDefaults()` and upserts every slug, but it only runs on fresh installs. Existing/prod DBs need a dedicated migration that does `if (!exists) insert` for just the new slug (mirrors `2026_07_01_000001_seed_rich_footer_pages_and_social_links.php` / `2028_01_09_000001_sync_qr_code_forms_link_type_showcase.php` patterns). Forgetting this means the page 404s (missing SitePage row) on every already-seeded environment including prod.

**Why:** each of these 6 surfaces is independently discoverable by grep, but the migration-backfill step is easy to skip since local/fresh dev DBs pick the new richDefaults() entry up "for free" via the seeder, masking that prod won't.

**How to apply:** whenever asked to add a new marketing/feature landing page, budget for all 6 steps up front, and always end with a local migration dry-run (`php artisan migrate:status` to confirm it's the only pending migration, then `php artisan migrate --path=...`) plus a `php -S` + curl smoke test of both the new page and the home page teaser.
