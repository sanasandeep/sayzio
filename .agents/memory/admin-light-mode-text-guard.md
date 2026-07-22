---
name: Admin light-mode text guard (ratchet)
description: How the check:admin-light-mode-text guard enforces ak-* pairing on admin blades via a per-file baseline ratchet
---

The `admin-light-mode-text` validation workflow (`check:admin-light-mode-text`) scans admin blade `class` attributes for dark-only text tokens (bare/low-opacity `text-white`, 100–300 tint shades) lacking an `ak-*` helper on the same element.

**Why:** nothing structural stopped new admin pages from shipping unreadable light-mode text; a full sweep was infeasible (~3.8k pre-existing hits in 153 files), so enforcement is a RATCHET.

**How to apply:**
- New admin blades (not in `scripts/src/data/admin-light-mode-baseline.json`) must be clean; baselined files may only shrink. After fixing files, re-tighten with `-- --update-baseline` (note the double `--` through pnpm).
- Auto-exempt: white text with a solid `bg-*-500..950` / `bg-gradient-to-*` / `bg-black` surface in the same static class string; prefixed variants (`hover:`, `dark:`) ignored.
- ak-* in the STATIC part clears the whole element; inside a `{{ ternary }}` each quoted branch needs its own ak-*/solid signal.
