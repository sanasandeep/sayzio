---
name: Section-header pill copy vs Playwright has-text selectors
description: Decorative text added inside a toggle button can collide with e2e button:has-text selectors for sibling controls.
---

Rule: never put words matching other clickable controls' labels (tab names like "Look", "Text", "Designs", "Preset(s)") into decorative pills/subtitles rendered INSIDE a `<button>`.

**Why:** Playwright `button:has-text("Look")` matches substrings of the whole button text. A "Designs · Text · Look" pill inside the Block Styling toggle made the toggle match the Look-tab selector → strict-mode violations broke 4 e2e-block-defaults specs.

**How to apply:** when adding badge/pill/subtitle copy inside accordion toggles or buttons in the biolink editor, pick synonyms that don't appear on sibling tab/chip labels (e.g. "Themes · Fonts · Colors"), then rerun the editor e2e suites.

Related pre-existing failure (July 2026): biolink-bg-preset-live-preview.spec.ts expects a "Gradient 1" swatch, but the gradients group was hidden from the Presets picker upstream — stale spec, not an editor regression.
