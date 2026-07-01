---
name: Dialer caller-ID reachability gate
description: The dialer has a caller-ID enrichment surface (separate from search) that also needs the status+block reachability gate.
---

The Dialer resolves a phone number to a Sayzio creator on TWO independent
families of surfaces, and both must apply the same reachability gate
(creator `status == active` — self exempt — AND the creator has NOT blocked the
searcher via `UserBlock.blocked_user_id = searcher`):

1. **Search finder** — `DialerSearch` (People / Followed groups). Gate is inline
   in `peopleItems` / `followedLinkItems`.
2. **Caller-ID enrichment** — resolving a number to a creator's name/handle/
   biolink. Gate lives in `DialerReachability` (`reaches()` / `enrichableCreator()`)
   and is applied at THREE call sites:
   - `Api\DialerController::lookup` (`/api/v1/dialer/lookup`)
   - `DialerIdentity::resolve` (backs web + API `/dialer/profile`)
   - `BiolinkAttachResolver::resolveFor` (silent auto-attach that SEEDS
     `contact.biolink_user_id`, i.e. the `$contact->biolinkUser` read path)

**Why:** neither `contact.biolink_user_id` nor `LinkedIdentifier::resolveUser()`
checks reachability, so without the gate a suspended/deactivated or blocking
creator's identity leaks on caller-ID even after the search gate was fixed.

**How to apply:** any change to the reachability rule (new status value, block
semantics) must touch BOTH `DialerSearch` and `DialerReachability`. The block
check is directional — only "they blocked me" hides an account; a searcher-side
block does not. Read-side gating covers stale attachments (a creator suspended
after auto-attach), so the auto-attach only needs to skip seeding new ones.
Tests: `DialerCallerIdReachabilityTest` (mirrors `DialerSearchVisibilityTest`).

**Stale-attachment cleanup (write-side):** read-side gating only HIDES a stale
`contact.biolink_user_id`; the row persists and silently reappears if the creator
becomes reachable again. `BiolinkAttachResolver::reconcile()` (scheduled hourly
via `contacts:reconcile-attachments`) actively clears it, gated by the SAME
`DialerReachability::reaches()` (single source of truth — don't re-derive the
status/block rule). Decision that matters: a **block** (creator blocked owner)
also records the id in detach memory (`detached_biolink_user_ids`) so an unblock
can't silently re-attach; a **suspension/deactivation/deletion** is only cleared
so reactivation re-attaches via `resolveFor`. Detach memory is never undone.
Test: `ContactAttachmentReconcileTest`.
