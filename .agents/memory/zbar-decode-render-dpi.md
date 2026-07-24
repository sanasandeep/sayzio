---
name: zbar QR verification render DPI
description: zbarimg fails on very large rasters; verify print PDFs at ~100dpi-per-QR scale
---
When verifying QR codes in print-collateral PDFs with zbarimg, render size matters more than sharpness: full A3 boards decode reliably at `pdftoppm -r 100` but FAIL at 300/600dpi (even cropped/thresholded). For N-up imposed sheets, render at the dpi that puts each embedded piece at ~100dpi equivalent (e.g. 200dpi for a 2x2 half-scale sheet).
**Why:** zbar's locator degrades on very large module sizes; high-dpi "safer" renders produce false negatives that look like broken QRs.
**How to apply:** if a QR "stops decoding" after rescaling/imposition, retry at lower render dpi before touching the artwork. Also: ImageMagick cannot produce correctly-sized PDF pages from PNGs here (always reports A5); use Playwright page.pdf with mm-sized @page + data-URL imgs.
