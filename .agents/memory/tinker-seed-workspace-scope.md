---
name: Tinker-seeded workspace-scoped fixtures
description: Why e2e fixtures seeded via artisan tinker are invisible to workspace-scoped queries, and the fix.
---

Rule: any fixture row for a model using the `BelongsToWorkspace` trait (Project, etc.) that is seeded via `php artisan tinker` must have `workspace_id` set explicitly — assign the property and `save()`, because `workspace_id` is typically NOT mass-assignable and is silently dropped from `create([...])`.

**Why:** Tinker/CLI has no bound `current_workspace`, so the trait's auto-fill is skipped and the row lands with `workspace_id = NULL`. Authenticated web requests DO bind a workspace, so the global scope filters the row out — the seeded fixture simply never renders, a silent, misleading e2e failure. Mass-assigning `workspace_id` in `create()` also fails silently (not fillable).

**How to apply:** In seed PHP, mirror `WorkspaceContext::resolve` for a fresh session: `Workspace::find((int)($u->active_workspace_id ?? 0)) ?? Workspace::where('owner_user_id', $u->id)->orderBy('id')->first()`, then `$model->workspace_id = $ws?->id; $model->save();`. Dashboard/desk-style queries should read via `workspace_owner()` (not `auth()->user()`) so team members see the active workspace's rows.
