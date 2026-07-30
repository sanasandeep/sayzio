---
name: Alpine @change re-dispatch infinite loop
description: Never $dispatch('change') from inside a @change handler on the same element — .stop doesn't prevent the target's own handler from re-firing.
---

Rule: on form controls (`<select>`, `<input>`) never wire `@change="$dispatch('change')"` (even with `.stop` and `$nextTick`). The dispatched bubbling CustomEvent fires the element's own `@change` handler again — `.stop` only blocks propagation, not the target listener — creating an infinite `$nextTick` dispatch loop that pegs the CPU and eventually crashes the renderer ("Target crashed" in Playwright).

**Why:** the biolink appearance Tiles selects did exactly this; the page looked fine on a quick manual glance but died after sustained interaction, and the e2e spec reliably crashed Chromium at the same selectOption.

**How to apply:** native `change` on selects/inputs already bubbles to the form's listener, so no re-dispatch is needed at all. `$dispatch('change')` is only for non-form elements (button swatch clicks) that produce no native change event.
