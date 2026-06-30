---
name: Marketing link-type showcase sync
description: The lockstep surfaces that must change together when the 1inme marketing "link types" list gains/loses a type, and how changes reach live installs.
---

# Marketing link-type showcase sync (1inme Laravel)

Adding/removing a headline link type in the marketing surfaces is a **multi-surface lockstep**:

1. `SitePagesContent::homeLinkTypesDefault()` — home "What you can create" cards `{name,icon,color,new,desc}`.
2. `SitePagesContent::featuresCategoriesDefault()` — the `link-types` category `features[]` `{name,icon,description}`.
   Keep these two lists in the **same order**; a standalone check / the demos page assume they mirror each other.
3. `SitePagesContent::richDefaults()['features']['meta_description']` — hardcodes the count + a parenthetical list of types.
4. Home/demos blade copy hardcodes the **count word** ("Fifteen kinds of link" in `home.blade.php`, "fifteen distinct kinds" in `public/demos.blade.php`).
5. `LinkTypeExplainerSeeder::pages()` — one `/demos` explainer page per type; bump `SEED_VERSION`. The demo alias is `'demo-type-' . Str::slug($name)` where `$name` is the **showcase name** (e.g. "Resume / Portfolio" → `demo-type-resume-portfolio`). `SitePageController::demos()` only renders a card when that aliased biolink page exists, so a mismatched alias = silently missing card.

## Reaching already-seeded / live installs
**Why it matters:** `scripts/post-merge.sh` runs ONLY `migrate --force` plus a fixed set of catalog seeders — it does **not** run `SitePagesSeeder`. The home `extra.link_types` and features `sections` are seeded *only when missing*, so editing the code defaults never updates rows that already exist.

**How to apply:**
- Propagate to existing `site_pages` rows with a **migration** (precedent: `database/migrations/*_seed_features_page_categories.php`). Append only the *new* entries matched by name (case-insensitive); never rewrite admin-edited order/copy or re-add an entry an admin deleted. Guard for missing row / missing `link-types` category. A missing home row needs no action (controller falls back to code defaults).
- Demo explainer pages reach live only if `LinkTypeExplainerSeeder` is in the post-merge background seeder block — add it there (it's idempotent, refreshes untouched pages on a `SEED_VERSION` bump, creates new ones).
