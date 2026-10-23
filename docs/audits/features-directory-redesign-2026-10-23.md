# Features directory redesign

The public features page grouped dozens of unrelated categories into a long, flat list. Its hero quoted stale category and usage counts, and stored categories prevented newly shipped feature rows from appearing.

The new directory groups the existing content into nine product areas, adds missing formats from the actual link picker, and includes AI creation, booking/commerce, event networking, custom domains and journey reporting. Cards use native disclosures; the search progressively enhances the server-rendered directory and reveals matching details. Existing category anchors, admin copy and explicit links survive. Custom admin categories get their own visible group. Newly shipped default rows are appended by normalized name without changing saved content.

## Validation

- PHPUnit `FeatureDirectoryTest`: 3 tests, 65 assertions passed. Covers stale stored content, preservation of custom copy/links, picker coverage, grouping without loss or repetition, custom categories and idempotence.
- Both changed Blade files compiled with Laravel's BladeCompiler and passed PHP syntax checks. The page rendered with both a minimal preview shell and the actual public site layout using isolated in-memory SQLite settings; no production database was used.
- PHP syntax, JavaScript syntax, whitespace, Alpine line-comment, Blade JSON-in-attribute and Blade PHP guards passed.
- Browser QA at desktop and 390 × 844: light/dark mode, search for a hidden feature, no-results state, reset and native disclosures. Document width was 390px at a 390px viewport.
- Repository-wide Blade comment-echo guard still reports three existing matches in `page-hero-appearance.blade.php` and `navigation-appearance.blade.php`; neither file is changed here.

The full PostgreSQL feature suite was not run. Preview used a production CSS asset for the shared layout rather than a fresh frontend build; the change adds its own plain CSS/JS and introduces no new Tailwind classes. Preview logo defaults were unavailable without production branding settings. Demo-link resolution retains the existing cached demo mapping, with no demo fixtures in the isolated preview.
