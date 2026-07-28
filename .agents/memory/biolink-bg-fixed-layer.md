---
name: Biolink background Fixed/Scroll layer contract
description: How bg_attachment renders on public biolink pages (fixed layer vs body) and which e2e specs assert it.
---

The rule: on the public biolink page, `bg_attachment=fixed` (the default) renders color/gradient/preset/image backgrounds on a dedicated `.bg-page-fixed` position:fixed layer behind the content — never via `background-attachment: fixed` on body. `bg_attachment=scroll` keeps the background on the scrolling body (image shorthand uses `scroll`; preset CSS gets `background-attachment: scroll !important` to neutralize catalog hardcodes). Slideshow and admin-template layers switch position fixed↔absolute the same way (template catalog/seeder CSS hardcodes `position:fixed`/`background-attachment:fixed`, so scroll mode overrides with `!important`).

**Why:** iOS/mobile Safari ignores `background-attachment: fixed`, so a fixed-position element layer is the only reliable "pinned" background; previously presets/gradients/colors ignored the toggle entirely.

**How to apply:** any spec or code that asserts/reads the page background must check `.bg-page-fixed` when attachment is fixed (default) and `<body>` when scroll — bg e2e specs (bg-preset-public, bg-branches, bg-preset-live-preview, bg-attachment) encode this. New background types should route through the same `$hasPageBgLayer` branch. Editor preview parity is automatic: previewDraft passes all form fields into the same renderer.
