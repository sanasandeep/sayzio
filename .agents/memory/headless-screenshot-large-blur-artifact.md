---
name: Headless screenshots vs large CSS blur layers
description: Viewport-sized fixed elements with filter blur(~80px) can rasterize as opaque blobs covering the whole page in headless Chromium screenshots, even though the DOM paints correctly.
---

A `position:fixed`, viewport-sized layer with `filter: blur(80px)` (the marketing `.aurora`) made headless Playwright/Screenshot captures of dark-mode marketing pages look like the entire page content was missing — only giant color blobs rendered.

**Why:** software rasterization in headless Chromium mishandles very large blur radii on huge layers; real browsers render fine. Computed styles, `elementsFromPoint`, and effective-opacity checks all showed the content painted correctly.

**How to apply:** when a headless screenshot shows content "hidden" behind glow/aurora layers, don't assume a stacking bug. Verify with DOM oracles (computed opacity/color, elementFromPoint at the element's center). To get a usable screenshot, inject `.aurora{filter:blur(20px)!important}` (or display:none) before capture. Note `pointer-events:none` layers are skipped by elementsFromPoint, so they can't be found that way.
