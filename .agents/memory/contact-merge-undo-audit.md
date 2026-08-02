---
name: Contact merge undo audit pattern
description: How contact-merge undo works and the restore-cast gotcha.
---

# Contact merge undo audit

- Merges record a `contact_merge_audits` row in the same transaction: raw `getAttributes()` snapshot of the loser (+ phones/emails) and a per-table map of moved row ids (incl. phone/email ids the merge ADDED to the primary). Audit-write failure is swallowed so it never breaks the merge.
- Undo repoints recorded rows ONLY where they still point at the primary — later re-merges win. Primary-side scalar/tags/notes fills and Google sync linkage are intentionally NOT reverted (documented in the undo service docblock).

**Restore-cast gotcha:** the snapshot holds RAW column values (JSON casts already encoded as strings). Rehydrating with `forceFill` runs the casts again and double-encodes JSON columns like `tags`. Use `setRawAttributes()` (with string datetimes) when restoring a model from a `getAttributes()` snapshot.

**How to apply:** any future snapshot/restore or soft-undo feature that persists `getAttributes()` output must restore via `setRawAttributes`, and its "moved rows" repointing must be conditional on the row still pointing where the undone operation left it.
