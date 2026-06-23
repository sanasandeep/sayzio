---
name: Biolink wizard category lockstep
description: The touchpoints that must stay in sync when adding a top-level category to the in-app Link-in-Bio wizard.
---

Adding a new top-level wizard category to the 1inme Laravel app requires edits in lockstep, or a combo silently falls back to a thin generic page:

1. `BiolinkWizardQuestions::categories()` — taxonomy entry (slug/label/icon/blurb).
2. `BiolinkWizardQuestions::pageTypes()` — sub-type array for that category slug.
3. `BiolinkWizardQuestions::questions()` `$byCombo` — a `"{category}.{pageType}"` entry per sub-type (merge `$base` identity + tailored keys).
4. `BiolinkWizardQuestions::defaultBrandColor()` — a brand color, or it falls back to the generic purple.
5. `BiolinkPageRecipes::extrasFor()` — a `case "{category}.{pageType}":` branch per sub-type producing real blocks.
6. `BiolinkWizardController::placeholder()` `$palette` — avatar SVG bg/fg/emoji keyed by category slug (and any industry slug from `industries()`), else the dark default.

**Why:** `profile_card_v1` is auto-emitted by `BiolinkPageRecipes::build()`, so the wizard test only asserts that. A missing recipe branch still "passes" but yields a near-empty page — the failure is invisible to the test.

**How to apply / verify without DB:** the taxonomy + recipe services are pure except `BiolinkPageRecipes::resolveAvatar()` which calls Laravel `url()`. Verify locally by requiring the two service files directly with a global `url()` stub defined first, then loop every category→pageType, synthesize answers from each question's `type`, call `build()`, and assert `profile_card_v1` present AND `count(blocks) >= 2`. Avoids the broken `php artisan tinker` and slow cross-region RDS boot.

**Reuse, don't reinvent:** generic helpers already auto-wire socials (instagram/tiktok/youtube/twitter/etc), `store_url`→shop CTA, `newsletter_blurb`→email_subscribe, `featured_url`/`signup_url`/`rsvp_url`/`tickets_url`/`cta_url`→top CTA, and `contactBlock()` fires on a fixed set of email keys (email, contact_email, booking_email, collab_email, host_email, …). Use those keys and you don't need explicit branches for them. Inline block shapes: `list_pricing` (settings.title + items[{name,price,description}]), `map` (settings.address,zoom), `testimonials` (settings.items[{quote,name}]). Mobile reads the whole taxonomy from the API endpoint, so no mobile code change is needed for new categories.
