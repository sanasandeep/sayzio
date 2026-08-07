---
name: Events module flag on mobile
description: How the mobile app learns and gates on the platform-wide Events module switch.
---

The platform-wide Events module switch (`EventsModule::enabled()`, AppSetting `events_module_enabled`) is surfaced to mobile via `GET /api/v1/feature-states` as a top-level `data.events_module_enabled` boolean alongside `features`.

**Why:** the API 404s every events endpoint when the module is off; without a bootstrap flag the app shows raw errors.

**How to apply:**
- Mobile reads it through `hooks/useFeatureStates.ts` (`eventsModuleEnabled`, fails OPEN true while loading/error/absent field — older APIs).
- Entry points hidden: DrawerSidebar item filter (`href === "/events"`) and profile TOOL_PAGES filter (`/events`, `/events/my-tickets`).
- Every screen under `app/events/*` wraps its default export in `components/EventsModuleGate.tsx`, which renders a graceful "not available" state — so deep links never hit 404s. New event screens must add the same wrapper.
- Any new platform module toggle should ride the same feature-states overview response rather than a new bootstrap endpoint.
