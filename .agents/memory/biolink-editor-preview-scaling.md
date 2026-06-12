---
name: Biolink editor device-preview scaling
description: Why the in-editor biolink preview text legibility is bound by a height budget, not font size
---

The biolink editor's live device preview (`resources/views/user/links/partials/device-preview.blade.php`) renders the public page in an iframe at the device's real logical width (phone 375px, tablet 768px, desktop 1440px) and CSS-`transform: scale()`s it down to fit the frame. Scale = frame_width / logical_width, computed in JS via a per-screen ResizeObserver.

**Key tension:** preview legibility is governed by frame *width*, but the phone frame width is capped by a vertical *height budget* (`calc((100vh - Npx) * 375 / 812)`) so the whole 812px-tall phone stays on screen. Shrinking that budget = smaller phone = tinier scaled text (one-word-per-line). To make text readable, raise the width caps AND the `max(floor, …)` minimum so the phone never collapses on short viewports — accept that a large phone may slightly exceed a short viewport (it's sticky, top portion stays visible).

**Why:** do NOT "fix legibility" by bumping font sizes in `common/biolink.blade.php` — that's the public visitor page and would change what real visitors see. Legibility must be solved purely in the editor partial by scaling the iframe up.

**How to apply:** when the preview looks tiny, adjust the `.device-frame-phone` width caps / height-budget subtrahend in the editor partial; never touch public page typography.
