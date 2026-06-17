---
name: Dark-mode legibility for hardcoded Tailwind light utilities
description: Why 1inme dashboard pages show white-on-dark, and the safe conversion pattern to themed CSS variables
---

# Dark-mode legibility in the 1inme Laravel dashboard

The theme is CSS-variable driven (`common/partials/theme-styles.blade.php`): default = dark, `html.light-mode` flips the vars. There is a JS-injected `light-mode-overrides` `<style>` that remaps Tailwind palette utilities (`text-slate-*`, `bg-slate-7xx`, etc.) **only for `html.light-mode`**. There is **no** symmetric dark-mode remapping.

**Consequence:** a page written with hardcoded LIGHT utilities (`bg-white`, `text-slate-900/700/600`, `text-gray-*`, `border-slate-200`, `bg-slate-50/100`) looks fine in light mode (overrides catch it) but renders white-on-dark / dark-on-dark in DARK mode (the default). `text-gray-*` is NOT even covered by the light overrides (only zinc/slate/neutral/stone), so gray utilities are doubly fragile.

**Fix pattern (per-element, NOT a global override):** convert the offending class to an inline `style="..."` using the themed vars — `--bg-card` (cards), `--bg-glass`/`--bg-glass-light` (subtle/chips), `--bg-glass-input` (form fields), `--border-glass` (borders), `--text-primary/-secondary/-muted/-faint`. Vars flip per mode so both modes read correctly. Light-gray ghost buttons (`bg-slate-100 text-slate-700 hover:bg-slate-200`) → existing `.btn-ghost` class. Reference: `user/stats/index.blade.php`.

**Why NOT a blanket `[class~="bg-white"]` dark override:** plain `bg-white` is also used for things that MUST stay white in both modes — toggle/switch knobs (`after:bg-white`, `rounded-full bg-white h-5 w-5` spans), QR-code quiet-zone backgrounds, preview iframes, and `bg-white/NN` opacity overlays on colored surfaces. A global remap would make knobs/QR vanish. Requires per-element judgment.

**Leave alone:** toggle knobs, opacity overlays (`bg-white/10`, `bg-white/90`), `text-white` on colored buttons, color-tinted text/badges, self-backgrounded status-badge ternaries (light chip + dark text, legible on any bg), and intentional white (QR, iframes, image overlays).
