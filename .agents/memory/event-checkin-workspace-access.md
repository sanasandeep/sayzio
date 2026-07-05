---
name: Event check-in workspace-aware access
description: How door check-in/scan authorization was extended from owner-only to any authorized workspace member, and where free RSVP tickets fit into the ticket model.
---

Event check-in ("door scanner") authorization spans three call paths that must
stay in lockstep whenever access rules change:

1. Web `EventCheckinController::scanner/scan/progress` — gated by
   `workspace.can:links.view` (view routes) / `links.edit` (scan route)
   middleware, and internally key resources off `workspace_owner_id()`
   (binds to the active workspace's owner when a team member is acting
   inside someone else's workspace via session `SetActiveWorkspace`).
2. Web `EventCheckinController::lookup()` — the QR-code landing page, reached
   *unauthenticated* by a generic camera app, so it can't rely on route
   middleware. It has its own `canCheckIn($user, Link $link)` helper: owner
   bypass OR `Workspace::find($link->workspace_id)` +
   `$user->canInWorkspace($workspace, 'links.edit')`.
3. Stateless API `EventTicketApiController` — no session, so no
   `workspace_owner_id()`/`SetActiveWorkspace` binding exists. Access is
   resolved per-request via `findEventLink()` (owner match OR
   `whereIn('workspace_id', accessibleWorkspaceIds())`) + `canAct($request,
   $link, $permission)` (owner bypass, else `canInWorkspace()`). This mirrors
   `LinkInsuranceController`'s pattern and should be reused for any other
   "owner-only Link::where('user_id', ...)" API endpoint that needs workspace
   team-member parity.

**Why:** the API path has no workspace binding at all (see
api-workspace-scope.md), so naive owner-id checks silently exclude
legitimate team members who have workspace access on the web. Any
user-facing copy on the unauthenticated QR-lookup page that says "sign in
as the owner" also needs to be updated in lockstep, or it misdescribes who
can actually check a ticket in.

**How to apply:** when adding/auditing an "owner acts on a Link" API
endpoint, check whether it does a literal `user_id` compare — if so, convert
to a `findXLink()`/`canAct()` pair rather than special-casing that one route.

A free RSVP can get a QR check-in ticket the same way a paid ticket-tier
purchase does: a tier-less ticket record (nullable tier reference, FK back
to the RSVP) so the door-scan/check-in code path is identical for both.
Keep a single sync function that mints/revives/cancels that ticket based on
the RSVP's current status+response, and call it from every surface that can
change an RSVP (initial submit AND any guest self-manage update/cancel) —
never mint the ticket inline in more than one place, or a status edit on one
surface can silently orphan/duplicate the ticket.
