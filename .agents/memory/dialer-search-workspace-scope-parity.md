---
name: Dialer search account-level relations must opt out of workspace scope
description: Why Follow/Contact/Subscriber ID-set queries in DialerSearch must use withoutGlobalScope('workspace') for web/API parity.
---

# Dialer search: account-level relations vs. workspace scope

`DialerSearch` runs on two surfaces: the web dialer (under `workspace.scope` /
SetActiveWorkspace, so a `current_workspace` is bound) and the Sanctum API +
mobile (no workspace binding). The documented invariant is web == API.

**The trap:** `Follow`, `Contact`, and `Subscriber` all use the
`BelongsToWorkspace` trait. Following a creator, saving a contact-linked Sayzio
account, and subscribing to a creator are all *account-level* relationships —
but the trait's global `workspace` scope narrows every query on those models to
the active workspace on the web surface. Rows created while a different
workspace was bound (or with null workspace_id) silently drop out. The API
surface (no binding) returns them all → web returns FEWER records than API for
the same searcher.

**Symptom:** on a clean `migrate:fresh` DB, the `actingAsWeb` People/Followed
cases in `DialerSearchVisibilityTest` fail with "Failed asserting that an array
contains <id>" — a *reachable* creator/link that should surface is missing,
while the identical `asUser` (Sanctum) case passes. It looks like the
reachability GATE broke, but the gate is fine; the reachable SET was pruned by
the workspace scope before the gate ever ran.

**The rule:** every query in `DialerSearch` that resolves an account-level
relationship set must call `withoutGlobalScope('workspace')`:
`reachableUserIds()` (Follow + Contact), `followedLinkItems()` (the Follow
creator-id pluck), `subscribedCreatorIds()` (Subscriber), and the legacy
per-link path in `canViewLink()` (Follow + Subscriber `exists()` checks). The
`Link` queries already did this; the ID-set queries were missed.

**Why safe:** the `follower_id`/`user_id`/`email` predicates still scope each
query to the searcher, so opting out never widens WHO is reachable — it only
stops the active-workspace filter from hiding rows the searcher legitimately
owns/follows/subscribes to.

**Also opted out — `contactsAdvanced()` (the Contacts *group*):** address books
are account-wide. `contactsAdvanced()` is NOT actually shared with the web
contacts index page (that builds its own inline `Contact::where('user_id')`
query); its only real caller is the dialer Contacts group (`contactItems()`)
plus a scale test. So it now calls `withoutGlobalScope('workspace')` too, so the
web dialer Contacts group returns the searcher's FULL address book (contacts
saved while any workspace was bound), matching the Sanctum/mobile surface. The
`user_id` predicate still scopes to the searcher. Covered by
`test_web_search_returns_a_contact_saved_in_a_non_active_workspace`.

Note: the standalone web contacts index page (`ContactController::index`) is a
SEPARATE query and remains workspace-scoped by design (CRM data gated by
`workspace.can:settings.view`); only the dialer finder is account-wide.
