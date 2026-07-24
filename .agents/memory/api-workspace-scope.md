---
name: Sanctum API has no current_workspace binding
description: Why Laravel API creates on BelongsToWorkspace models land with workspace_id=null, and why the API index still returns them.
---

The Laravel 1inme sanctum API group (`routes/api.php`, `auth:sanctum` + `TouchSessionToken`) does NOT run the `SetActiveWorkspace` middleware. That middleware is web-only.

Consequences for any model using the `BelongsToWorkspace` trait (e.g. `Form`, `SocialProof`):
- On an API request `app()->bound('current_workspace')` is false, so the `creating` hook does NOT auto-fill `workspace_id`. New rows get `workspace_id = null`.
- The same unbound state means the trait's global `workspace` scope is skipped, so an API index like `Form::where('user_id', ...)` still returns those null-workspace rows. The mobile picker therefore sees freshly-created items.
- BUT the web forms list IS workspace-scoped, so a form/proof created via the API will not appear on web until/unless it gets a workspace_id.

**Why:** Established by the pre-existing `SocialAccountController@storeProof`, which creates with only `user_id` and no `workspace_id`. New API create endpoints (forms create-on-the-spot) deliberately match this pattern for consistency rather than resolving a workspace manually.

**How to apply:** When adding an API create endpoint for a `BelongsToWorkspace` model, expect null workspace_id and verify the corresponding API list path is unscoped on the API. If true cross-surface (web) visibility is required, you must resolve and set `workspace_id` explicitly — the API will not do it for you.

**Fillable trap (verified via tinker):** `workspace_id` is NOT in `$fillable` on most of these models (Link, Project, Domain, SplashPage, Contact, CreatorPost, QrCode, Follow, UserFile, …). So `Model::create([...,'workspace_id'=>x])` silently DROPS it (lands null) — mass-assign appears to work but doesn't. You must build then assign directly: `$m = new Model([...]); $m->workspace_id = $id; $m->save();`. A few models DO whitelist it (e.g. `ClientPortal`, `ClientPortalLink`) where mass-assign works — check the model's `$fillable` before trusting a `create()`.

**Shared model factory methods leak too:** `UserFile::createFromUpload()` does an internal `self::create()` with no workspace_id; on web the `creating` hook fills it, but API callers (e.g. ResumeController photo upload) must stamp workspace_id on the returned model after the call.

**Helpers added:** `ApiResponses::activeWorkspaceId(?User)` resolves first accessible → ensureDefaultWorkspace WITHOUT binding current_workspace (binding would re-enable the read-side global scope and break things like `Form::uniqueSlug` global uniqueness). `resolveWorkspaceId(?User,$requested)` honors a caller-supplied workspace_id only if accessible. Regression coverage: `tests/Feature/ApiCreateWorkspaceScopeTest.php`.

**Read-side danger (IDOR), not just write-side:** the same unbound scope means any API code that does `Model::find($clientSuppliedId)` on a `BelongsToWorkspace` model (e.g. resolving a `contact_id` passed in a create/update payload) returns ANY tenant's row, not just the caller's — a caller can pull another workspace's contact/record data into their own resource by guessing an id. Fix by explicitly scoping: `Model::withoutWorkspaceScope()->where('workspace_id', $ws->id)->find($id)`, using a `$ws` you've already resolved server-side (never trust a workspace_id in the request). Apply this to every foreign-id lookup an API endpoint accepts, not just the ones that create rows.

## Active-workspace parity (list scoping)
- Active workspace is now PERSISTED in `users.active_workspace_id`: web `WorkspaceContext::set()` writes it and `resolve()` falls back to it before "first accessible"; the API resolves via `ApiResponses::activeWorkspace()` and `POST /api/v1/workspaces/{id}/activate` (mobile switcher) writes it. Keep all three in lockstep or web/app lists desync again.
- API list/read endpoints that must mirror a web workspace-scoped list should filter `withoutWorkspaceScope()->where(user_id, ws.owner_user_id)->where(workspace_id, ws.id)` — caller-owned `user_id = auth id` is NOT parity (misses team-member views, spans all workspaces).
- LinkResource::toArray falls back to 2 per-link pixel queries; ANY batch serialization must call `LinkResource::preload()` first or a 100-row page takes minutes on distant RDS (was the mobile "Couldn't load" timeout).
