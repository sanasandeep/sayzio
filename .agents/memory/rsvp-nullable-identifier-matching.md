---
name: RSVP/guest matching on nullable identifiers
description: Never match rsvps (or other guest rows) by a nullable email/phone alone — NULL matches strangers' rows.
---

# Guest-row matching with nullable identifiers

Rule: when reconciling a signed-in user to their `rsvps` row (or any anonymous-capable guest table), never use a nullable identifier column as the sole key. `where('email', $user->email)` with a mobile-only user (email NULL) becomes `WHERE email IS NULL` and selects — then mutates — some other attendee's email-less row.

**Why:** users.email is nullable (WhatsApp/mobile-only signups); rsvps.email is nullable (anonymous form entries). A completion code review caught a connect flow converting a stranger's RSVP to "yes" this way.

**How to apply:** prefer a (link_id, user_id)-keyed attribution row that stores the rsvp_id; fall back to email/phone matches only when that identifier is non-empty. See EventConnectQrController::connect + EventConnectQrTest's email-less test for the pattern.
