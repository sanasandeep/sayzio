---
name: Alpine $root inside nested x-data
description: Why $root.prop breaks accordions/sub-components nested in another x-data — rely on scope inheritance instead.
---

Rule: inside a nested `x-data` component, never reference parent state via `$root.prop`. Just use the bare property name (`prop`) — Alpine's scope chain reads AND writes through to the nearest ancestor component that owns it.

**Why:** `$root` resolves relative to the Alpine tree, and with nested/sibling `x-data` roots (e.g. a sub-accordion card that has its own `x-data` for local fields) it can point at the wrong component, so `$root.openCard = ...` silently mutates a component that doesn't drive the `x-show`. This is exactly why the biolink editor's Display Settings "Schedule" and "Limits & Scarcity" cards wouldn't open while sibling cards without their own `x-data` worked.

**How to apply:** when an accordion/toggle inside a nested `x-data` doesn't respond, grep for `$root.` first; replace with the inherited bare name. Writes via the scope proxy land on the owning ancestor, so no event plumbing is needed.
