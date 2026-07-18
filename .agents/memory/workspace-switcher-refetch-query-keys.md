---
name: Workspace switcher refetch query keys (mobile)
description: Which workspace query key actually refetches on foreground, for e2e that assert owner-gear/list updates.
---

The mobile app has TWO workspace-list React Query caches under different keys:

- `["workspaces"]` — used by `app/workspaces.tsx` (the full-screen list). Only
  refetched via pull-to-refresh (`RefreshControl` → `q.refetch()`), which is
  NOT reachable from a web/Playwright driver.
- `["workspaces-list"]` — used by `WorkspaceContext`, which the drawer
  `WorkspaceSwitcher` (components/DrawerSidebar.tsx) reads. The context wires
  `useForegroundRefresh(refresh)` → `refetch()`, so this cache refetches when
  the app returns to the foreground.

**Why:** an owner-gear / ownership-handoff e2e needs a refetch path drivable on
react-native-web. Only `["workspaces-list"]` has one: RNW's AppState maps
"change"→"active" onto the DOM `visibilitychange` event, so flipping
`document.visibilityState` + dispatching `visibilitychange` triggers the
context refetch.

**How to apply:** drive the DRAWER switcher (open menu → tap the
`Active workspace:` header to expand the dropdown), not the Workspaces screen,
when you need a live refetch to reflect a mocked backend change. The owner gear
is an `[aria-label="Edit workspace {name}"]` button bound to `ws.is_owner`.
Both surfaces share the same `is_owner` ternary. See
`scripts/test-workspace-owner-gear-refresh-e2e.mjs`.
