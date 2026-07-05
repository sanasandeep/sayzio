---
name: Delivery Project calendar privacy tiers
description: How each Delivery Project's own calendar (project/workspace/public) reuses the existing Calendar/CalendarEvent model and rollup mechanics.
---

Each `DeliveryProject` gets its own `Calendar` (one-to-one via `delivery_project_id`), auto-created by `ensureCalendar()`. Task start/due dates are projected as `CalendarEvent`s and kept in sync purely via `DeliveryProjectTask::booted()` model events (`saved` → `DeliveryProjectCalendarSync::syncTaskEvent`, `deleting` → `deleteTaskEvent`) — no controller-side sync calls needed for normal task CRUD. Bulk deletes (e.g. `DeliveryProject::destroy()`) bypass model events, so the calendar+events must be cleaned up explicitly before bulk-deleting tasks.

Privacy has 3 tiers stored on the **Calendar** row (not the DeliveryProject row): `project` (default, participants + client share link only), `workspace` (rolls into every workspace member's My Calendar), `public` (subscribable ICS link).

**Why:** `calendarPrivacy()` reads `$this->calendar?->privacy`, not a DeliveryProject column — changing privacy means updating the Calendar's `privacy` + `is_public` together (see `DeliveryProjectController::updateCalendarPrivacy`), not the project. `is_public=true` is exactly the existing followable-calendar public flag, so `PublicCalendarController::icsFeed`/`toggleFollow` needed zero changes — the public tier is 100% free reuse.

**How to apply:** Workspace-tier rollup into "My Calendar" / workspace views goes through `CalendarController::workspaceProjectCalendarIds($user)` (calendars with `delivery_project_id` set, `workspace_id` in the user's `accessibleWorkspaces()`, and `privacy` in `[workspace, public]`) merged into `buildMyCalendarQuery()` and `myCalendarFeed()`. Project-tier is deliberately excluded from every rollup — it only ever renders via `_readonly.blade.php` (share-link/portal, which have project-level access regardless of tier) or the project's own dashboard page.
