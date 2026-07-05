---
name: Admin-managed event categories & hashtags
description: How EventCategories/EventHashtag work as admin CRUD-backed replacements for hardcoded event category/tag lists on the /events directory.
---

The event-category lookup helper caches its admin-table read for several minutes; every admin CRUD write path must explicitly invalidate that cache or edits appear to "not save" for a while.

Legacy free-text category values (pre-dating the admin table) are NOT broken: unknown values fall back to keyword-based guessing + a humanized label, and an explicit "Other" sentinel preserves arbitrary custom text. Don't try to force every legacy value onto a curated slug — leave low-confidence matches alone rather than guessing wrong.

The public hashtag row merges two sources in a fixed order: admin-curated tags always come first (shown even with zero matching events — they're editorial curation, not an analytics rollup), then auto-computed trending tags backfill after, deduped.

When an admin-managed "tag" or "slug" field allows characters like hyphens in its seed/display data, the create/edit validation regex and the HTML `pattern` attribute must accept the same charset — a stricter regex than the seeded data silently makes existing seeded records uneditable.

**Why:** avoids a stale-admin-UI bug class (cached reads) and prevents an admin migration off a hardcoded list from breaking years of freely-typed legacy data.

**How to apply:** when adding a new admin-CRUD-backed replacement for a previously-hardcoded list, cache the read path with an explicit flush-on-write, keep a legacy/fallback branch instead of a hard migration, and double check validation regexes against the actual seeded values (not just idealized new input).
