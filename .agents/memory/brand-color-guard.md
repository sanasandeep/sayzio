---
name: Brand-color regression guard
description: The validation step that blocks the retired purple palette from re-entering primary UI surfaces, and why some surfaces are excluded.
---

# Brand-color guard

Validation step `brand-color` (script `scripts/src/check-brand-color.ts`) fails
CI if the RETIRED purple ramp (the three retired hexes + their rgb forms +
`violet-`/`purple-<shade>` Tailwind classes) appears in a PRIMARY UI surface of
the four product artifacts. The live brand accent is BLUE (`--color-primary-*`).
The script itself is the source of truth for the exact patterns, scanned roots,
and the documented allowlist (run it with `-- --explain`).

**Why some surfaces are excluded (intentional, not a miss):** purple is a
legitimate member of MULTI-COLOR categorical palettes — block-style pickers,
template galleries, decorative pitch-deck slides — and of palette/seed DATA, not
the brand accent. Re-coloring those would break the intended rainbow.

**How to apply:**
- If a NEW intentional categorical surface trips the guard, add it to the
  script's ALLOWLIST with a reason — don't widen the banned regex or recolor a
  deliberate palette. Everything not allow-listed must use brand blue.
- Don't trust a per-line grep when sweeping a file: inline `style="..."` hexes
  hide on lines separate from the Tailwind class hits. Run the full guard.
- spawnSync needs a large `maxBuffer` + the deck `pages/**` rg exclude, or the
  deck's ~180 decorative slides overflow the default 1MB buffer (ENOBUFS).
