---
name: Nested-interactive Blade guard
description: Static guard that fails CI when interactive elements are nested inside <button>/<a> in Blade views; scanner gotchas.
---

Guard: `pnpm --filter @workspace/scripts run check:nested-interactive` (validation workflow `nested-interactive`, source `scripts/src/check-nested-interactive.ts`).

**Rule:** never nest an interactive element (`button`, `a`, `input`, `select`, `textarea`, `details`, `label`, `iframe`, `embed`) inside an open `<button>` or `<a>` in a Blade view.

**Why:** the HTML parser force-closes an outer `<button>` at a nested `<button>`, ejecting later markup from its layout column (broke the admin Block Defaults editor); `<button>`-in-`<a>` and similar are invalid HTML even when the parser copes.

**How to apply:** the sanctioned fix is a `<span role="button" tabindex="0">` with `@click.stop` + `@keydown.enter` handlers, or restructure so the control sits outside the anchor/button (e.g. outer `<div role="tab"/"button" tabindex="0">` with JS Enter/Space activation, as done in the home AI-suite cards and admin marketing-SEO accordion headers).

Scanner gotchas learned while building it:
- Must blank `@php … @endphp` bodies — PHP comments mentioning `<a>` otherwise open a phantom container that flags the whole rest of the file.
- Blade conditionals need snapshot/RESTORE semantics, not stack-clearing: snapshot the open-element stack at `@if/@unless/@isset/@forelse`, restore at `@else/@elseif/@empty`, pop at `@end*`. Clearing on `@else` erases a legitimate outer `<a>` wrapping the conditional and masks real offenders.
- Tokenize tags with quoted-attr-skipping regex so `@click="a < b"` / `x-html="'<a>'"` never split a tag.
