---
name: Seeder-owned text ownership without HTML markers
description: SafeHtml-rendered markdown shows HTML comments as visible text; detect "seeder still owns this row" by exact content match, not hidden markers
---

Rule: never stamp stored markdown/notes with an HTML-comment marker (e.g. `<!-- seeded-notes:v1 -->`) to track "seeder-owned vs admin-edited" — the admin `SafeHtml::render` pipeline escapes comments, so the marker renders as literal visible text on the page.

**Why:** the Versions & Releases curated-notes seeder first shipped a marker suffix; screenshots showed `<!-- seeded-notes:v1 -->` printed under every changelog entry (July 2026).

**How to apply:** detect untouched rows by exact (trimmed) content match against the current curated texts plus a `LEGACY_CURATED_NOTES` list of prior snapshots (append outgoing copy there whenever curated text changes). Keep recognising the old marker regex so early-stamped rows still refresh. Pattern lives in `ReleasesSeeder::isUntouchedSeedNotes`.
