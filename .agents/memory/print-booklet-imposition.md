---
name: Print booklet imposition pipeline
description: How the Sayzio A3 saddle-stitch booklet reuses the stall-collateral print pipeline, and the pitfalls hit.
---
- booklet.ts imports shared pieces (BASE_CSS, art*, qrSvg, doc, wordmark, vis) from generate.ts; generate.ts stays directly runnable via `if (import.meta.url === pathToFileURL(process.argv[1]).href)` guard. Import as "./generate.js" (tsconfig lacks allowImportingTsExtensions).
- Vector imposition: each A3 side (426×303 incl. bleed) hosts two `.half` containers 213mm wide with overflow:hidden; right page offset left:-3mm so the inner bleed is trimmed at the fold. Fully vector, no rasters.
- Multi-page PDF: one HTML doc, `.sheetbox` divs exact bleed size with break-after:page + @page size + pg.pdf without pageRanges.
- **Pitfall:** page-builder CSS must be injected into the render doc — unstyled classes silently render (giant natural-size QR imgs, broken flex lists). Visually proof low-DPI pdftoppm renders.
- QR verify: pdftoppm -r 100 + zbarimg on reader AND imposed PDFs; tiny decorative QRs inside art mockups won't decode — document as decorative.
- **Why:** print jobs are expensive to reprint; on-screen structure can look fine while CSS is missing.
