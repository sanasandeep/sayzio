---
name: Creator events page
description: How the per-creator public events surface (/@handle/events) is wired and tested
---

The per-creator events listing at `/@{handle}/events` shares one query helper
(`CreatorProfilePublicController::upcomingEventsQuery`) with the profile's
"Events" preview section — both filter `type=ics`, `is_active`, `visibility=public`,
upcoming via `icsData.start_date`, and the `hide_from_directory` opt-out, scoped
to `user_id`. The route is a 2-segment path registered right after `/@{handle}`,
so it can't collide with the single-segment `/{alias}` catch-all.

**Why:** keeps the directory-style filters in exactly one place instead of
duplicating them per surface, and the events() action mirrors show()'s full
gating chain (published → age-gate → block → country-gate) since it's an
alternate creator surface, not a lower-trust one.

**How to apply:** when adding another creator-scoped listing (e.g. a future
"products by this host"), follow this pattern — one static query builder on
the profile controller, reused by both the profile preview and its "see all"
page, gated the same way as `show()`.

Test note: `ics_data` requires `event_name` and `end_date` NOT NULL (no DB
default) — seeding a test event via tinker needs both set or the insert
throws 23502.
