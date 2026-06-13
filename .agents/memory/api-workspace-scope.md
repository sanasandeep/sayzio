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
