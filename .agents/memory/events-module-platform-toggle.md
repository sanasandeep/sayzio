---
name: Platform-wide Events module toggle
description: How the events_module_enabled kill switch gates every events surface, and the easy-to-miss nav surfaces.
---

# Platform-wide Events module toggle

`EventsModule::enabled()` (AppSetting `events_module_enabled`, default ON, fail-open on missing table) is the single gate; `events.enabled` middleware aborts 404 and is tagged onto every events route (web /events + alias sub-routes, creator @handle/events, user links-ics/* + user.events.*, API event controllers).

**Why:** admins need one switch; per-surface toggles (marketing band) remain subordinate — module-off trumps them in EventsHeroBandComposer.

**How to apply / gotchas:**
- The public event page renders inside the `/{alias}` catch-all — cannot middleware it; the guard lives in RedirectController::handleEventTicketingPage.
- Nav hiding needs FOUR surfaces in lockstep: desktop sidebar + mobile drawer (app.blade.php), marketing header (Solutions entry + desktop AND mobile Events pills), and the **global-shortcuts command palette** (`common/partials/global-shortcuts.blade.php`) — the palette is the one everyone forgets and it made the "sidebar hides Events" test fail on page-content grep.
- Creator profile show() collapses `$upcomingEvents` to `collect()` when off.
- New events surfaces MUST consult EventsModule or tag `events.enabled`.
