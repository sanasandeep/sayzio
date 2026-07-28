---
name: Template preview thumbnails are dynamic SVGs
description: How seeded page-template thumbnails are rendered and why thumbnail_url is stored relative
---

Seeded page templates get preview thumbnails from `GET /template-thumbs/{slug}.svg?v=SEED_VERSION`, rendered live by `TemplateThumbnailRenderer` from the row's own snapshot (theme + block skeleton). No files on disk, no screenshots.

Rules:
- **Store `thumbnail_url` root-relative** (`/template-thumbs/...`). `url()` at seed time bakes the seeding host (e.g. `localhost:5000`) into the DB. A `PageTemplate` accessor absolutizes relative paths at read time so web, API, and mobile all get the current host; absolute URLs (admin uploads) pass through.
- **Why:** rows must be portable across dev/prod hosts, and mobile `<Image uri>` needs an absolute URL.
- Two templates with identical themes render identical SVGs; the renderer seeds glow-circle geometry from `crc32(slug)` so cards stay visually distinct.
- Seeder auto-refresh preserves rows whose `updated_at` drifted from `created_at` (treated as admin-edited) — repeated dev seeder runs can strand old rows; fix by direct update or accept the drift.
