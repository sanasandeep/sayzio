---
name: Playwright PDF layout vs viewport
description: In-page measurements for pg.pdf() output must be taken with the viewport sized to the physical page.
---
Rule: when generating print PDFs with Playwright and measuring/adjusting layout via `pg.evaluate` (e.g. auto-fit scaling of mock tiles), set the viewport to the page's physical pixel size (`mm * 96 / 25.4`) before `setContent`. Emulating print media is NOT enough.

**Why:** `pg.pdf()` lays out at the PDF page size while the default 1280x720 viewport gives flex-driven containers different heights, so measured frame sizes (and computed scale factors) silently disagree with the printed output — content looked fine in measurements but was cropped in the PDF.

**How to apply:** any generate script that inspects `getBoundingClientRect` before `pg.pdf()` needs `pg.setViewportSize` per piece. Also: a `margin-top:auto` footer does not prevent page overflow — bound the middle flex row with `flex:1;min-height:0` or bottom content (QRs) gets clipped past the page edge.
