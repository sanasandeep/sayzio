---
name: Event organizer profile
description: Account-wide reusable organizer profile shown on event surfaces
---

Organizer details (logo, name, description, website, contact person, socials, address) are set **once per account**, not per event. Stored as a single JSON column (`users.organizer_profile`), mirroring the existing `socials` JSON column pattern already on `users`.

**Why:** the user explicitly rejected per-event organizer overrides — one creator can host many events and wants one profile to apply everywhere, and a single JSON blob avoids a parallel table just for a handful of optional fields.

**How to apply:** always read the profile through `User::organizerProfile()`, never the raw `organizer_profile` column — it normalizes every field to a string (blank when unset), plus a `socials` array and a `filled` boolean. Display surfaces (public event detail page's shared `event-rich-content` partial, and `/@handle/events`) branch on `filled` to choose between the rich organizer card and the old simple "Hosted by" avatar+name fallback — don't re-derive emptiness per-surface or the fallback logic will drift.
