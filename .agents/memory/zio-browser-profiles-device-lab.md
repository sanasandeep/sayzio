---
name: Zio Browser workspace profiles + device lab
description: Architecture decisions for workspace profiles and device lab features in artifacts/zio-browser
---

## Workspace profiles

**Profile ID**: `'default'` for personal; workspace ID string (e.g. `'42'`) for workspace profiles.

**Session partition**: `persist:zio-profile-{profileId}` — one Electron session partition per profile, created via `session.fromPartition()`.

**DB scoping**: `profile_id TEXT NOT NULL DEFAULT 'default'` on history/bookmarks/collections. Active profile is tracked PER WINDOW (`windowProfileRegistry` in `ipc-handlers.ts`, keyed by BrowserWindow id) — there is intentionally no process-global active profile, so switching in one window never changes the DB scope or session partition of another window. IPC handlers resolve the profile from `event.sender`'s window; `profiles:switch` only mutates the calling window's entry (and persists `active_profile` preference as the initial profile for NEW windows).

**Schema version**: bumped to 6; `MIGRATION_SQL[6]` ALTERs existing tables (splits on `;`, ignores "already exists").

**Sync isolation**: `profileSyncEntityKey(entity, profileId)` → `'bookmarks:default'` / `'history:42'` — keeps sync_state cursors separate per profile.

**Startup restore**: `main/index.ts` reads `active_profile` preference and calls `registerWindowProfile(win, id)` + `session.fromPartition()` before creating the first tab (private windows register the same last-used profile for reads).

**Renderer**: `useProfileStore` (module-level singleton, not React context) syncs workspaces from `/api/v1/workspaces` and calls `profiles:upsert-from-workspace` for each. `ProfileSwitcher` component in ChromeBar.

## Device Lab

- Implemented purely in the renderer as a full-screen overlay (`DeviceLab.tsx`).
- Fetches biolinks via `device-lab:list-biolinks` IPC → main calls `/api/v1/links?type=biolink&limit=50` with the stored bearer token.
- Three frames: Phone 375px, Tablet 768px, Desktop 1280px — each an `<iframe>` with `transform:scale(columnWidth/nativeWidth)` and `transformOrigin:'0 0'`.
- `pointerEvents:none` on iframes (preview only, no interaction).
- `sandbox="allow-scripts allow-same-origin"` sufficient for public biolink pages.
- Opened via 🔬 button in ChromeBar; also accessible from DashboardLayout and SplitLayout wrappers.

## Laravel server (BrowserSyncController)

- `resolveWorkspaceId()` reads `X-Browser-Workspace-Id` header, verifies workspace ownership/membership, returns `int|null`.
- All push/pull queries add `.where('workspace_id', $workspaceId)` — null = personal profile.
- Migration `2029_07_18_000003` adds nullable `workspace_id` + widens unique index to `(user_id, workspace_id, local_id)`.

**Why:**
- Profile isolation is the security boundary: a workspace user must not see another workspace's bookmarks/history.
- Per-window profile tracking exists precisely because a process-global active profile would let a switch in one window silently rescope background sync/reads in another.
