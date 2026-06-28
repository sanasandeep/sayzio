---
name: AI Artistic QR decode verification
description: How the QR builder confirms an AI Artistic QR actually scans, and the artistic-strength control
---

The AI Artistic QR builder (`user/qr-codes/builder.blade.php`) now decodes the
returned Replicate artwork client-side with **jsQR** to confirm it genuinely
scans to the right destination — the old behaviour only re-ran the heuristic
scannability score and never decoded the real image.

- jsQR is **self-hosted** at `public/js/vendor/jsqr.min.js` (pinned 1.4.0, no SRI,
  per the vendored-libs policy). Its UMD hits the browser branch and sets
  `window.jsQR`; that is the global the builder reads. (Note: `require()`-ing the
  file in Node returns an empty object when a parent `package.json` has
  `"type":"module"`, because Node parses it as ESM and the UMD falls through to
  the browser branch — this is expected and irrelevant to the browser.)
- Verification draws the artwork onto a canvas and runs
  `jsQR(pixels.data, w, h, {inversionAttempts:'attemptBoth'})`, comparing the
  decoded string to the encoded destination (`design.ai_art.data`).
  Statuses: `pass` / `mismatch` / `fail` / `unknown` / `checking`.
- It is **purely client-side**, so there is no extra coin charge for verifying.
- **Cross-origin caveat:** for local-disk `/storage` images (the default) decode
  works. If user content is on S3/CloudFront without CORS headers, the
  `crossOrigin='anonymous'` decode image fails to load (or the canvas taints) and
  verification reports `unknown` ("scan it yourself"); the displayed `<img>` is
  unaffected because it has no crossOrigin attribute.

**Artistic strength** control (0–100, default 60) maps inversely onto the model's
`qr_conditioning_scale` in `QrArtService::conditioningScale()`:
`scale = 2.0 - (strength/100)*0.9` (strength 0 → 2.0 most faithful/scannable,
100 → 1.1 most artistic). Default 60 → ~1.46, preserving the prior fixed 1.5.
On a failed/mismatch verdict the UI nudges the user to lower strength + regenerate.

`strength` and `verify.status` are persisted through `QrCodeDesignSanitizer`
(`sanitizeAiArt`) so they round-trip on save; on edit the builder re-runs the
decode live rather than trusting the stored status.
