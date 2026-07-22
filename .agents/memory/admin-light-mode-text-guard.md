---
name: Admin light-mode text guard (ratchet)
description: How the check:admin-light-mode-text guard enforces ak-* pairing on admin blades via a per-file baseline ratchet
---

The `admin-light-mode-text` validation workflow (`check:admin-light-mode-text`) scans admin blade `class` attributes for dark-only text tokens (bare/low-opacity `text-white`, 100–300 tint shades) lacking an `ak-*` helper on the same element.

**Why:** nothing structural stopped new admin pages from shipping unreadable light-mode text; a full sweep was infeasible (~3.8k pre-existing hits in 153 files), so enforcement is a RATCHET.

**How to apply:**
- New admin blades (not in `scripts/src/data/admin-light-mode-baseline.json`) must be clean; baselined files may only shrink. After fixing files, re-tighten with `-- --update-baseline` (note the double `--` through pnpm).
- Auto-exempt: white text with a solid `bg-*-500..950` / `bg-gradient-to-*` / `bg-black` / arbitrary `bg-[#hex]` surface in the same static class string; prefixed variants (`hover:`, `dark:`) ignored.
- Baseline is now ZERO (`{}`) — every admin blade must be clean going forward.
- Visual spot-check: `lm-visual-verify` workflow runs `artifacts/1inme/lm-verify.mjs` (demo-admin CSRF login + html.light-mode + luminance asserts). Gotcha: `/admin/templates` page tab hangs for minutes (wasCustomized/isOutdatedBlueprint over 422 seeded rows re-instantiates the seeder per row); verify via `?tab=card` instead.
- ak-* in the STATIC part clears the whole element; inside a `{{ ternary }}` each quoted branch needs its own ak-*/solid signal.
- Also scans dynamic surfaces: quoted strings in `:class`/`x-bind:class` (per-string ak/solid, exempt if the tag's static class has ak/solid OR the tag/parent window paints a `:style` background — dynamic icon tiles), `=>` arm strings in `@php` blocks (match arms), and `'inputClass' =>` partial args.
- Gotcha: an ak-* token dropped OUTSIDE the quoted string inside a `:class` expression (`'…' ak-blue"`) is an Alpine JS syntax error that silently breaks the binding — pair inside the string or on the static class attr.
