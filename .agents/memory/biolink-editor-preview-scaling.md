---
name: Biolink editor device-preview scaling
description: Why the in-editor biolink preview text legibility is bound by a height budget, not font size
---

The biolink editor's live device preview (`resources/views/user/links/partials/device-preview.blade.php`) is a SINGLE mobile-first phone preview — there is no device switcher (phone/tablet/desktop) and no "Simulate as" (device/country) control anymore. It renders the public page in an iframe at the phone's real logical width (375px) and CSS-`transform: scale()`s it to fit the frame. Scale = frame_width / 375, computed in JS via a per-screen ResizeObserver. (The RedirectController still honors `_sim_country`/`_sim_device` server-side; only the frontend controls were removed.)

**Key tension:** preview legibility is governed by frame *width*, but the phone frame width is capped by a vertical *height budget* (`calc((100vh - Npx) * 375 / 812)`) so the whole 812px-tall phone stays on screen. Shrinking that budget = smaller phone = tinier scaled text (one-word-per-line). To make text readable, raise the width caps AND the `max(floor, …)` minimum so the phone never collapses on short viewports — accept that a large phone may slightly exceed a short viewport (it's sticky, top portion stays visible).

**Why:** do NOT "fix legibility" by bumping font sizes in `common/biolink.blade.php` — that's the public visitor page and would change what real visitors see. Legibility must be solved purely in the editor partial by scaling the iframe up.

**How to apply:** when the preview looks tiny, adjust the `.device-frame-phone` width caps / height-budget subtrahend in the editor partial; never touch public page typography.

**Manual zoom layer (rewritten):** zoom now ENLARGES the previewed content instead of shrinking a frame. Slider range is 100–200% (100% == auto-fit baseline). `_applyZoom()` clears the phone frame's inline width to measure the CSS fit baseline, then sets `width = base * zoom` when zoom>1; the wider frame → wider `.device-screen` → the ResizeObserver/`_scaleSingleIframe` scales the iframe UP, so the page renders bigger and more legible. Do NOT scale the `.device-frame` wrapper with `transform` (old approach) — widen its width instead. The frame lives in a `.device-preview-stage` flex wrapper with `overflow-x:auto; justify-content: safe center` so a zoomed-in phone scrolls horizontally rather than clipping. Persisted per-tab via `sessionStorage._previewZoom`.

**Refresh coalescing:** `refreshPreview()` is debounced ~300ms (wraps `_doRefreshPreview`) so a burst of edits (e.g. palette add → reorder persist) does ONE full iframe reload over the slow remote RDS, not several. `_doRefreshPreview` reloads all `.preview-iframe` + the pop-out if open. The pop-out is phone-only now (no device buttons).

## Color inputs must never be named form fields (Task-#4025 class)
`input[type=color]` always holds a browser-normalized solid 6-digit hex — it can't represent empty/transparent/8-digit/gradient values. If it's a *named* field in the Edit Block form, its normalized default gets stamped into `_style` on every save even when the user never touched the Look tab. Pattern: keep the picker UNNAMED, make a paired text/hidden input the named source of truth, and in `update()` treat an explicitly-submitted empty style value as "unset this key from _style" before the merge (sanitizeBlockStyle skips empties, so a plain merge can never clear).
