---
name: Biolink palette DnD spec — in-place insert, no navigation
description: tests/Browser/biolink-editor-palette-dnd.spec.ts asserts in-place insert (no reload) and is now a gated e2e spec
---

The palette-create path (`paletteCreateBlock` in `biolink-editor.blade.php`)
inserts the new block **in place** and does NOT reload — only the
move-existing-block (`onAdd` → `doMoveBlock`) and card-add paths call
`location.reload()`. The spec's `dropPalette` helper reflects that: it asserts
the target list (`#blockList > .block-card-wrapper` or
`.card-child-list[data-card-id] > .child-block-card`) gains one card and the
"Block added" success toast shows — NOT `waitForNavigation`.

This spec is now **gated** in the `e2e` validation step (see
browser-e2e-validation-gate.md). It seeds via `php artisan tinker`, logs in as
the demo user, and drives the real drop pipeline through the `window.__editorTest`
hook (armed only when a Playwright init script sets `window.__E2E__`).

**Why this matters:** if you change the "Add blocks" palette markup/Alpine and
this spec fails, it is NOT a navigation issue — the drop pipeline never reloads.

**How to apply:** the palette tiles' drop contract is `.palette-block-item`
elements (flat direct children of `#paletteList`) carrying `data-block-type`;
keep that intact and drops keep working. The helpers use generous explicit
timeouts (openEditor goto/waitForFunction 120s; dropPalette toHaveCount/toast
60s) because cold editor renders + store round-trips over the distant RDS far
exceed Playwright defaults — a 30s default here surfaces as an intermittent
"waitForFunction timeout" that is really just slowness, not a logic bug.

**Per-test cold-render settle guard:** any position-sensitive drop (index 1 /
append / inside-card) MUST first `expect.poll(topLevelLabels).toEqual([Divider,
Spacer,Card Container])` BEFORE calling `dropPalette`. `openEditor` only waits
for `window.__editorTest` to exist — `#blockList` can still be rendering, so a
drop fired immediately can land at the wrong index (the "BETWEEN two blocks"
flake). The TOP test always had this guard; the BETWEEN/END/INSIDE tests did
not and were the flaky ones — keep the baseline poll on every new drop test.
