---
name: Mobile biolink block editor silently-missing field UI
description: How the mobile block editor decides which field inputs render per block type, and why a type can silently get zero editable fields.
---

The mobile block editor (`app/links/[id]/blocks/[blockId].tsx`) renders generic
text-input fields from `blockKind(block.type)?.fields` (defined in
`lib/api/blocks.ts` `BLOCK_KINDS`). If a `links.type`/block type exists on the
public renderer and web editor but has no `BLOCK_KINDS` entry, `meta` resolves
to `null` and **zero fields render** — not even a generic fallback — so the
block silently becomes uneditable on mobile (looked "supported" because the
public renderer already handles it, e.g. via `PreviewBlueprint.tsx` /
`biolink/[handle].tsx`).

**Why:** `Object.entries(block.settings)` still hydrates every primitive value
into the generic `values` map regardless of `BLOCK_KINDS`, so data isn't lost,
but with no field list nothing is rendered for the creator to edit. Types with
a bespoke editor UI (list/pricing, map_location, profile_card) intentionally
keep `fields: []` and render their own section instead — an empty `fields`
array is not itself a bug, but a *missing* `BLOCK_KINDS` entry combined with
no bespoke `is<Type>` section is.

**How to apply:** when auditing mobile parity for a block/link type, don't just
grep for the type string — check whether `BLOCK_KINDS` has an entry AND
(if fields is empty) whether a bespoke `is<Type>` section actually renders in
the JSX. `map_location` was exactly this: type existed everywhere except the
editor, fixed by reusing the existing `MapPickerModal` (same component already
used by `app/calendars/event.tsx`) rather than building a new picker.
