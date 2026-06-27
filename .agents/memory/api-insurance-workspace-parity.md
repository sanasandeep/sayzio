---
name: Stateless API workspace-collaborator parity
description: How to give a Sanctum API feature the same workspace-collaborator access the web feature has, when the API never binds an active workspace.
---

When porting a workspace-scoped web feature to the stateless Sanctum API, owner-only
`Link::where('user_id', me)` scoping is NOT parity — it locks out workspace
collaborators the web feature lets in.

**Rule:** resolve the record across the caller's *accessible* workspaces
(`User::accessibleWorkspaces()` = owned + member-of), then gate each action with
`User::canInWorkspace($ws, $permission)` (`links.view` on reads, `links.edit` on
mutations). The link owner and workspace owner always pass.

**Why:** the API never runs SetActiveWorkspace, so the `BelongsToWorkspace` global
scope is inactive — `Model::query()` returns ALL workspaces' rows. You must scope
explicitly; there is no active-workspace shortcut.

**How to apply:**
- Per-link methods: `findLink` resolves owner-link first (covers personal + API-created
  links whose `workspace_id` is null), then falls back to `whereIn('workspace_id',
  accessibleWorkspaceIds)`. A `canAct()` helper then enforces the per-action permission.
- **Enumeration endpoints (dashboards/lists) must PRE-FILTER the workspace id set by the
  read permission** (`accessibleWorkspaces()->filter(canInWorkspace(...,'links.view'))`),
  not rely on per-row gating — otherwise a member without `links.view` can enumerate the
  list. This was the specific code-review blocker.
- Guard every `workspace_id` query with `Schema::hasColumn('links','workspace_id')`.

**Web gotcha:** the web `authorizeLink` calls `workspace_can('links.edit')`, but that
helper does NOT exist (dead code) — on web, workspace *membership* (via the global scope)
is the real gate. The API check is stricter/correct; don't copy the dead web call.
