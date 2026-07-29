---
name: SortableJS filter zones block input focus
description: Why preventOnFilter:false is required on editor Sortable lists containing inline form editors
---

The rule: any SortableJS list whose `filter` selector covers a region containing form inputs (e.g. `.inline-block-editor` in the biolink editor) MUST set `preventOnFilter: false`.

**Why:** Sortable's default `preventOnFilter: true` calls `preventDefault()` on every pointerdown inside filtered zones. That silently kills text-cursor placement/focus in all inputs there — users report "I can't even put a cursor in the field". Nothing shows in hit-testing (elementFromPoint returns the input, pointer-events auto, not readonly), so it looks unexplainable. Playwright `fill()` still works (programmatic), so existing e2e specs missed it — only a real mouse click + `document.activeElement` check reproduces it.

**How to apply:** when adding/editing Sortable configs with `filter`, add `preventOnFilter: false`. Drag initiation stays constrained by `handle`, so this loses nothing. Repro/regression spec: `tests/Browser/profile-card-name-focus.spec.ts`.

Also: Chrome treats ports 5060/5061 as unsafe (`ERR_UNSAFE_PORT`) — never use them for VALIDATION_PORT.
