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
